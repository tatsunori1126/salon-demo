<?php
if (!defined('ABSPATH')) exit;

/***********************************************************
 * 共通関数群（全システムで使用）
 * ---------------------------------------------------------
 * - サロンの営業時間、時間変換、範囲判定などの汎用ロジック
 * - helpers.php の統合最新版
 * - function_exists で多重定義防止済み
 ***********************************************************/


/** 営業時間 → タイムスロット生成 */
if (!function_exists('salon_time_slots')) {
  function salon_time_slots($from = null, $to = null, $step = null) {
    $s = salon_get_store_settings();
    $from = $from ?: $s['open_time'];
    $to   = $to   ?: $s['close_time'];
    $step = $step ?: intval($s['time_step']);
    $out = [];
    $t = strtotime($from);
    $end = strtotime($to);
    while ($t <= $end) {
      $out[] = date('H:i', $t);
      $t += $step * 60;
    }
    return $out;
  }
}

/** 時刻文字列 → 分換算 */
if (!function_exists('salon_time_to_min')) {
  function salon_time_to_min($time) {
    if (strpos($time, ':') === false) return 0;
    list($h, $m) = explode(':', $time);
    return intval($h) * 60 + intval($m);
  }
}

/** 時刻範囲内判定 */
if (!function_exists('salon_between')) {
  function salon_between($time, $start, $end) {
    $t = salon_time_to_min($time);
    $s = salon_time_to_min($start);
    $e = salon_time_to_min($end);
    if ($t === null || $s === null || $e === null) return false;
    return ($t >= $s) && ($t < $e);
  }
}

/** 出勤メタキー生成 */
if (!function_exists('salon_shift_meta_key')) {
  function salon_shift_meta_key($ym = '') {
    if (empty($ym)) $ym = date('Ym');
    return 'salon_shift_' . $ym;
  }
}

/** シフトメタ正規化 */
if (!function_exists('salon_normalize_shift_meta')) {
  function salon_normalize_shift_meta($raw, $ym) {
    if (!$raw) return [];
    // 旧フォーマット対応
    if (array_values($raw) === $raw && is_int(reset($raw))) {
      $store = salon_get_store_settings();
      $open  = $store['open_time'];
      $close = $store['close_time'];
      $out = [];
      foreach ((array)$raw as $d) {
        $out[$d] = ['s' => $open, 'e' => $close];
      }
      return $out;
    }
    // 正規形式
    $out = [];
    foreach ((array)$raw as $day => $pair) {
      $s = $pair['s'] ?? '';
      $e = $pair['e'] ?? '';
      if ($s && $e && salon_time_to_min($e) > salon_time_to_min($s)) {
        $out[(int)$day] = ['s' => $s, 'e' => $e];
      }
    }
    return $out;
  }
}

/** スタッフ一覧取得 */
if (!function_exists('salon_get_staff_users')) {
  function salon_get_staff_users() {
    return get_users([
      'role'    => 'salon_staff',
      'orderby' => 'display_name',
      'order'   => 'ASC',
      'fields'  => ['ID', 'display_name', 'user_login']
    ]);
  }
}

/** ----------------------------------------------
 * スタッフの出勤可否チェック（静音安定版）
 * ---------------------------------------------- */
if (!function_exists('salon_is_staff_available')) {
  function salon_is_staff_available($staff_id, $date, $time) {
    if (!$staff_id || !$date || !$time) return false;

    $ym = date('Ym', strtotime($date));
    $shift_key  = salon_shift_meta_key($ym);
    $shift_meta = get_user_meta($staff_id, $shift_key, true);

    // 代替キー参照
    if (empty($shift_meta)) {
      $shift_meta = get_user_meta($staff_id, 'salon_staff_info', true);
    }

    // start/end → s/e に統一
    if (is_array($shift_meta)) {
      foreach ($shift_meta as $k => $v) {
        if (isset($v['start']) || isset($v['end'])) {
          $shift_meta[$k] = [
            's' => $v['start'] ?? ($v['s'] ?? ''),
            'e' => $v['end']   ?? ($v['e'] ?? ''),
          ];
        }
      }
    }

    // 正規化
    $shift_norm = salon_normalize_shift_meta((array)$shift_meta, $ym);
    $day_key = date('j', strtotime($date));
    $shift = $shift_norm[$day_key] ?? null;
    if (!$shift || empty($shift['s']) || empty($shift['e'])) return false;

    $t = salon_time_to_min($time);
    $s = salon_time_to_min($shift['s']);
    $e = salon_time_to_min($shift['e']);

    return ($t >= $s && $t < $e);
  }
}

/** ----------------------------------------------
 * 重複予約チェック（施術時間も考慮 + デバッグログ入り）
 * ---------------------------------------------- */
if (!function_exists('salon_is_time_available')) {
  function salon_is_time_available($staff_id, $date, $time, $duration) {

    error_log("=== [CHECK START] staff={$staff_id} date={$date} time={$time} duration={$duration} ===");

    $start_ts = strtotime("$date $time");
    $end_ts   = $start_ts + ($duration * 60);

    // ▼ 対象スタッフの“同日の既存予約”を取得
    $reservations = get_posts([
      'post_type'   => 'reservation',
      'post_status' => 'publish',
      'numberposts' => -1,
      'meta_query'  => [
        ['key' => 'res_date',  'value' => $date],
        ['key' => 'res_staff', 'value' => $staff_id],
      ]
    ]);

    error_log("=== existing_reservations_count=" . count($reservations) . " ===");

    foreach ($reservations as $r) {

      $r_time = get_post_meta($r->ID, 'res_time', true);
      $r_menu = get_post_meta($r->ID, 'res_menu', true);

      // ▼ 施術時間は meta から取得（※超重要）
      $r_duration = intval(get_post_meta($r->ID, 'res_duration', true));

      if ($r_duration <= 0) {
        error_log("!!! WARNING: res_duration missing for reservation {$r->ID}");
      }

      $r_start = strtotime("$date $r_time");
      $r_end   = $r_start + ($r_duration * 60);

      error_log(">>> R[{$r->ID}] time={$r_time}, menu={$r_menu}, duration={$r_duration}");
      error_log(">>> R range = {$r_start} - {$r_end}, New = {$start_ts} - {$end_ts}");

      // ▼ 1分でもかぶっていれば NG
      if ($start_ts < $r_end && $end_ts > $r_start) {
        error_log("!!! conflict FOUND with reservation {$r->ID} !!!");
        return false;
      }
    }

    error_log("=== [CHECK END] OK ===");
    return true;
  }
}



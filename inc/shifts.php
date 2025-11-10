<?php
if (!defined('ABSPATH')) exit;

/***********************************************************
 * 出勤管理（管理画面・保存）
 ***********************************************************/

/**
 * 管理メニュー追加
 */
add_action('admin_menu', function() {
  add_menu_page(
    '出勤管理',
    '出勤管理',
    'read',
    'salon-shifts',
    'salon_render_shifts_page',
    'dashicons-groups',
    26
  );
});

/**
 * 出勤管理ページ本体
 */
function salon_render_shifts_page() {
  $current  = wp_get_current_user();
  $is_admin = in_array('administrator', (array)$current->roles, true);
  $uid      = $is_admin ? intval($_GET['user'] ?? $_POST['user'] ?? $current->ID) : $current->ID;
  $ym       = preg_replace('/[^0-9]/', '', ($_GET['ym'] ?? $_POST['ym'] ?? date('Ym')));

  // ✅ 保存処理
  if (isset($_POST['save_shift'])) {
    check_admin_referer('save_shift_' . $ym);
    if ($is_admin && !empty($_POST['user'])) $uid = intval($_POST['user']);

    $starts = $_POST['start'] ?? [];
    $ends   = $_POST['end'] ?? [];

    $year  = (int)substr($ym, 0, 4);
    $month = (int)substr($ym, 4, 2);
    $days_in_month = date('t', strtotime("{$year}-{$month}-01"));

    $save = [];
    for ($d = 1; $d <= $days_in_month; $d++) {
      $s = sanitize_text_field($starts[$d] ?? '');
      $e = sanitize_text_field($ends[$d] ?? '');
      $save[$d] = [
        's'    => $s,
        'e'    => $e,
        'work' => ($s && $e) ? 1 : 0,
      ];
    }

    $meta_key = salon_shift_meta_key($ym);
    update_user_meta($uid, $meta_key, $save);

    // ✅ 保存後リロード
    echo "<script>location.href='?page=salon-shifts&user={$uid}&ym={$ym}&saved=1';</script>";
    exit;
  }

  // ✅ 出勤データ読み込み
  $meta_key = salon_shift_meta_key($ym);
  $shift = get_user_meta($uid, $meta_key, true);

  // 後方互換（旧キー対応）
  if (empty($shift)) {
    $shift = get_user_meta($uid, 'salon_staff_info', true);
  }

  // 🔧 フォーマット正規化（旧データ対応）
  $fixed_shift = [];
  foreach ((array)$shift as $k => $v) {
    if (isset($v['s']) || isset($v['e'])) {
      $fixed_shift[(int)$k] = [
        'start' => $v['s'] ?? '',
        'end'   => $v['e'] ?? ''
      ];
    } elseif (isset($v['start']) || isset($v['end'])) {
      $fixed_shift[(int)$k] = $v;
    }
  }
  $shift = $fixed_shift;

  // ===== カレンダー描画 =====
  $times = salon_time_slots();
  $year  = (int)substr($ym, 0, 4);
  $month = (int)substr($ym, 4, 2);
  $days  = (int)date('t', strtotime("$year-$month-01"));

  echo '<div class="wrap"><h1>出勤管理</h1>';
  if (!empty($_GET['saved'])) {
    echo '<div class="notice notice-success"><p>保存しました ✅</p></div>';
  }

  // 管理者：スタッフ切り替えセレクト
  echo '<form method="get"><input type="hidden" name="page" value="salon-shifts">';
  if ($is_admin) {
    echo 'スタッフ：<select name="user">';
    foreach (salon_get_staff_users() as $u) {
      printf('<option value="%d"%s>%s</option>', $u->ID, selected($uid, $u->ID, false), esc_html($u->display_name));
    }
    echo '</select> <button class="button">変更</button>';
  } else {
    echo '<strong>' . esc_html($current->display_name) . '</strong>';
  }
  echo '</form>';

  // 月ナビゲーション
  $dt   = DateTime::createFromFormat('Ym', $ym);
  $prev = $dt->modify('-1 month')->format('Ym');
  $next = DateTime::createFromFormat('Ym', $ym)->modify('+1 month')->format('Ym');

  printf('<p><a class="button" href="?page=salon-shifts&user=%d&ym=%s">前月</a> ', $uid, $prev);
  printf('<a class="button" href="?page=salon-shifts&user=%d&ym=%s">今月</a> ', $uid, date('Ym'));
  printf('<a class="button" href="?page=salon-shifts&user=%d&ym=%s">次月</a></p>', $uid, $next);

  echo '<form method="post">';
  wp_nonce_field('save_shift_' . $ym);
  echo '<input type="hidden" name="user" value="' . $uid . '">';
  echo '<input type="hidden" name="ym" value="' . $ym . '">';
  echo "<h2>{$year}年 {$month}月</h2><div class='salon-shift-grid'>";

  // ===== 日ごとの行を描画 =====
  for ($d = 1; $d <= $days; $d++) {
    $w = (int)date('w', strtotime("$year-$month-$d"));
    $jp = ['日', '月', '火', '水', '木', '金', '土'][$w];
    $cur = $shift[$d] ?? ['start' => '', 'end' => ''];

    echo "<div class='salon-shift-cell'><div class='salon-shift-date'>{$d}日（{$jp}）</div>";

    // 開始時間
    echo "<div class='time-row'><label>開始</label><select name='start[{$d}]'><option value=''>—</option>";
    foreach ($times as $t) {
      printf('<option value="%s"%s>%s</option>', esc_attr($t), selected($cur['start'], $t, false), $t);
    }
    echo "</select></div>";

    // 終了時間
    echo "<div class='time-row'><label>終了</label><select name='end[{$d}]'><option value=''>—</option>";
    foreach ($times as $t) {
      printf('<option value="%s"%s>%s</option>', esc_attr($t), selected($cur['end'], $t, false), $t);
    }
    echo "</select></div>";

    echo "</div>";
  }

  echo '</div>';
  submit_button('保存', 'primary', 'save_shift');
  echo '</form></div>';
}

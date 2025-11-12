<?php
if (!defined('ABSPATH')) exit;

/***********************************************************
 * フロント用カレンダー生成（指名あり／なし対応）【祝日個別＋臨時休業対応 完全版】
 ***********************************************************/
function salon_generate_calendar_html($menu_key, $staff_id = 0, $week = 0, $mode = 'front') {
  date_default_timezone_set('Asia/Tokyo');
  $store             = salon_get_store_settings();
  $holidays          = $store['holidays'] ?? [];
  $holiday_closures  = $store['holiday_closures'] ?? []; // 祝日個別休業
  $special_holidays  = $store['special_holidays'] ?? []; // 臨時休業
  $time_step         = intval($store['time_step'] ?? 30);
  $times             = salon_time_slots();

  // ===== 週の日付一覧 =====
  $today = strtotime('today');
  $start = strtotime("+" . (7 * intval($week)) . " days", $today);
  $week_dates = [];
  for ($i = 0; $i < 7; $i++) $week_dates[] = date('Y-m-d', strtotime("+$i day", $start));

  // ===== スタッフ対象 =====
  $staffs = [];
  if ($staff_id > 0) {
    $u = get_userdata($staff_id);
    if ($u) $staffs = [$u];
  } else {
    $all_staffs = salon_get_staff_users();
    foreach ($all_staffs as $s) {
      $uid = $s->ID;
      $menu_settings = get_user_meta($uid, 'salon_menu_settings', true);
      if (!empty($menu_settings[$menu_key]['enabled']) && intval($menu_settings[$menu_key]['enabled']) === 1) {
        $staffs[] = $s;
      }
    }
  }

  // ====== 予約情報の取得（ブロック考慮＋全スタッフ対応） ======
  $booked = [];
  $posts = get_posts([
    'post_type'   => 'reservation',
    'post_status' => 'publish',
    'numberposts' => -1,
    'meta_query'  => [
      ['key' => 'res_date', 'value' => $week_dates, 'compare' => 'IN']
    ]
  ]);

  foreach ($posts as $p) {
    $pid   = $p->ID;
    $date  = get_post_meta($pid, 'res_date', true);
    $time  = get_post_meta($pid, 'res_time', true);
    $sid   = intval(get_post_meta($pid, 'res_staff', true));
    $menu  = get_post_meta($pid, 'res_menu', true);
    if (!$date || !$time) continue;

    // メニュー時間取得
    $menu_duration = 60;
    $base_staff = ($sid > 0) ? get_userdata($sid) : current(salon_get_staff_users());
    if ($base_staff) {
      $settings = get_user_meta($base_staff->ID, 'salon_menu_settings', true);
      $menu_duration = intval($settings[$menu]['duration'] ?? 60);
    }

    $start_ts = strtotime("$date $time");
    $before_minutes = $menu_duration - $time_step;
    $block_start_ts = strtotime("-{$before_minutes} minutes", $start_ts);
    $block_end_ts   = strtotime("+{$menu_duration} minutes", $start_ts);

    $target_staffs = ($sid === 0) ? salon_get_staff_users() : [get_userdata($sid)];

    foreach ($target_staffs as $stf) {
      if (!$stf) continue;
      $uid = $stf->ID;
      $menu_settings = get_user_meta($uid, 'salon_menu_settings', true);
      if (!empty($menu_settings[$menu]['enabled']) && intval($menu_settings[$menu]['enabled']) === 1) {
        for ($t = $block_start_ts; $t < $block_end_ts; $t += ($time_step * 60)) {
          $block_time = date('H:i', $t);
          $booked[$uid][$date][$block_time] = true;
        }
      }
    }
  }

  // ===== 出勤データの取得 =====
  $shifts = [];
  foreach ($staffs as $s) {
    $uid = $s->ID;
    $shifts[$uid] = [];
    foreach ($week_dates as $d) {
      $ym = date('Ym', strtotime($d));
      $meta_key = salon_shift_meta_key($ym);
      $shift_data = get_user_meta($uid, $meta_key, true);
      $fixed = [];
      foreach ((array)$shift_data as $k => $v) {
        if (isset($v['s']) || isset($v['e'])) $fixed[(int)$k] = ['start' => $v['s'] ?? '', 'end' => $v['e'] ?? ''];
        elseif (isset($v['start']) || isset($v['end'])) $fixed[(int)$k] = $v;
      }
      $day = (int)date('j', strtotime($d));
      if (isset($fixed[$day])) $shifts[$uid][$day] = $fixed[$day];
    }
  }

  // ===== 出力 =====
  ob_start(); ?>
  <div class="calendar-nav" data-week="<?php echo intval($week); ?>">
    <button type="button" class="cal-prev-week">← 前の週</button>
    <button type="button" class="cal-this-week">今週</button>
    <button type="button" class="cal-next-week">次の週 →</button>
  </div>
  <table class="calendar-table">
    <thead>
      <tr>
        <th class="time-col">時間</th>
        <?php foreach ($week_dates as $d): ?>
          <th><?php echo esc_html(date('n/j (D)', strtotime($d))); ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($times as $time): ?>
      <tr>
        <td class="time-col"><?php echo esc_html($time); ?></td>
        <?php foreach ($week_dates as $d):
          $wd = date('w', strtotime($d));

          // ✅ 定休日・祝日・臨時休業判定
          $is_holiday = false;
          if (in_array((string)$wd, $holidays, true)) $is_holiday = true;
          if (in_array($d, (array)$holiday_closures, true)) $is_holiday = true;
          if (in_array($d, (array)$special_holidays, true)) $is_holiday = true;

          if ($is_holiday) {
            echo '<td class="holiday">休</td>';
            continue;
          }

          $has_shift   = false;
          $has_vacancy = false;

          foreach ($staffs as $s) {
            $uid = $s->ID;
            $ym  = date('Ym', strtotime($d));
            $day = (int)date('j', strtotime($d));
            $shift = $shifts[$uid][$day] ?? null;
            if (!$shift || empty($shift['start']) || empty($shift['end'])) continue;
            if (!salon_between($time, $shift['start'], $shift['end'])) continue;
            $has_shift = true;
            $is_booked = !empty($booked[$uid][$d][$time]) || !empty($booked[0][$d][$time]);
            if (!$is_booked) {
              $has_vacancy = true;
              break;
            }
          }

          if (!$has_shift) {
            echo '<td class="off">—</td>';
          } elseif ($has_vacancy) {
            printf(
              '<td class="available"><button type="button" class="slot-btn" data-date="%s" data-time="%s" data-staff="0">○</button></td>',
              esc_attr($d), esc_attr($time)
            );
          } else {
            echo '<td class="booked">×</td>';
          }

        endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php
  return ob_get_clean();
}


/***********************************************************
 * 🎯 指名スタッフ専用カレンダー（指名なし予約も考慮）
 ***********************************************************/
if (!function_exists('salon_generate_calendar_html_with_shared_blocks')) {
  function salon_generate_calendar_html_with_shared_blocks($menu_key, $staff_id, $week = 0) {
    date_default_timezone_set('Asia/Tokyo');

    $store            = salon_get_store_settings();
    $holidays         = $store['holidays'] ?? [];
    $holiday_closures = $store['holiday_closures'] ?? [];
    $special_holidays = $store['special_holidays'] ?? [];
    $time_step        = intval($store['time_step'] ?? 30);
    $times            = salon_time_slots();

    $today = strtotime('today');
    $start = strtotime("+" . (7 * intval($week)) . " days", $today);
    $week_dates = [];
    for ($i = 0; $i < 7; $i++) $week_dates[] = date('Y-m-d', strtotime("+$i day", $start));

    ob_start(); ?>
    <div class="salon-calendar">
      <h3 class="cal-title">空き状況（1週間）</h3>
      <div class="cal-legend"><span>○：予約可</span><span>×：予約済</span><span>—：出勤なし</span></div>
      <table class="cal-table">
        <thead>
          <tr>
            <th>時間</th>
            <?php foreach ($week_dates as $d): ?>
              <th><?php echo esc_html(date('n/j (D)', strtotime($d))); ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($times as $time): ?>
            <tr>
              <th><?php echo esc_html($time); ?></th>
              <?php foreach ($week_dates as $d): ?>
                <?php
                $wd = date('w', strtotime($d));
                $is_holiday = false;
                if (in_array((string)$wd, $holidays, true)) $is_holiday = true;
                if (in_array($d, (array)$holiday_closures, true)) $is_holiday = true;
                if (in_array($d, (array)$special_holidays, true)) $is_holiday = true;

                if ($is_holiday) {
                  echo '<td class="holiday">休</td>';
                  continue;
                }

                if (!salon_is_staff_available($staff_id, $d, $time)) {
                  echo '<td class="off">—</td>';
                  continue;
                }

                $q = new WP_Query([
                  'post_type'      => 'reservation',
                  'post_status'    => 'any',
                  'posts_per_page' => -1,
                  'meta_query'     => [
                    'relation' => 'AND',
                    ['key' => 'res_date', 'value' => $d],
                    [
                      'relation' => 'OR',
                      ['key' => 'res_staff', 'value' => (string)$staff_id, 'compare' => '='],
                      ['key' => 'res_staff', 'value' => '0', 'compare' => '='],
                    ],
                  ],
                ]);

                $is_booked = false;
                if ($q->have_posts()) {
                  while ($q->have_posts()) {
                    $q->the_post();
                    $res_time = get_post_meta(get_the_ID(), 'res_time', true);
                    if ($res_time === $time) { $is_booked = true; break; }
                  }
                  wp_reset_postdata();
                }

                echo $is_booked ? '<td class="booked">×</td>' : '<td class="available">○</td>';
                ?>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
    return ob_get_clean();
  }
}


/***********************************************************
 * 読み取り専用カレンダー（管理・確認用）【祝日＋臨時休業対応】
 ***********************************************************/
if (!function_exists('salon_generate_readonly_calendar')) {
  function salon_generate_readonly_calendar($menu_key, $staff_id = 0, $week = 0) {
    date_default_timezone_set('Asia/Tokyo');

    $store            = salon_get_store_settings();
    $holidays         = $store['holidays'] ?? [];
    $holiday_closures = $store['holiday_closures'] ?? [];
    $special_holidays = $store['special_holidays'] ?? [];
    $times            = salon_time_slots();
    $staffs           = salon_get_staff_users();

    $today = strtotime('today');
    $start = strtotime('+' . (7 * intval($week)) . ' days', $today);
    $week_dates = [];
    for ($i = 0; $i < 7; $i++) $week_dates[] = date('Y-m-d', strtotime("+$i day", $start));

    ob_start(); ?>
    <div class="salon-calendar readonly">
      <table class="calendar-table">
        <thead>
          <tr>
            <th class="time-col">時間</th>
            <?php foreach ($week_dates as $d): ?>
              <th><?= esc_html(date('n/j (D)', strtotime($d))) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($times as $time): ?>
            <tr>
              <th class="time-col"><?= esc_html($time) ?></th>
              <?php foreach ($week_dates as $d):
                $w = date('w', strtotime($d));
                $is_holiday = false;
                if (in_array((string)$w, $holidays, true)) $is_holiday = true;
                if (in_array($d, (array)$holiday_closures, true)) $is_holiday = true;
                if (in_array($d, (array)$special_holidays, true)) $is_holiday = true;

                if ($is_holiday) {
                  echo '<td class="holiday">休</td>';
                  continue;
                }

                $has_shift = false;
                $has_vacancy = false;

                foreach ($staffs as $u) {
                  $uid = $u->ID;
                  if (!salon_is_staff_available($uid, $d, $time)) continue;
                  $has_shift = true;

                  $q = new WP_Query([
                    'post_type'      => 'reservation',
                    'post_status'    => 'any',
                    'posts_per_page' => -1,
                    'meta_query'     => [
                      'relation' => 'AND',
                      ['key' => 'res_date', 'value' => $d],
                      [
                        'relation' => 'OR',
                        ['key' => 'res_staff', 'value' => (string)$uid, 'compare' => '='],
                        ['key' => 'res_staff', 'value' => '0', 'compare' => '='],
                      ],
                    ],
                  ]);

                  $is_booked = false;
                  if ($q->have_posts()) {
                    while ($q->have_posts()) {
                      $q->the_post();
                      $res_time = get_post_meta(get_the_ID(), 'res_time', true);
                      $menu     = get_post_meta(get_the_ID(), 'res_menu', true);
                      $settings = get_user_meta($uid, 'salon_menu_settings', true) ?: [];
                      $dur      = intval($settings[$menu]['duration'] ?? 60);
                      $start_ts = strtotime("$d $res_time");
                      $end_ts   = $start_ts + ($dur * 60);
                      $chk_ts   = strtotime("$d $time");
                      if ($chk_ts >= $start_ts && $chk_ts < $end_ts) {
                        $is_booked = true;
                        break;
                      }
                    }
                    wp_reset_postdata();
                  }

                  if (!$is_booked) {
                    $has_vacancy = true;
                    break;
                  }
                }

                if (!$has_shift) echo '<td class="off">—</td>';
                elseif ($has_vacancy) echo '<td class="available">○</td>';
                else echo '<td class="booked">×</td>';
              endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
    return ob_get_clean();
  }
}

/**
 * ▼ ショートコード（管理・確認用）
 */
add_shortcode('salon_calendar_readonly', function($atts) {
  $staff = intval($atts['staff'] ?? 0);
  return salon_generate_readonly_calendar('default', $staff);
});

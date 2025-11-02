<?php
/**
 * Template Name: Salon Front Page
 */
get_header();
date_default_timezone_set('Asia/Tokyo');

/* =========================
   週移動
========================= */
$week_shift = isset($_GET['week']) ? intval($_GET['week']) : 0;

/* 今週（日曜始まり） */
$today_w = date('w');
$start_of_week = ($today_w == 0) ? strtotime('today') : strtotime('last sunday');
$start_of_week = strtotime(($week_shift>0?"+$week_shift week":($week_shift<0?"$week_shift week":"0 week")), $start_of_week);

/* 週の日付配列 */
$week_dates = [];
for ($i=0; $i<7; $i++) {
    $week_dates[] = date('Y-m-d', strtotime("+$i day", $start_of_week));
}

/* 営業時間（30分刻み） */
$times = [];
for ($t = strtotime("09:00"); $t <= strtotime("19:30"); $t += 1800) {
    $times[] = date("H:i", $t);
}

/* スタッフ */
$staff_users = get_users([
    'role'    => 'salon_staff',
    'orderby' => 'display_name',
    'order'   => 'ASC',
    'fields'  => ['ID','display_name'],
]);
$staffs = [];
foreach ($staff_users as $u) {
    $staffs[$u->ID] = $u->display_name;
}

/* 予約データ */
$q = new WP_Query([
    'post_type'      => 'reservation',
    'posts_per_page' => -1,
]);
$res_data = [];
$name_to_id = [];
foreach ($staff_users as $u) { $name_to_id[$u->display_name] = $u->ID; }

while ($q->have_posts()) {
    $q->the_post();
    $pid  = get_the_ID();
    $date = get_post_meta($pid, 'res_date', true);
    $time = get_post_meta($pid, 'res_time', true);
    $stf  = get_post_meta($pid, 'res_staff', true); // 基本は user_id。旧は display_name の可能性。
    $name = get_post_meta($pid, 'res_name', true);
    $menu = get_post_meta($pid, 'res_menu', true);

    if ($date && $time) {
        // 後方互換：display_name だった場合 user_id に変換（見つからなければ 0）
        $staff_id = is_numeric($stf) ? intval($stf) : ( $name_to_id[$stf] ?? 0 );
        $res_data[$date][$time][$staff_id] = ['name'=>$name,'menu'=>$menu];
    }
}
wp_reset_postdata();

/* 出勤：各スタッフの該当月の配列を取得 → 日付に変換 */
$attendance = []; // $attendance[スタッフID][$Y-m-d] = true
foreach ($staff_users as $u) {
    // 週内の各日付の月キー
    $ym_keys = [];
    foreach ($week_dates as $d) { $ym_keys[ date('Ym', strtotime($d)) ] = true; }
    $attendance[$u->ID] = [];

    foreach (array_keys($ym_keys) as $ym) {
        $days = (array) get_user_meta($u->ID, "salon_shift_".$ym, true); // [1,3,15,…]
        if (!$days) continue;
        $year = intval(substr($ym,0,4));
        $mon  = intval(substr($ym,4,2));
        foreach ($days as $num) {
            $dstr = sprintf('%04d-%02d-%02d', $year, $mon, intval($num));
            $attendance[$u->ID][$dstr] = true;
        }
    }
}

/* ナビリンク */
$prev_week  = esc_url(add_query_arg('week', $week_shift - 1));
$next_week  = esc_url(add_query_arg('week', $week_shift + 1));
$today_week = esc_url(remove_query_arg('week'));

$week_days = ['日','月','火','水','木','金','土'];
?>

<div class="salon-calendar">

  <div class="week-nav" style="text-align:center;margin-bottom:12px;">
    <a href="<?= $prev_week ?>" class="btn-week">← 前の週</a>
    <a href="<?= $today_week ?>" class="btn-week" style="margin:0 8px;">今週</a>
    <a href="<?= $next_week ?>" class="btn-week">次の週 →</a>
  </div>

  <h2 style="text-align:center;">1週間の予約カレンダー</h2>

  <table class="calendar-table" style="width:100%;border-collapse:collapse;">
    <thead>
        <tr>
            <th style="width:80px;"></th>
            <?php foreach ($week_dates as $d): ?>
                <?php
                  $w = $week_days[ date('w', strtotime($d)) ];
                  // 当日の出勤スタッフ名をヘッダー直下で表示
                  $on_names = [];
                  foreach ($staffs as $sid => $sname) {
                      if (!empty($attendance[$sid][$d])) $on_names[] = $sname;
                  }
                ?>
                <th style="text-align:center;border:1px solid #ddd;padding:6px 4px;">
                  <?= esc_html(date('n/j', strtotime($d))) ?>（<?= esc_html($w) ?>）<br>
                  <?php if ($on_names): ?>
                    <small style="color:#2c7a4b;display:inline-block;margin-top:4px;">
                      <?= esc_html(implode('・', $on_names)) ?>
                    </small>
                  <?php else: ?>
                    <small style="color:#999;display:inline-block;margin-top:4px;">出勤なし</small>
                  <?php endif; ?>
                </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
      <?php foreach ($times as $time): ?>
        <tr>
          <th style="text-align:center;border:1px solid #eee;padding:6px 4px;white-space:nowrap;"><?= esc_html($time) ?></th>

          <?php foreach ($week_dates as $d): ?>
            <td style="border:1px solid #f0f0f0;padding:4px;">
              <?php foreach ($staffs as $sid => $sname): ?>
                <?php if (!empty($res_data[$d][$time][$sid])): ?>
                  <div class="booked" style="background:#ffe5e5;border:1px solid #ffb8b8;border-radius:6px;padding:6px 8px;margin:4px 0;font-size:12px;">
                    <?= esc_html($res_data[$d][$time][$sid]['name']) ?><br>
                    <small><?= esc_html($res_data[$d][$time][$sid]['menu']) ?></small>
                  </div>
                <?php elseif (!empty($attendance[$sid][$d])): ?>
                  <div class="work" style="background:#e8f7ed;border:1px solid #bfe8cd;border-radius:6px;padding:6px 8px;margin:4px 0;text-align:center;font-size:12px;color:#20824f;">出勤</div>
                <?php else: ?>
                  <div class="empty" style="height:28px;margin:4px 0;border:1px dashed #eee;border-radius:6px;"></div>
                <?php endif; ?>
              <?php endforeach; ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php get_footer(); ?>

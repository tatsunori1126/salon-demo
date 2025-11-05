<?php
/**
 * Template Name: Manage Calendar
 */
get_header();
date_default_timezone_set('Asia/Tokyo');

$week_shift = isset($_GET['week']) ? intval($_GET['week']) : 0;

// 今週（日曜始まり）
$today_w = date('w');
$start = ($today_w == 0) ? strtotime('today') : strtotime('last sunday');
$start = strtotime(($week_shift>0?"+$week_shift week":($week_shift<0?"$week_shift week":"0 week")), $start);

// 週の日付配列
$week_dates = [];
for ($i=0; $i<7; $i++) $week_dates[] = date('Y-m-d', strtotime("+$i day", $start));

// 営業時間
$times = salon_time_slots();

// スタッフ
$staff_users = get_users([
    'role'    => 'salon_staff',
    'orderby' => 'display_name',
    'order'   => 'ASC',
    'fields'  => ['ID','display_name']
]);
$staffs = [];
foreach ($staff_users as $u) $staffs[$u->ID] = $u->display_name;

// 予約取得
$q = new WP_Query([
    'post_type'=>'reservation',
    'posts_per_page'=>-1,
]);
$res_data = [];
$res_post_ids = [];
$name_to_id = [];
foreach ($staff_users as $u){
    $name_to_id[$u->display_name] = $u->ID;
}
while($q->have_posts()){ $q->the_post();
    $pid = get_the_ID();
    $date = get_post_meta($pid,'res_date',true);
    $time = get_post_meta($pid,'res_time',true);
    $stf  = get_post_meta($pid,'res_staff',true);
    if(!$date || !$time) continue;
    $sid = is_numeric($stf)? intval($stf): ($name_to_id[$stf]??0);
    if(!$sid) continue;

    $menu = get_post_meta($pid,'res_menu',true);
    $settings = get_user_meta($sid,'salon_menu_settings',true) ?: [];
    $duration = intval($settings[$menu]['duration'] ?? 30);

    $start_ts = strtotime("$date $time");
    $end_ts   = $start_ts + ($duration * 60);
    for($t=$start_ts; $t<$end_ts; $t+=30*60){
        $t_key = date('H:i',$t);
        $res_data[$date][$t_key][$sid] = true;
        $res_post_ids[$date][$t_key][$sid] = $pid;
    }
}
wp_reset_postdata();

// 出勤情報
$shifts=[];
foreach($staff_users as $u){
    foreach($week_dates as $d){
        $ym = date('Ym',strtotime($d));
        $raw = get_user_meta($u->ID,'salon_shift_'.$ym,true);
        $norm = salon_normalize_shift_meta((array)$raw,$ym);
        $day  = intval(date('d',strtotime($d)));
        if(!empty($norm[$day])) $shifts[$u->ID][$d] = $norm[$day];
    }
}

// 週切替
$prev_week  = esc_url(add_query_arg('week',$week_shift-1));
$next_week  = esc_url(add_query_arg('week',$week_shift+1));
$today_week = esc_url(remove_query_arg('week'));

$week_days = ['日','月','火','水','木','金','土'];
?>

<div class="salon-calendar">

  <div class="calendar-top">
    <h2 class="cal-title">1週間の予約カレンダー</h2>
    <!-- ✅新規予約ボタン（お客様予約ページへ） -->
    <a class="btn-new" href="<?php echo esc_url( home_url('/reservation/') ); ?>">＋ 新規予約</a>
  </div>

  <div class="week-nav">
    <a href="<?= $prev_week ?>" class="btn-week">← 前の週</a>
    <a href="<?= $today_week ?>" class="btn-week is-today">今週</a>
    <a href="<?= $next_week ?>" class="btn-week">次の週 →</a>
  </div>

  <table class="calendar-table">
    <thead>
    <tr>
      <th class="time-col"></th>
      <?php foreach($week_dates as $d): ?>
        <?php $w = $week_days[date('w',strtotime($d))]; ?>
        <th class="day-group" colspan="<?= count($staffs) ?>">
          <?= date('n/j',strtotime($d)) ?>（<?= $w ?>）
        </th>
      <?php endforeach; ?>
    </tr>
    <tr>
      <th></th>
      <?php foreach($week_dates as $d): foreach($staffs as $sname): ?>
        <th class="staff-col"><?= esc_html($sname) ?></th>
      <?php endforeach; endforeach; ?>
    </tr>
    </thead>

    <tbody>
    <?php foreach($times as $time): ?>
      <tr>
        <th class="time-col"><?= $time ?></th>
        <?php foreach($week_dates as $d): ?>
          <?php $last_staff_id = array_key_last($staffs); ?>
          <?php foreach($staffs as $sid=>$sname): ?>
            <?php
              $booked = !empty($res_data[$d][$time][$sid]);
              $pid = $res_post_ids[$d][$time][$sid] ?? 0;
              $shift = $shifts[$sid][$d] ?? null;
              $within = $shift ? salon_between($time, $shift['s'], $shift['e']) : false;

              $cls = 'off'; $mark='×'; $href='';
              if($booked){
                  $cls='booked'; $mark='×';
                  $href = get_edit_post_link($pid);
              } elseif($within){
                  $cls='available'; $mark='○';
              }
              $sep = ($sid === $last_staff_id) ? 'date-sep':'';
            ?>
            <td class="cell <?= $cls.' '.$sep ?>">
              <?php if($href): ?>
                <a href="<?= esc_url($href) ?>" class="slot-link"><?= $mark ?></a>
              <?php else: ?>
                <?= $mark ?>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

</div>

<style>
.calendar-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.btn-new {
  padding:8px 14px; font-size:14px;
  background:#0073aa; color:#fff; border-radius:4px; text-decoration:none;
}
.btn-new:hover { opacity:.85; }
</style>

<?php get_footer(); ?>

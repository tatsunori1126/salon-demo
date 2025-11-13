<?php
if (!defined('ABSPATH')) exit;

/***********************************************************
 * Ajax処理・ショートコード（予約・カレンダー・スタッフ関連）
 ***********************************************************/

/**
 * Ajax：カレンダー切替
 */
add_action('wp_ajax_salon_load_calendar', 'salon_ajax_load_calendar');
add_action('wp_ajax_nopriv_salon_load_calendar', 'salon_ajax_load_calendar');
function salon_ajax_load_calendar() {
  $menu_key = sanitize_text_field($_POST['menu_key'] ?? '');
  $staff_id = intval($_POST['staff_id'] ?? 0);
  $week     = intval($_POST['week'] ?? 0);
  ob_clean();

  if ($staff_id === 0) {
    echo salon_generate_calendar_html_all_staff($menu_key, $week);
  } else {
    echo salon_generate_calendar_html_with_shared_blocks($menu_key, $staff_id, $week);
  }

  wp_die();
}


/**
 * Ajax：選択メニューに対応するスタッフを取得
 */
add_action('wp_ajax_salon_get_staffs_by_menu_front', 'salon_get_staffs_by_menu_front');
add_action('wp_ajax_nopriv_salon_get_staffs_by_menu_front', 'salon_get_staffs_by_menu_front');
function salon_get_staffs_by_menu_front() {
  $menu_key = sanitize_text_field($_POST['menu_key'] ?? '');
  $staffs = salon_get_staff_users();
  $list = [];

  // 「指名なし」を常に先頭に追加
  $list[0] = '指名なし';

  foreach ($staffs as $s) {
    $settings = get_user_meta($s->ID, 'salon_menu_settings', true) ?: [];
    if (!empty($settings[$menu_key]['enabled'])) {
      $list[$s->ID] = $s->display_name;
    }
  }

  wp_send_json($list);
}


/**
 * Ajax：フロント予約登録
 */
add_action('wp_ajax_salon_submit_reservation', 'salon_submit_reservation');
add_action('wp_ajax_nopriv_salon_submit_reservation', 'salon_submit_reservation');
function salon_submit_reservation() {
  check_ajax_referer('salon_reservation_nonce', 'nonce');

  $name   = sanitize_text_field($_POST['name']   ?? '');
  $tel    = sanitize_text_field($_POST['tel']    ?? '');
  $email  = sanitize_email($_POST['email']       ?? '');
  $date   = sanitize_text_field($_POST['date']   ?? '');
  $time   = sanitize_text_field($_POST['time']   ?? '');
  $menu   = sanitize_text_field($_POST['menu']   ?? '');
  $staff  = intval($_POST['staff'] ?? 0); // ← 初期値は0（指名なし）

  // ▼ バリデーション
  $errors = [];
  if(!$name)  $errors[]='お名前を入力してください。';
  if(!$tel)   $errors[]='電話番号を入力してください。';
  if(!$date)  $errors[]='日付を選択してください。';
  if(!$time)  $errors[]='時間を選択してください。';
  if(!$menu)  $errors[]='メニューを選択してください。';
  if(!empty($errors)) wp_send_json_error(['msg'=>implode('<br>',$errors)]);

  // ▼ 指名なし → 自動担当割当
  // ▼ 指名なし → 全スタッフから自動割当（出勤 + メニュー対応 + 重複 + duration）
$auto_assigned = 0;

if ($staff === 0) {

    $staffs = salon_get_staff_users();
    $assigned_staff = 0;

    foreach ($staffs as $s) {

        $uid = $s->ID;

        // メニュー対応可？
        $menu_settings = get_user_meta($uid, 'salon_menu_settings', true);
        if (empty($menu_settings[$menu]['enabled'])) continue;

        // スタッフ固有の施術時間
        $duration = intval($menu_settings[$menu]['duration'] ?? 0);
        if ($duration <= 0) $duration = 60; // 念のため

        // 出勤チェック
        if (!salon_is_staff_available($uid, $date, $time)) continue;

        // ★ duration込みの重複チェック
        if (!salon_is_time_available($uid, $date, $time, $duration)) {
            continue; // このスタッフは満席
        }

        // ここまで来たら割当可能
        $assigned_staff = $uid;
        break;
    }

    if ($assigned_staff > 0) {
        $staff = $assigned_staff;
        $auto_assigned = 1;
        error_log("🎯 自動割当: {$assigned_staff}");
    } else {
        wp_send_json_error([
            'msg' => '現在この時間帯は全スタッフ満席です。他の時間をご選択ください。'
        ]);
    }
}


  // ▼ ログ確認用
  error_log("✅ 自動割当結果: staff={$staff} auto={$auto_assigned}");


  // ▼ スタッフごとの施術時間を取得（こちらが正しい）
$menu_settings = get_user_meta($staff, 'salon_menu_settings', true);
$duration = intval($menu_settings[$menu]['duration'] ?? 0);

error_log("◆ duration_from_staff={$duration}");

  // ▼ 追加：重複予約チェック（施術時間も考慮）
  if (!salon_is_time_available($staff, $date, $time, $duration)) {
      wp_send_json_error([
          'msg' => '選択した時間帯はすでに予約が入っています。他の時間をご選択ください。'
      ]);
  }


  // ▼ 予約投稿を生成
  $post_id = wp_insert_post([
    'post_type'   => 'reservation',
    'post_status' => 'publish',
    'post_title'  => sprintf('%s %s %s（%s）', $date, $time, $name, $menu),
  ]);

  if (is_wp_error($post_id) || !$post_id) {
    error_log('❌ wp_insert_post失敗');
    wp_send_json_error(['msg' => '予約の登録に失敗しました。']);
  }

  // ▼ メタ保存
update_post_meta($post_id, 'res_name', $name);
update_post_meta($post_id, 'res_tel', $tel);
update_post_meta($post_id, 'res_email', $email);
update_post_meta($post_id, 'res_date', $date);
update_post_meta($post_id, 'res_time', $time);
update_post_meta($post_id, 'res_menu', $menu);
update_post_meta($post_id, 'res_staff', intval($staff));
update_post_meta($post_id, 'res_auto_assigned', intval($auto_assigned));
update_post_meta($post_id, 'res_datetime', "$date $time:00");

// 🔥 これが重複予約防止のキー（追加する行）
update_post_meta($post_id, 'res_duration', intval($duration));

  

  // ▼ 通知メールなど
  if (function_exists('salon_send_reservation_mail')) {
    salon_send_reservation_mail($post_id);
  }

  wp_send_json_success(['msg' => 'ご予約を受け付けました。']);
}





/**
 * ショートコード：カレンダー表示
 */
add_shortcode('salon_calendar', function($atts) {
  $menu = $atts['menu'] ?? 'default';
  return salon_generate_calendar_html_wrapper($menu);
});


/***********************************************************
 * 読み取り専用カレンダー（Ajax対応）
 ***********************************************************/
add_action('wp_ajax_salon_render_readonly_calendar_ajax', 'salon_render_readonly_calendar_ajax');
add_action('wp_ajax_nopriv_salon_render_readonly_calendar_ajax', 'salon_render_readonly_calendar_ajax');

if (!function_exists('salon_render_readonly_calendar_ajax')) {
  function salon_render_readonly_calendar_ajax() {
    // weekパラメータを安全に取得
    $week = isset($_POST['week']) && $_POST['week'] !== '' ? intval($_POST['week']) : 0;

    // 範囲外チェック
    if ($week < 0 || $week > 52) {
      $week = 0;
    }

    // カレンダーHTML生成
    $html = salon_generate_readonly_calendar('default', 0, $week);

    echo $html ?: '<div style="padding:10px;color:#999;">表示できるカレンダーがありません。</div>';
    wp_die();
  }
}


/***********************************************************
 * 🧩 フロント：モーダルカレンダーAjax対応（指名・指名なし対応）
 ***********************************************************/
add_action('wp_ajax_salon_render_calendar_front', 'salon_render_calendar_front');
add_action('wp_ajax_nopriv_salon_render_calendar_front', 'salon_render_calendar_front');

if (!function_exists('salon_render_calendar_front')) {
  function salon_render_calendar_front() {
    // ===== リクエスト受取 =====
    $menu_key = sanitize_text_field($_POST['menu'] ?? '');
    $staff_id = isset($_POST['staff']) ? intval($_POST['staff']) : 0;
    $week     = intval($_POST['week'] ?? 0);
    $mode     = sanitize_text_field($_POST['mode'] ?? 'front');

    // ===== スタッフ抽出 =====
    if ($staff_id > 0) {
      // 指定スタッフ
      $u = get_userdata($staff_id);
      $staffs = $u ? [$u] : [];
    } else {
      // 指名なし → 全スタッフ
      $staffs = salon_get_staff_users();
    }

    // ===== スタッフ情報がない場合 =====
    if (empty($staffs)) {
      echo '<div style="padding:10px;color:#999;">スタッフ情報が取得できませんでした。</div>';
      wp_die();
    }

    // ===== カレンダーHTML生成 =====
    $html = salon_generate_calendar_html($menu_key, $staff_id, $week, $mode);
    echo $html ?: '<div style="padding:10px;color:#999;">カレンダーの生成に失敗しました。</div>';
    wp_die();
  }
}


/**
 * --------------------------------------------------
 * 🧩 公開用カレンダー描画（readonly表示）
 * --------------------------------------------------
 * - すべてのユーザー（ログイン不要）対応
 * - メニュー・スタッフ・週指定に応じてカレンダー生成
 * --------------------------------------------------
 */
add_action('wp_ajax_salon_render_calendar_public_readonly', 'salon_render_calendar_public_readonly');
add_action('wp_ajax_nopriv_salon_render_calendar_public_readonly', 'salon_render_calendar_public_readonly');

function salon_render_calendar_public_readonly() {
  $menu_key = sanitize_text_field($_POST['menu_key'] ?? '');
  $staff_id = intval($_POST['staff_id'] ?? 0);
  $week     = intval($_POST['week'] ?? 0);

  // ✅ カレンダーHTML生成
  if (function_exists('salon_generate_calendar_html_all_staff')) {
    // 指名なし → 全スタッフカレンダー
    $html = salon_generate_calendar_html_all_staff($menu_key, $week);
  } elseif (function_exists('salon_generate_calendar_html')) {
    // 指名あり → 通常カレンダー
    $html = salon_generate_calendar_html($menu_key, $staff_id, $week, 'front');
  } else {
    $html = '<p>カレンダー生成関数が見つかりません。</p>';
  }

  echo $html;
  wp_die(); // ← WordPress Ajax処理の終了
}


/**
 * --------------------------------------------------
 * 🧩 GET版：カレンダー取得（シンプル表示用）
 * --------------------------------------------------
 * - URLパラメータで取得できるようにGET対応
 * - 例：?action=salon_get_calendar_html&menu_key=cut&staff_id=3
 * --------------------------------------------------
 */
add_action('wp_ajax_salon_get_calendar_html', 'salon_get_calendar_html');
add_action('wp_ajax_nopriv_salon_get_calendar_html', 'salon_get_calendar_html');

function salon_get_calendar_html() {
  $menu_key = sanitize_text_field($_GET['menu_key'] ?? '');
  $staff_id = intval($_GET['staff_id'] ?? 0);

  if (function_exists('salon_generate_calendar_html')) {
    echo salon_generate_calendar_html($menu_key, $staff_id);
  } else {
    echo '<p>カレンダー生成関数が見つかりません。</p>';
  }

  wp_die();
}

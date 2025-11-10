<?php
/**
 * =========================================================
 * IWAI制作所：予約管理システム functions.php（最終安定版）
 * =========================================================
 * - 予約投稿タイプ（reservation）
 * - 担当スタッフ（res_staff / _old_res_staff）
 * - カレンダー更新通知
 * - 管理画面UI調整・カラム追加
 * =========================================================
 */

require_once get_template_directory() . '/inc/functions-core.php';
require_once get_template_directory() . '/inc/setup.php';
require_once get_template_directory() . '/inc/store-settings.php';
require_once get_template_directory() . '/inc/staff-settings.php';
require_once get_template_directory() . '/inc/cpt-reservation.php';
require_once get_template_directory() . '/inc/shifts.php';
require_once get_template_directory() . '/inc/reservation-metabox.php';
require_once get_template_directory() . '/inc/reservation-save.php';
require_once get_template_directory() . '/inc/ajax-reservation.php';
require_once get_template_directory() . '/inc/mail.php';
require_once get_template_directory() . '/inc/calendar.php';



/***********************************************************
 * 🗂️ 管理画面リスト：予約一覧のカラム調整
 ***********************************************************/
add_filter('manage_edit-reservation_columns', function($cols) {
  return [
    'cb'          => '<input type="checkbox">',
    'res_datetime'=> '日時',
    'res_name'    => 'お名前',
    'res_tel'     => '電話',
    'res_email'   => 'メール',
    'res_menu'    => 'メニュー',
    'res_staff'   => '担当',
    'res_actions' => '操作',
    'date'        => '登録日',
  ];
});

add_action('manage_reservation_posts_custom_column', function($col, $id) {
  $v = get_post_meta($id, $col, true);

  switch ($col) {
    case 'res_tel':
      if ($v) echo '<a href="tel:' . esc_attr($v) . '">' . esc_html($v) . '</a>';
      break;

    case 'res_email':
      if ($v) echo '<a href="mailto:' . esc_attr($v) . '">' . esc_html($v) . '</a>';
      break;

    case 'res_staff':
      $v = intval($v);
      $u = $v ? get_userdata($v) : null;
      $auto = intval(get_post_meta($id, 'res_auto_assigned', true));
      if ($u) {
        echo esc_html($u->display_name);
        if ($auto) echo '（指名なし）';
      } else {
        echo '指名なし';
      }
      break;

    case 'res_actions':
      $edit_url  = get_edit_post_link($id);
      $trash_url = get_delete_post_link($id);
      echo '<div style="display:flex;gap:6px;">';
      echo '<a href="' . esc_url($edit_url) . '" class="button button-small">編集</a>';
      echo '<a href="' . esc_url($trash_url) . '" class="button button-small" style="color:#a00;">削除</a>';
      echo '</div>';
      break;

    default:
      echo esc_html($v ?: '');
  }
}, 10, 2);



/***********************************************************
 * 💾 予約保存時：「_old_res_staff」を正しく保持（最新版）
 ***********************************************************/
add_action('save_post_reservation', function($post_id, $post, $update) {

  if (defined('SALON_SAVE_RUNNING')) return;
  define('SALON_SAVE_RUNNING', true);

  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;
  if (!isset($_POST['res_staff'])) return;

  $new_staff = intval($_POST['res_staff']);
  $old_staff = get_post_meta($post_id, '_old_res_staff', true);

  if ($old_staff === '' || $old_staff === null) {
    $staff_label = '';
    if (!empty($_POST['res_staff_name'])) {
      $staff_label = sanitize_text_field($_POST['res_staff_name']);
    } else {
      $user = get_userdata($new_staff);
      $staff_label = $user ? $user->display_name : '';
    }

    $is_no_nomination = (
      stripos($staff_label, '指名なし') !== false ||
      stripos($staff_label, 'no staff') !== false ||
      $new_staff === 0
    );

    if ($is_no_nomination) {
      update_post_meta($post_id, '_old_res_staff', 0);
      error_log("=== 初回保存: 指名なし post_id={$post_id} ===");
    } else {
      update_post_meta($post_id, '_old_res_staff', $new_staff);
      error_log("=== 初回保存: 指名あり post_id={$post_id} staff_id={$new_staff} ===");
    }
  } else {
    error_log("=== 更新維持: _old_res_staff={$old_staff} post_id={$post_id} ===");
  }

}, 20, 3);



/***********************************************************
 * 🎨 管理画面UI調整（タイトル・メタボックス非表示）
 ***********************************************************/
add_action('admin_head', function() {
  global $post_type;
  if ($post_type === 'reservation') {
    echo '<style>
      #titlediv, #postdivrich, #wp-content-editor-container, #editor {
        display: none !important;
      }
    </style>';
  }
});

add_action('add_meta_boxes', function() {
  remove_meta_box('reservation_staff_box', 'reservation', 'side');
}, 9999);



/***********************************************************
 * 🔁 Ajax：担当スタッフ変更後にカレンダー更新トリガー
 ***********************************************************/
add_action('save_post_reservation', function($post_id) {

  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;

  $date  = get_post_meta($post_id, 'res_date', true);
  $time  = get_post_meta($post_id, 'res_time', true);
  $staff = intval(get_post_meta($post_id, 'res_staff', true));

  $data = [
    'date'    => $date ?: '',
    'time'    => $time ?: '',
    'staff'   => $staff,
    'updated' => current_time('mysql'),
  ];

  update_option('salon_last_update', $data);
  error_log("=== salon_last_update 更新: post_id={$post_id}, staff={$staff}, date={$date}, time={$time} ===");

}, 30);



/***********************************************************
 * 🔄 Ajax：カレンダー更新情報取得（JS側から呼び出し）
 ***********************************************************/
add_action('wp_ajax_salon_get_last_update', function() {
  $data = get_option('salon_last_update', []);
  wp_send_json_success($data);
});
add_action('wp_ajax_nopriv_salon_get_last_update', function() {
  $data = get_option('salon_last_update', []);
  wp_send_json_success($data);
});



/***********************************************************
 * 💰 予約一覧に「指名料」「合計金額」カラム追加
 ***********************************************************/
add_filter('manage_edit-reservation_columns', function($columns) {
  $new = [];
  foreach ($columns as $key => $label) {
    $new[$key] = $label;
    if ($key === 'res_staff') {
      $new['nomination_fee'] = '指名料';
      $new['total_price']    = '合計金額';
    }
  }
  return $new;
});

add_action('manage_reservation_posts_custom_column', function($column, $post_id) {

  if (!in_array($column, ['nomination_fee', 'total_price'], true)) return;

  $store       = salon_get_store_settings();
  $menus       = $store['menus'] ?? [];
  $default_fee = intval($store['nomination_fee'] ?? 0);

  $menu_name   = get_post_meta($post_id, 'res_menu', true);
  $staff_id    = intval(get_post_meta($post_id, 'res_staff', true));
  $auto_assign = intval(get_post_meta($post_id, 'res_auto_assigned', true));

  $menu_price = 0;
  foreach ($menus as $m) {
    if (!empty($m['name']) && $m['name'] === $menu_name) {
      $menu_price = intval($m['price']);
      break;
    }
  }

  $nomination_fee = ($staff_id > 0 && $auto_assign === 0) ? $default_fee : 0;
  $total = $menu_price + $nomination_fee;

  switch ($column) {
    case 'nomination_fee':
      echo ($auto_assign === 1) ? '-' :
        ($nomination_fee > 0 ? esc_html(number_format($nomination_fee)) . '円' : '');
      break;

    case 'total_price':
      echo ($total > 0)
        ? esc_html(number_format($total)) . '円'
        : '-';
      break;
  }

}, 10, 2);



/***********************************************************
 * 🔁 res_staff 更新・追加時に _old_res_staff 自動同期
 ***********************************************************/
add_action('updated_post_meta', function($meta_id, $post_id, $meta_key, $meta_value) {
  if (get_post_type($post_id) !== 'reservation' || $meta_key !== 'res_staff') return;

  $staff_id = intval($meta_value);
  $user = get_userdata($staff_id);
  $staff_label = $user ? $user->display_name : '';

  $is_no_nomination = ($staff_id === 0 || stripos($staff_label, '指名なし') !== false);

  update_post_meta($post_id, '_old_res_staff', $is_no_nomination ? 0 : $staff_id);
  error_log("=== updated_post_meta: " . ($is_no_nomination ? "指名なし" : "指名あり") . " post_id={$post_id} ===");

}, 10, 4);

add_action('added_post_meta', function($meta_id, $post_id, $meta_key, $meta_value) {
  if (get_post_type($post_id) !== 'reservation' || $meta_key !== 'res_staff') return;

  $staff_id = intval($meta_value);
  $user = get_userdata($staff_id);
  $staff_label = $user ? $user->display_name : '';

  $is_no_nomination = ($staff_id === 0 || stripos($staff_label, '指名なし') !== false);

  update_post_meta($post_id, '_old_res_staff', $is_no_nomination ? 0 : $staff_id);
  error_log("=== added_post_meta: " . ($is_no_nomination ? "指名なし" : "指名あり") . " post_id={$post_id} ===");

}, 10, 4);



/***********************************************************
 * ✅ 最終補正：投稿保存完了後に _old_res_staff を確実に登録
 ***********************************************************/
add_action('wp_after_insert_post', function($post_id, $post, $update) {

  if ($post->post_type !== 'reservation') return;

  $staff_id = isset($_POST['res_staff'])
    ? intval($_POST['res_staff'])
    : intval(get_post_meta($post_id, 'res_staff', true));

  if ($staff_id === 0) {
    update_post_meta($post_id, '_old_res_staff', 0);
    return;
  }

  $user = get_userdata($staff_id);
  $staff_label = $user ? $user->display_name : '';

  $is_no_nomination = ($staff_id === 0 || stripos($staff_label, '指名なし') !== false);

  update_post_meta($post_id, '_old_res_staff', $is_no_nomination ? 0 : $staff_id);

}, 10, 3);



/***********************************************************
 * ✅ 指名なし補正処理（res_staffが欠けている場合の保険）
 ***********************************************************/
add_action('save_post_reservation', function($post_id, $post, $update) {

  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if ($post->post_status === 'auto-draft') return;

  $staff = get_post_meta($post_id, 'res_staff', true);
  if ($staff === '' || $staff === null || intval($staff) < 1) {
    update_post_meta($post_id, 'res_staff', 0);
  }

}, 20, 3);

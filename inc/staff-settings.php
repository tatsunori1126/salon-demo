<?php
if (!defined('ABSPATH')) exit;

/***********************************************************
 * スタッフ設定（施術メニュー対応・施術時間）
 * - 管理画面プロフィールにメニュー設定項目を追加
 * - 各スタッフが対応できるメニュー・時間を登録可能
 ***********************************************************/

/**
 * スタッフプロフィール画面に「施術メニュー設定」を追加
 */
function salon_staff_menu_settings_fields($user){
  // スタッフまたは管理者のみ表示
  if (!in_array('salon_staff', (array)$user->roles, true) && !current_user_can('manage_options')) return;

  $store = salon_get_store_settings();
  $menus = $store['menus'] ?? [];
  $saved = get_user_meta($user->ID, 'salon_menu_settings', true) ?: [];

  echo '<h2>施術メニュー設定</h2>';

  if (empty($menus)) {
    echo '<p style="color:#666;">※ 先に「店舗設定」でメニューを登録してください。</p>';
    return;
  }

  echo '<table class="form-table">';
  echo '<tr><th>メニュー名</th><th>対応可・施術時間</th></tr>';

  foreach ($menus as $m) {
    $key = $m['name'];
    $price = intval($m['price']);
    $enabled = $saved[$key]['enabled'] ?? 0;
    $duration = $saved[$key]['duration'] ?? 60;

    echo '<tr>';
    echo '<th><label>' . esc_html($key) . '</label><br><small>¥' . number_format($price) . '</small></th>';
    echo '<td>';
    echo '<label><input type="checkbox" name="salon_menu_enabled[' . esc_attr($key) . ']" value="1" ' . checked($enabled, 1, false) . '> 対応可</label> ';
    echo '<select name="salon_menu_duration[' . esc_attr($key) . ']">';
    for ($m = 30; $m <= 180; $m += 15) {
      echo '<option value="' . $m . '" ' . selected($duration, $m, false) . '>' . $m . '分</option>';
    }
    echo '</select>';
    echo '</td></tr>';
  }

  echo '</table>';
}
add_action('show_user_profile', 'salon_staff_menu_settings_fields');
add_action('edit_user_profile', 'salon_staff_menu_settings_fields');


/**
 * 保存処理
 */
function salon_save_staff_menu_settings($user_id){
  if (!current_user_can('edit_user', $user_id)) return;

  $enabled  = $_POST['salon_menu_enabled'] ?? [];
  $duration = $_POST['salon_menu_duration'] ?? [];

  $store = salon_get_store_settings();
  $menus = $store['menus'] ?? [];

  $save = [];
  foreach ($menus as $m) {
    $key = $m['name'];
    $save[$key] = [
      'enabled'  => isset($enabled[$key]) ? 1 : 0,
      'duration' => isset($duration[$key]) ? intval($duration[$key]) : 60,
    ];
  }

  update_user_meta($user_id, 'salon_menu_settings', $save);
}
add_action('personal_options_update', 'salon_save_staff_menu_settings');
add_action('edit_user_profile_update', 'salon_save_staff_menu_settings');

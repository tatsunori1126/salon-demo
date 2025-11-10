<?php
if (!defined('ABSPATH')) exit;

/***********************************************************
 * 予約メタボックス（管理画面編集フォーム）
 ***********************************************************/

// メタボックス追加
add_action('add_meta_boxes', function() {
  add_meta_box('reservation_fields', '予約情報', 'salon_reservation_mb', 'reservation', 'normal', 'high');
});

// メタボックスHTML
function salon_reservation_mb($post) {
  wp_nonce_field('salon_reservation_save', 'salon_reservation_nonce');

  $meta = ['name', 'tel', 'email', 'date', 'time', 'menu', 'staff'];
  foreach ($meta as $m) {
    $$m = get_post_meta($post->ID, 'res_' . $m, true);
  }

  $store  = salon_get_store_settings();
  $menus  = $store['menus'] ?? [];
  $staffs = salon_get_staff_users();

  // ✅ 出勤＆メニュー対応可スタッフを絞り込み
  $filtered_staffs = [];
  if ($date && $time && $menu) {
    foreach ($staffs as $s) {
      $uid = $s->ID;
      $menu_settings = get_user_meta($uid, 'salon_menu_settings', true) ?: [];
      $enabled   = !empty($menu_settings[$menu]['enabled']);
      $available = salon_is_staff_available($uid, $date, $time);

      if ($enabled && $available) {
        $filtered_staffs[] = $s;
      }
    }
  } else {
    $filtered_staffs = $staffs; // 未選択時は全員表示
  }
  ?>

  <table class="form-table">
    <tr><th>お名前*</th><td><input name="res_name" type="text" value="<?= esc_attr($name) ?>" required></td></tr>
    <tr><th>電話*</th><td><input name="res_tel" type="text" value="<?= esc_attr($tel) ?>" required></td></tr>
    <tr><th>メール</th><td><input name="res_email" type="email" value="<?= esc_attr($email) ?>"></td></tr>
    <tr><th>日付*</th><td><input name="res_date" type="date" value="<?= esc_attr($date) ?>" required></td></tr>
    <tr><th>時間*</th><td><input name="res_time" type="time" value="<?= esc_attr($time) ?>" required></td></tr>

    <tr><th>メニュー*</th>
      <td><select name="res_menu" required>
        <option value="">— 選択 —</option>
        <?php foreach ($menus as $m): ?>
          <option value="<?= esc_attr($m['name']) ?>" <?= selected($menu, $m['name'], false) ?>>
            <?= esc_html($m['name']) ?>
          </option>
        <?php endforeach; ?>
      </select></td>
    </tr>

    <tr><th>担当*</th>
      <td>
        <select name="res_staff" required>
          <option value="">— 選択 —</option>
          <option value="0" <?= selected($staff, '0', false) ?>>指名なし（自動割当）</option>
          <?php if (!empty($filtered_staffs)): ?>
            <?php foreach ($filtered_staffs as $s): ?>
              <option value="<?= $s->ID ?>" <?= selected($staff, $s->ID, false) ?>>
                <?= esc_html($s->display_name) ?>
              </option>
            <?php endforeach; ?>
          <?php else: ?>
            <option value="">（出勤中スタッフなし）</option>
          <?php endif; ?>
        </select>
      </td>
    </tr>
  </table>
  <?php
}

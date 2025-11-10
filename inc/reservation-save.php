<?php
if (!defined('ABSPATH')) exit;

/***********************************************************
 * 予約データ保存・担当変更検知
 ***********************************************************/

// メタ保存処理
add_action('save_post_reservation', function($post_id) {
  if (defined('SALON_SAVE_RUNNING')) return;
  define('SALON_SAVE_RUNNING', true);

  if (!isset($_POST['salon_reservation_nonce'])
      || !wp_verify_nonce($_POST['salon_reservation_nonce'], 'salon_reservation_save')) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

  $fields = ['name', 'tel', 'email', 'date', 'time', 'menu'];
  foreach ($fields as $f) {
    update_post_meta($post_id, 'res_' . $f, sanitize_text_field($_POST['res_' . $f] ?? ''));
  }

  $staff = intval($_POST['res_staff'] ?? 0);
  update_post_meta($post_id, 'res_staff', $staff);
  update_post_meta($post_id, 'res_datetime', ($_POST['res_date'] ?? '') . ' ' . ($_POST['res_time'] ?? '') . ':00');

  // タイトル更新（ループ防止）
  remove_action('save_post_reservation', __FUNCTION__);
  wp_update_post([
    'ID'         => $post_id,
    'post_title' => sprintf(
    '%s %s / %s（%s）',
    esc_html($_POST['res_date']),
    esc_html($_POST['res_time']),
    esc_html($_POST['res_name']),
    esc_html($_POST['res_menu'])
  ),
  ]);
  add_action('save_post_reservation', __FUNCTION__);
}, 10, 1);

// 担当変更の安全保存（メモリリーク防止）
add_action('save_post_reservation', function($post_id) {
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;
  if (!isset($_POST['res_staff'])) return;

  $old_staff = intval(get_post_meta($post_id, '_old_res_staff', true));
  $new_staff = intval($_POST['res_staff']);

  if ($new_staff !== $old_staff) {
    error_log("=== 担当変更 === post_id={$post_id} old={$old_staff} new={$new_staff}");
    update_post_meta($post_id, 'res_staff', $new_staff);
    update_post_meta($post_id, '_old_res_staff', $new_staff);
  }
}, 20);

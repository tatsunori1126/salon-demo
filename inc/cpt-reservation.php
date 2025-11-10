<?php
if (!defined('ABSPATH')) exit;

/***********************************************************
 * 予約CPTとスタッフロール登録
 ***********************************************************/

add_action('init', function() {
  // 予約投稿タイプ
  register_post_type('reservation', [
    'label' => '予約',
    'public' => false,
    'show_ui' => true,
    'supports' => [],
    'menu_icon' => 'dashicons-calendar-alt',
    'show_in_rest' => false,
  ]);

  // スタッフロール
  if (!get_role('salon_staff')) {
    add_role('salon_staff', 'サロンスタッフ', ['read' => true]);
  }
});

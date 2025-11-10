<?php
if (!defined('ABSPATH')) exit;

/***********************************************************
 * テーマ基本設定・共通アセット読み込み
 * ---------------------------------------------------------
 * - テーマサポート、SEOタイトル、不要タグ削除
 * - 絵文字無効化、共通CSS/JS読み込み
 * - 管理画面専用CSS
 ***********************************************************/

/** テーマサポート */
add_action('after_setup_theme', function() {
  add_theme_support('html5', ['comment-list', 'comment-form', 'search-form', 'gallery', 'caption']);
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  add_theme_support('automatic-feed-links');
  add_theme_support('custom-logo');
  add_theme_support('wp-block-styles');
  add_theme_support('responsive-embeds');
  add_theme_support('align-wide');
});

/** SEO向けタイトル最適化 */
function seo_friendly_title($title){
  if (is_front_page()) {
    $title = get_bloginfo('name', 'display');
  } elseif (is_singular()) {
    $title = single_post_title('', false) . ' | ' . get_bloginfo('name', 'display');
  }
  return $title;
}
add_filter('pre_get_document_title', 'seo_friendly_title');

/** 不要なwp_head出力削除 */
remove_action('wp_head','wp_generator');
remove_action('wp_head','wlwmanifest_link');
remove_action('wp_head','rsd_link');
remove_action('wp_head','adjacent_posts_rel_link_wp_head',10,0);
remove_action('wp_head','feed_links_extra',3);
remove_action('wp_head','print_emoji_detection_script',7);
remove_action('wp_print_styles','print_emoji_styles');

/** 絵文字完全無効化 */
add_action('init', function(){
  remove_action('wp_head','print_emoji_detection_script',7);
  remove_action('admin_print_scripts','print_emoji_detection_script');
  remove_action('wp_print_styles','print_emoji_styles');
  remove_action('admin_print_styles','print_emoji_styles');
  remove_filter('the_content_feed','wp_staticize_emoji');
  remove_filter('comment_text_rss','wp_staticize_emoji');
  remove_filter('wp_mail','wp_staticize_emoji_for_email');
  add_filter('emoji_svg_url','__return_false');
});

/** CSS/JS共通読み込み */
function salon_enqueue_assets(){
  // CSS
  wp_enqueue_style('theme-style', get_template_directory_uri().'/css/style.min.css', [], filemtime(get_template_directory().'/css/style.min.css'));
  wp_enqueue_style('swiper', 'https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css', [], null);
  wp_enqueue_style('fontawesome', 'https://use.fontawesome.com/releases/v6.6.0/css/all.css', [], null);
  wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100..900&display=swap', [], null);

  // JS
  wp_enqueue_script('jquery');
  wp_enqueue_script('swiper', 'https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js', [], null, true);
  wp_enqueue_script('gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js', [], null, true);
  wp_enqueue_script('gsap-scrolltrigger', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js', ['gsap'], null, true);

  // JS読込
  wp_enqueue_script('salon-script', get_template_directory_uri().'/js/script.min.js', ['jquery'], filemtime(get_template_directory().'/js/script.min.js'), true);

  // AjaxURL共有
  wp_localize_script('salon-script', 'salon_ajax', [
    'url'   => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('salon_reservation_nonce')
  ]);
}
add_action('wp_enqueue_scripts', 'salon_enqueue_assets');

/** 管理画面：出勤管理専用CSS */
add_action('admin_enqueue_scripts', function($hook){
  if ($hook === 'toplevel_page_salon-shifts') {
    wp_enqueue_style(
      'salon-admin-style',
      get_template_directory_uri().'/css/admin.min.css',
      [],
      filemtime(get_template_directory().'/css/admin.min.css')
    );
  }
});

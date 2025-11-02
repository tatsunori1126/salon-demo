<?php
/***********************************************************
* テーマサポートの追加
***********************************************************/
add_theme_support( 'html5', array( 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption' ) );
add_theme_support( 'title-tag' );
add_theme_support( 'post-thumbnails' );
add_theme_support( 'automatic-feed-links' );
add_theme_support( 'custom-logo' );
add_theme_support( 'wp-block-styles' );
add_theme_support( 'responsive-embeds' );
add_theme_support( 'align-wide' );

/***********************************************************
* SEO対策のためのタイトルタグのカスタマイズ
***********************************************************/
function seo_friendly_title( $title ) {
  // トップページの場合
  if ( is_front_page() ) {
      $title = get_bloginfo( 'name', 'display' ); // トップページではサイトのタイトルのみを表示
  } 
  // トップページ以外の場合
  elseif ( is_singular() ) {
      $title = single_post_title( '', false ) . ' | ' . get_bloginfo( 'name', 'display' ); // ページタイトル | サイトタイトル
  }
  return $title;
}
add_filter( 'pre_get_document_title', 'seo_friendly_title' );


/***********************************************************
* 不要なwp_headアクションを削除（パフォーマンス向上）
***********************************************************/
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);
remove_action('wp_head', 'feed_links_extra', 3);
remove_action('wp_head', 'print_emoji_detection_script', 7 );
remove_action('wp_print_styles', 'print_emoji_styles');

/***********************************************************
* 絵文字機能を無効化（パフォーマンス向上）
***********************************************************/
function disable_emoji_feature() {
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
    add_filter( 'emoji_svg_url', '__return_false' ); // さらに絵文字を無効化
}
add_action( 'init', 'disable_emoji_feature' );

/***********************************************************
* CSSとJavaScriptの読み込み（フロント側）
***********************************************************/
function enqueue_theme_assets() {

  // メインテーマCSS（_top.scss含む）
  wp_enqueue_style(
      'theme-style',
      get_template_directory_uri() . '/css/style.min.css',
      array(),
      filemtime(get_template_directory() . '/css/style.min.css')
  );

  // Swiper CSS
  wp_enqueue_style('swiper', 'https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css', array(), null);

  // Font Awesome CSS
  wp_enqueue_style('fontawesome', 'https://use.fontawesome.com/releases/v6.6.0/css/all.css', array(), null);

  // Google Fonts
  wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100..900&display=swap', array(), null);

  // jQuery
  wp_enqueue_script('jquery');

  // Swiper JS
  wp_enqueue_script('swiper', 'https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js', array(), null, true);

  // Custom JS
  wp_enqueue_script(
      'theme-script',
      get_template_directory_uri() . '/js/script.min.js',
      array('jquery'),
      filemtime(get_template_directory() . '/js/script.min.js'),
      true
  );
}
add_action('wp_enqueue_scripts', 'enqueue_theme_assets');


/***********************************************************
* GSAPとScrollTriggerの読み込み（フロント側）
***********************************************************/
function enqueue_gsap_with_scrolltrigger() {
  wp_enqueue_script('gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js', [], null, true);
  wp_enqueue_script('gsap-scrolltrigger', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js', ['gsap'], null, true);
}
add_action('wp_enqueue_scripts', 'enqueue_gsap_with_scrolltrigger');


/***********************************************************
* 管理画面：出勤管理ページのみ admin.min.css を読み込み
***********************************************************/
add_action('admin_enqueue_scripts', function($hook){
  if ($hook === 'toplevel_page_salon-shifts') { // 出勤管理ページ専用
      wp_enqueue_style(
          'salon-admin-style',
          get_template_directory_uri() . '/css/admin.min.css',
          [],
          filemtime(get_template_directory() . '/css/admin.min.css')
      );
  }
});



/***********************************************************
* カスタム投稿によって表示件数を変える
***********************************************************/
// function change_posts_per_page($query) {
//   if ( is_admin() || ! $query->is_main_query() )
//       return;

//   // カスタム投稿タイプ "news" のアーカイブページの場合
//   if ( $query->is_post_type_archive('news') ) {
//       $query->set( 'posts_per_page', 12 );
//       return;
//   }

//   // カスタム投稿タイプ "achievements" のアーカイブページの場合
//   if ( $query->is_post_type_archive('achievements') ) {
//       $query->set( 'posts_per_page', 12 );
//       return;
//   }

//   // タクソノミー "news_category" のアーカイブページの場合
//   if ( $query->is_tax('news_category') ) {
//       $query->set( 'posts_per_page', 12 );
//       return;
//   }
// }
// add_action( 'pre_get_posts', 'change_posts_per_page' );


/***********************************************************
* Options Page
***********************************************************/
// if( function_exists('acf_add_options_page') ) {
//   acf_add_options_page(array(
//     'page_title' 	=> 'RECRUIT - 数字でみる',
//     'menu_title'	=> 'RECRUIT - 数字でみる',
//     'menu_slug' 	=> 'top-data',
//     'capability'	=> 'edit_posts',
//     'redirect'		=> false
//   ));
// }










/**
 * サロン予約・出勤管理（完成・統一版）
 * - 予約CPT
 * - 予約メタ（担当スタッフ = user_id 保存 / 旧display_nameも後方互換）
 * - 出勤管理（管理者は任意スタッフを保存可、保存後は同対象でリロード）
 * - 管理画面の予約一覧カラム＆並び替え＆絞り込み
 */

/* =========================================================
 *  ロール & 予約CPT
 * =======================================================*/
add_action('init', function () {

  // 予約CPT
  register_post_type('reservation', [
      'label'         => '予約',
      'public'        => false,
      'show_ui'       => true,
      'supports'      => [],
      'menu_icon'     => 'dashicons-calendar-alt',
      'show_in_rest'  => false,
  ]);

  // サロンスタッフロール（なければ）
  if (!get_role('salon_staff')) {
      add_role('salon_staff', 'サロンスタッフ', ['read' => true]);
  }
});

/** スタッフ一覧（ID/表示名） */
function salon_get_staff_users() {
  return get_users([
      'role'     => 'salon_staff',
      'orderby'  => 'display_name',
      'order'    => 'ASC',
      'fields'   => ['ID','display_name','user_login'],
  ]);
}

/** 表示名 => ID の逆引き（旧データ後方互換用） */
function salon_staff_name_to_id_map() {
  $map = [];
  foreach (salon_get_staff_users() as $u) {
      $map[$u->display_name] = $u->ID;
  }
  return $map;
}

/* =========================================================
*  予約編集画面
* =======================================================*/
function rsrv_get_menu_options() {
  return [
      'カット' => 'カット',
      'カット＋カラー' => 'カット＋カラー',
      'カラー' => 'カラー',
      'パーマ' => 'パーマ',
      'トリートメント' => 'トリートメント',
  ];
}

add_action('add_meta_boxes', function () {
  add_meta_box('reservation_fields', '予約情報', 'rsrv_render_mb', 'reservation', 'normal', 'high');
});

function rsrv_render_mb($post) {
  wp_nonce_field('rsrv_save', 'rsrv_nonce');

  $name   = get_post_meta($post->ID, 'res_name', true);
  $tel    = get_post_meta($post->ID, 'res_tel', true);
  $date   = get_post_meta($post->ID, 'res_date', true);
  $time   = get_post_meta($post->ID, 'res_time', true);
  $menu   = get_post_meta($post->ID, 'res_menu', true);
  $staff  = get_post_meta($post->ID, 'res_staff', true); // 基本は user_id。旧データは display_name の場合あり。

  $menus  = rsrv_get_menu_options();
  $staffs = salon_get_staff_users();
  ?>
  <table class="form-table">
      <tr>
          <th><label>名前 *</label></th>
          <td><input type="text" name="res_name" class="regular-text" required value="<?php echo esc_attr($name); ?>"></td>
      </tr>
      <tr>
          <th><label>電話 *</label></th>
          <td><input type="text" name="res_tel" class="regular-text" required value="<?php echo esc_attr($tel); ?>"></td>
      </tr>
      <tr>
          <th><label>日時 *</label></th>
          <td>
              <input type="date" name="res_date" value="<?php echo esc_attr($date); ?>" required>
              <input type="time" name="res_time" value="<?php echo esc_attr($time); ?>" min="09:00" max="20:00" step="1800" required>
              <p class="description">※ 09:00〜20:00 の30分刻み</p>
          </td>
      </tr>
      <tr>
          <th><label>メニュー *</label></th>
          <td>
              <select name="res_menu" required>
                  <option value="">— 選択 —</option>
                  <?php foreach ($menus as $k => $v): ?>
                      <option value="<?php echo esc_attr($k); ?>" <?php selected($menu, $k); ?>><?php echo esc_html($v); ?></option>
                  <?php endforeach; ?>
              </select>
          </td>
      </tr>
      <tr>
          <th><label>担当 *</label></th>
          <td>
              <select name="res_staff" required>
                  <option value="">— 選択 —</option>
                  <?php
                  // 旧保存(display_name)でも選択状態が付くように判定
                  $name_to_id = salon_staff_name_to_id_map();
                  $staff_selected_id = is_numeric($staff) ? intval($staff) : ($name_to_id[$staff] ?? 0);
                  foreach ($staffs as $s):
                  ?>
                      <option value="<?php echo esc_attr($s->ID); ?>" <?php selected($staff_selected_id, $s->ID); ?>>
                          <?php echo esc_html($s->display_name); ?>
                      </option>
                  <?php endforeach; ?>
              </select>
          </td>
      </tr>
  </table>
  <?php
}

/** 予約保存処理 */
add_action('save_post_reservation', function ($post_id, $post, $update) {
  if (!isset($_POST['rsrv_nonce']) || !wp_verify_nonce($_POST['rsrv_nonce'], 'rsrv_save')) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;

  static $processing = false;
  if ($processing) return;
  $processing = true;

  $fields = ['res_name','res_tel','res_date','res_time','res_menu','res_staff'];
  foreach ($fields as $k) {
      $val = sanitize_text_field($_POST[$k] ?? '');
      // res_staff は user_id を保存（数値化）。display_name が来た旧フォームも念のため対応。
      if ($k === 'res_staff') {
          if (!is_numeric($val)) {
              $map = salon_staff_name_to_id_map();
              $val = isset($map[$val]) ? $map[$val] : 0;
          }
          $val = intval($val);
      }
      update_post_meta($post_id, $k, $val);
  }

  $date = sanitize_text_field($_POST['res_date'] ?? '');
  $time = sanitize_text_field($_POST['res_time'] ?? '');
  update_post_meta($post_id, 'res_datetime', ($date && $time) ? "$date $time:00" : '');

  // タイトル自動
  remove_action('save_post_reservation', __FUNCTION__, 10);
  wp_update_post([
      'ID'         => $post_id,
      'post_title' => sprintf('%s %s / %s（%s）', $date ?: '', $time ?: '', sanitize_text_field($_POST['res_name'] ?? ''), sanitize_text_field($_POST['res_menu'] ?? '')),
  ]);
  add_action('save_post_reservation', __FUNCTION__, 10, 3);

  $processing = false;
}, 10, 3);

/** 予約投稿でタイトル・本文 UI を隠す */
add_action('admin_head', function () {
  $screen = get_current_screen();
  if ($screen && $screen->post_type === 'reservation') {
      echo '<style>#titlediv,#post-body-content{display:none!important}</style>';
  }
});

/* =========================================================
*  出勤管理
* =======================================================*/
function salon_shift_meta_key($ym){ return "salon_shift_".$ym; }

add_action('admin_menu', function () {
  add_menu_page(
      '出勤管理',
      '出勤管理',
      'read',                 // スタッフも閲覧可能
      'salon-shifts',
      'salon_render_shifts_page',
      'dashicons-groups',
      26
  );
});

function salon_render_shifts_page() {
  // ここでは絶対に HTML 出力より前に保存処理を置く
  $current  = wp_get_current_user();
// ✅ロールで管理者判定（これが今回の正解）
$is_admin = in_array('administrator', (array)$current->roles, true);

// ✅対象ユーザー（管理者は任意、スタッフは自分）
$uid = $is_admin
    ? intval($_GET['user'] ?? $_POST['user'] ?? $current->ID)
    : $current->ID;

  // 対象ユーザー（管理者は任意、スタッフは自分）
  $uid = $is_admin
      ? intval($_GET['user'] ?? $_POST['user'] ?? $current->ID)
      : $current->ID;

  // 対象年月
  $ym = preg_replace('/[^0-9]/', '', ($_GET['ym'] ?? $_POST['ym'] ?? date('Ym')));

  // ───────── 保存処理（必ず出力前）─────────
  $just_saved = false;
  if (isset($_POST['save_shift'])) {
      // 保存は POST の user を絶対的な真実として扱う（選択中スタッフ）
      $post_user = intval($_POST['user'] ?? 0);
      if ($is_admin && $post_user > 0) {
          $uid = $post_user; // 管理者は誰でも保存できる
      }

      check_admin_referer('save_shift_' . $ym);

      $days = $_POST['days'] ?? [];
      if (!is_array($days)) $days = [$days];
      $days = array_map('intval', $days);
      $days = array_values(array_unique($days));
      sort($days);

      update_user_meta($uid, salon_shift_meta_key($ym), $days);
      $just_saved = true;

      // POST再送防止：同対象でGETに戻す
      $redir = add_query_arg([
          'page'  => 'salon-shifts',
          'user'  => $uid,
          'ym'    => $ym,
          'saved' => 1,
      ], admin_url('admin.php'));

      if (!headers_sent()) {
          wp_safe_redirect($redir);
          exit;
      }
  }

  // 現在値取得
  $days = (array) get_user_meta($uid, salon_shift_meta_key($ym), true);

  // ───────── 表示 ─────────
  echo '<div class="wrap"><h1>出勤管理</h1>';

  // 成功通知（リダイレクト or 直描画の両対応）
  if (!empty($_GET['saved']) || $just_saved) {
      echo '<div class="notice notice-success is-dismissible"><p>保存しました ✅</p></div>';
  }

  // スタッフ選択（GET）
  echo '<form method="get" style="margin-bottom:10px">';
echo '<input type="hidden" name="page" value="salon-shifts">';

if ($is_admin) {
    echo 'スタッフ：<select name="user">';
    foreach (salon_get_staff_users() as $u) {
        printf(
            '<option value="%d"%s>%s</option>',
            $u->ID,
            selected($uid, $u->ID, false),
            esc_html($u->display_name)
        );
    }
    echo '</select> ';
    echo '<button type="submit" class="button">変更</button>';
} else {
    echo '<strong>'. esc_html($current->display_name) .'</strong> ';
    echo '<input type="hidden" name="user" value="'. esc_attr($uid) .'">';
}

echo '</form>';

  // 月移動（GET維持）
  $dt   = DateTime::createFromFormat('Ym', $ym);
  $prev = $dt->modify('-1 month')->format('Ym');
  $dt   = DateTime::createFromFormat('Ym', $ym);
  $next = $dt->modify('+1 month')->format('Ym');

  printf('<a class="button" href="?page=salon-shifts&user=%d&ym=%s">前月</a> ', $uid, $prev);
  printf('<a class="button" href="?page=salon-shifts&user=%d&ym=%s">今月</a> ', $uid, date('Ym'));
  printf('<a class="button" href="?page=salon-shifts&user=%d&ym=%s">次月</a>', $uid, $next);
  echo '</form>';

  // カレンダー
  $year = (int)substr($ym, 0, 4);
  $month = (int)substr($ym, 4, 2);
  $days_in_month = (int)date('t', strtotime("$year-$month-01"));

  echo '<form method="post" class="salon-shift-form">';
  wp_nonce_field('save_shift_' . $ym);

  // ✅ 保存対象を “user” で POST に渡す（GETと同じキーに統一）
  echo '<input type="hidden" name="user" value="' . esc_attr($uid) . '">';
  echo '<input type="hidden" name="ym" value="' . esc_attr($ym) . '">';

  echo "<h2>{$year}年 {$month}月</h2>";
  echo '<div class="salon-shift-grid">';
  for ($d = 1; $d <= $days_in_month; $d++) {
      $checked = in_array($d, $days, true);
      $w       = (int) date('w', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $d)));
      $w_jp    = ['日','月','火','水','木','金','土'][$w];
      $cls     = $checked ? ' salon-shift-on' : '';
      echo '<div class="salon-shift-cell'. esc_attr($cls) .'">';
      echo '<div class="salon-shift-date">'. $d .'日（'. $w_jp .'）</div>';
      echo '<label><input type="checkbox" name="days[]" value="'. $d .'" '. checked($checked, true, false) .'> 出勤</label>';
      echo '</div>';
  }
  echo '</div>';
  submit_button('保存', 'primary', 'save_shift');
  echo '</form></div>';
}

/* =========================================================
* 予約一覧：カラム/並び替え/絞り込み
* =======================================================*/

// カラム
add_filter('manage_edit-reservation_columns', function ($columns) {
  return [
    'cb'            => '<input type="checkbox" />',
    'res_datetime'  => '予約日時',
    'res_name'      => '名前',
    'res_tel'       => '電話番号',
    'res_menu'      => 'メニュー',
    'res_staff'     => '担当',
    'actions'       => '操作',
    'date'          => '公開日'
  ];
});

// 出力
add_action('manage_reservation_posts_custom_column', function ($column, $post_id) {
  $v = get_post_meta($post_id, $column, true);

  switch ($column) {
    case 'res_datetime':
    case 'res_name':
    case 'res_menu':
      echo esc_html($v ?: 'ー');
      break;

    case 'res_staff':
      if ($v) {
        // user_id -> display_name（旧データ=文字列はそのまま表示）
        if (is_numeric($v)) {
          $u = get_userdata(intval($v));
          echo $u ? esc_html($u->display_name) : 'ー';
        } else {
          echo esc_html($v);
        }
      } else {
        echo 'ー';
      }
      break;

    case 'res_tel':
      echo $v ? '<a href="tel:' . esc_attr($v) . '">' . esc_html($v) . '</a>' : 'ー';
      break;

    case 'actions':
      $edit = get_edit_post_link($post_id);
      $del  = get_delete_post_link($post_id, '', true);
      echo '<a class="button button-small" href="'.esc_url($edit).'">編集</a> ';
      echo '<a class="button button-small" href="'.esc_url($del).'" onclick="return confirm(\'本当に削除しますか？\');">削除</a>';
      break;
  }
}, 10, 2);

// 並び替え可能設定
add_filter('manage_edit-reservation_sortable_columns', function ($columns) {
  $columns['res_datetime'] = 'res_datetime';
  $columns['res_staff']    = 'res_staff';
  return $columns;
});

// 並び替え実体 & 既定：datetime昇順
add_action('pre_get_posts', function ($query) {
  if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'reservation') return;

  $orderby = $query->get('orderby');
  if ($orderby === 'res_staff') {
      $query->set('meta_key', 'res_staff');
      $query->set('orderby', 'meta_value');
  } else {
      $query->set('meta_key', 'res_datetime');
      $query->set('orderby',  'meta_value');
      $query->set('order',    'ASC');
  }
});

// 絞り込みUI
add_action('restrict_manage_posts', function () {
  global $typenow;
  if ($typenow !== 'reservation') return;

  // 担当
  $staffs = salon_get_staff_users();
  $selected_staff = $_GET['filter_staff'] ?? '';
  echo '<select name="filter_staff">';
  echo '<option value="">担当（すべて）</option>';
  foreach ($staffs as $s) {
      printf('<option value="%s"%s>%s</option>',
          esc_attr($s->ID),
          selected($selected_staff, (string)$s->ID, false),
          esc_html($s->display_name)
      );
  }
  echo '</select>';

  // 月
  $selected_month = $_GET['filter_month'] ?? '';
  echo '<input type="month" name="filter_month" value="'. esc_attr($selected_month) .'">';
});

// 絞り込み処理
add_filter('pre_get_posts', function ($query) {
  if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'reservation') return;

  if (!empty($_GET['filter_staff'])) {
      $query->set('meta_key', 'res_staff');
      $query->set('meta_value', sanitize_text_field($_GET['filter_staff']));
  }

  if (!empty($_GET['filter_month'])) {
      $month = sanitize_text_field($_GET['filter_month']); // yyyy-mm
      $query->set('meta_query', [
          [
              'key'     => 'res_datetime',
              'value'   => $month,
              'compare' => 'LIKE',
          ]
      ]);
  }
});

/* デフォルトはサロンスタッフに */
add_action('user_register', function($user_id){
  $u = get_userdata($user_id);
  if ($u && empty($u->roles)) { $u->set_role('salon_staff'); }
});

/* 権限の明示付与（念のため） */
add_action('init', function () {
  // スタッフ権限強化
  $role = get_role('salon_staff');
  if ($role) {
      $role->add_cap('read');
      $role->add_cap('edit_user');
      $role->add_cap('edit_users');
      $role->add_cap('list_users');
  }
  // 管理者も対象ユーザーの user_meta を編集可に
  $admin = get_role('administrator');
  if ($admin) {
      $admin->add_cap('edit_user');
      $admin->add_cap('edit_users');
  }
});


/**
 * 管理画面「ユーザー追加」でデフォルト権限をサロンスタッフに変更
 */
add_action('admin_footer-user-new.php', function () {
  ?>
  <script>
      document.addEventListener('DOMContentLoaded', function () {
          const roleSelect = document.getElementById('role');
          if (roleSelect) {
              roleSelect.value = 'salon_staff';
          }
      });
  </script>
  <?php
});
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
 * サロン予約・出勤管理（時間帯対応・完成版）
 * - 予約CPT
 * - 予約メタ（担当スタッフ = user_id保存／旧display_name互換）
 * - 出勤管理（各日ごとに開始〜終了時刻を保存）
 * - 管理画面の予約一覧カラム＆並び替え＆絞り込み
 * - 指名なし（0）対応
 * - フロント：メニュー→スタッフ（指名なし含む）→カレンダー→モーダル予約（メール通知）
 */

/// ============================
/// 固定営業時間（30分刻み）
/// ============================
const SALON_OPEN  = '09:00';
const SALON_CLOSE = '19:30';
const SALON_STEP  = 30; // minutes

/** 30分刻みの時刻配列 */
function salon_time_slots($from = SALON_OPEN, $to = SALON_CLOSE, $step = SALON_STEP){
    $out = []; $t = strtotime($from); $end = strtotime($to);
    while ($t <= $end){ $out[] = date('H:i', $t); $t += $step * 60; }
    return $out;
}
function salon_time_to_min($hhmm){ if(!$hhmm) return null; [$h,$m]=array_map('intval',explode(':',$hhmm)); return $h*60+$m; }
function salon_between($time,$start,$end){ $t=salon_time_to_min($time); $s=salon_time_to_min($start); $e=salon_time_to_min($end); if($t===null||$s===null||$e===null) return false; return ($t>=$s)&&($t<$e); }

/** 出勤メタキー（YYYYMM） */
function salon_shift_meta_key($ym){ return 'salon_shift_'.$ym; }
/** 旧: 数値配列→OPEN〜CLOSEに正規化 */
function salon_upgrade_days_to_ranges($days, $ym){
    $out=[]; foreach((array)$days as $d){ $d=(int)$d; if($d>=1&&$d<=31){ $out[$d]=['s'=>SALON_OPEN,'e'=>SALON_CLOSE]; } } return $out;
}
/** 保存形式正規化 day => ['s'=>'HH:MM','e'=>'HH:MM'] */
function salon_normalize_shift_meta($raw,$ym){
    if(!$raw) return [];
    if(array_values($raw)===$raw && is_int(reset($raw))){ return salon_upgrade_days_to_ranges($raw,$ym); }
    $out=[]; foreach((array)$raw as $day=>$pair){ $s=$pair['s']??''; $e=$pair['e']??''; if($s&&$e&&salon_time_to_min($e)>salon_time_to_min($s)){ $out[(int)$day]=['s'=>$s,'e'=>$e]; } }
    return $out;
}

/* =========================================================
 *  ロール & 予約CPT
 * =======================================================*/
add_action('init', function () {
    register_post_type('reservation', [
        'label'        => '予約',
        'public'       => false,
        'show_ui'      => true,
        'supports'     => [],
        'menu_icon'    => 'dashicons-calendar-alt',
        'show_in_rest' => false,
    ]);
    if (!get_role('salon_staff')) add_role('salon_staff', 'サロンスタッフ', ['read' => true]);
});

/** スタッフ一覧 */
function salon_get_staff_users() {
    return get_users([
        'role'    => 'salon_staff',
        'orderby' => 'display_name',
        'order'   => 'ASC',
        'fields'  => ['ID','display_name','user_login'],
    ]);
}

/* =========================================================
*  メニュー定義（表示名 / 価格はUIで使用）
*  ※ 施術時間は各スタッフのプロフィール画面で上書き可能
* =======================================================*/
function rsrv_get_menu_master(){
    // key => [label, price, default_duration_min]
    return [
      'カット'         => ['label'=>'カット',         'price'=>4000,  'dur'=>60],
      'カラー'         => ['label'=>'カラー',         'price'=>6000,  'dur'=>60],
      'カット＋カラー' => ['label'=>'カット＋カラー', 'price'=>10000, 'dur'=>120],
      'パーマ'         => ['label'=>'パーマ',         'price'=>8000,  'dur'=>90],
      'トリートメント' => ['label'=>'トリートメント', 'price'=>3000,  'dur'=>30],
    ];
}
function rsrv_get_menu_options(){ $m=rsrv_get_menu_master(); $out=[]; foreach($m as $k=>$v){ $out[$k]=$v['label']; } return $out; }
function rsrv_menu_default_duration($key){ $m=rsrv_get_menu_master(); return (int)($m[$key]['dur'] ?? 60); }
function rsrv_menu_price($key){ $m=rsrv_get_menu_master(); return (int)($m[$key]['price'] ?? 0); }

/* =========================================================
*  管理画面：予約メタボックス
* =======================================================*/
add_action('add_meta_boxes', function () {
    add_meta_box('reservation_fields', '予約情報', 'rsrv_render_mb', 'reservation', 'normal', 'high');
});

function rsrv_render_mb($post) {
  wp_nonce_field('rsrv_save', 'rsrv_nonce');

  $name   = get_post_meta($post->ID, 'res_name', true);
  $tel    = get_post_meta($post->ID, 'res_tel', true);
  $email  = get_post_meta($post->ID, 'res_email', true);
  $date   = get_post_meta($post->ID, 'res_date', true);
  $time   = get_post_meta($post->ID, 'res_time', true);
  $menu   = get_post_meta($post->ID, 'res_menu', true);
  $staff  = get_post_meta($post->ID, 'res_staff', true); // user_id or 0(指名なし)

  // 新規作成時：URLパラメータ反映
  if ($post->post_status === 'auto-draft') {
    if (!empty($_GET['res_date']))  $date  = sanitize_text_field($_GET['res_date']);
    if (!empty($_GET['res_time']))  $time  = sanitize_text_field($_GET['res_time']);
    if (!empty($_GET['res_staff'])) $staff = intval($_GET['res_staff']);
  }

  $menus  = rsrv_get_menu_options();
  $staffs = salon_get_staff_users();

  // 現在選択中のスタッフに応じてメニューを絞る
  $filtered_menus = $menus;
  if ($staff && (int)$staff !== 0) {
    $menu_settings = get_user_meta((int)$staff, 'salon_menu_settings', true) ?: [];
    $filtered_menus = [];
    foreach ($menus as $key => $label) {
      if (!empty($menu_settings[$key]['enabled'])) $filtered_menus[$key] = $label;
    }
    if (!$filtered_menus) $filtered_menus = $menus;
  }

  // カレンダーから来た場合：担当固定表示
  $is_fixed_staff = ($post->post_status === 'auto-draft' && isset($_GET['res_staff']) && (int)$_GET['res_staff']>0);
  $fixed_staff_id = $is_fixed_staff ? intval($_GET['res_staff']) : 0;
  $fixed_staff    = $fixed_staff_id ? get_userdata($fixed_staff_id) : null;
  ?>
  <table class="form-table">
    <tr><th><label>名前 *</label></th>
      <td><input type="text" name="res_name" class="regular-text" required value="<?php echo esc_attr($name); ?>"></td></tr>
    <tr><th><label>電話 *</label></th>
      <td><input type="text" name="res_tel" class="regular-text" required value="<?php echo esc_attr($tel); ?>"></td></tr>
    <tr><th><label>メール</label></th>
      <td><input type="email" name="res_email" class="regular-text" value="<?php echo esc_attr($email); ?>"></td></tr>
    <tr><th><label>日時 *</label></th>
      <td>
        <input type="date" name="res_date" value="<?php echo esc_attr($date); ?>" required>
        <input type="time" name="res_time" value="<?php echo esc_attr($time); ?>"
               min="<?php echo SALON_OPEN; ?>" max="<?php echo SALON_CLOSE; ?>"
               step="<?php echo SALON_STEP*60; ?>" required>
        <p class="description">※ <?php echo SALON_OPEN; ?>〜<?php echo SALON_CLOSE; ?> の<?php echo SALON_STEP; ?>分刻み</p>
      </td></tr>
    <tr><th><label>メニュー *</label></th>
      <td>
        <select name="res_menu" id="res_menu_select" required>
          <option value="">— 選択 —</option>
          <?php foreach ($filtered_menus as $k => $v): ?>
            <option value="<?php echo esc_attr($k); ?>" <?php selected($menu, $k); ?>><?php echo esc_html($v); ?></option>
          <?php endforeach; ?>
        </select>
        <p class="description">※ 担当スタッフが対応可能なメニューのみ表示</p>
      </td></tr>

    <tr><th><label>担当 *</label></th>
      <td>
        <?php if ($is_fixed_staff && $fixed_staff): ?>
          <strong><?php echo esc_html($fixed_staff->display_name); ?></strong>
          <input type="hidden" name="res_staff" value="<?php echo esc_attr($fixed_staff_id); ?>">
        <?php else: ?>
          <select name="res_staff" id="res_staff_select" required>
            <option value="">— 選択 —</option>
            <option value="0" <?php selected((string)$staff,'0'); ?>>指名なし</option>
            <?php foreach ($staffs as $s): ?>
              <option value="<?php echo esc_attr($s->ID); ?>" <?php selected((int)$staff,$s->ID); ?>>
                <?php echo esc_html($s->display_name); ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </td></tr>
  </table>
  <?php
}

/** 予約重複チェック + 保存処理（管理画面） */
add_action('save_post_reservation', function ($post_id, $post, $update) {

  if (!isset($_POST['rsrv_nonce']) || !wp_verify_nonce($_POST['rsrv_nonce'], 'rsrv_save')) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;

  static $processing = false; if ($processing) return; $processing = true;

  $name  = sanitize_text_field($_POST['res_name'] ?? '');
  $tel   = sanitize_text_field($_POST['res_tel'] ?? '');
  $email = sanitize_email($_POST['res_email'] ?? '');
  $date  = sanitize_text_field($_POST['res_date'] ?? '');
  $time  = sanitize_text_field($_POST['res_time'] ?? '');
  $menu  = sanitize_text_field($_POST['res_menu'] ?? '');
  $staff = intval($_POST['res_staff'] ?? -1); // 0=指名なし

  if (!$date || !$time || !$menu || $staff===-1) return;

  // 指名なしは管理保存時は重複チェック不要（自動アサインはフロントのみ運用想定）
  if($staff>0){
    $settings = get_user_meta($staff, 'salon_menu_settings', true) ?: [];
    $duration = intval($settings[$menu]['duration'] ?? rsrv_menu_default_duration($menu));
    $new_start = strtotime("$date $time"); $new_end = $new_start + ($duration * 60);

    $existing = new WP_Query([
      'post_type' => 'reservation','post_status'=>'any','posts_per_page'=>-1,
      'meta_query' => [
        ['key'=>'res_staff','value'=>$staff,'compare'=>'='],
        ['key'=>'res_date','value'=>$date,'compare'=>'='],
      ],
    ]);
    if ($existing->have_posts()) {
      while ($existing->have_posts()) { $existing->the_post();
        $pid=get_the_ID(); if ($pid==$post_id) continue;
        $ex_time = get_post_meta($pid,'res_time',true);
        $ex_menu = get_post_meta($pid,'res_menu',true);
        if(!$ex_time || !$ex_menu) continue;
        $ex_settings = get_user_meta($staff,'salon_menu_settings',true) ?: [];
        $ex_dur = intval($ex_settings[$ex_menu]['duration'] ?? rsrv_menu_default_duration($ex_menu));
        $ex_s=strtotime("$date $ex_time"); $ex_e=$ex_s + ($ex_dur*60);
        if ($new_start < $ex_e && $new_end > $ex_s) {
          remove_action('save_post_reservation', __FUNCTION__, 10);
          wp_die('<strong style="color:#d63638;font-size:16px;">選択した時間帯は既に予約が入っています。</strong>','予約エラー',['response'=>400,'back_link'=>true]);
        }
      }
      wp_reset_postdata();
    }
  }

  update_post_meta($post_id, 'res_name',  $name);
  update_post_meta($post_id, 'res_tel',   $tel);
  update_post_meta($post_id, 'res_email', $email);
  update_post_meta($post_id, 'res_date',  $date);
  update_post_meta($post_id, 'res_time',  $time);
  update_post_meta($post_id, 'res_menu',  $menu);
  update_post_meta($post_id, 'res_staff', $staff);
  update_post_meta($post_id, 'res_datetime', "$date $time:00");

  remove_action('save_post_reservation', __FUNCTION__, 10);
  wp_update_post([
      'ID'         => $post_id,
      'post_title' => sprintf('%s %s / %s（%s）',$date ?: '',$time ?: '',esc_html($name),esc_html($menu)),
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
*  出勤管理（開始〜終了時刻）  user_meta "salon_shift_YYYYMM"
* =======================================================*/
add_action('admin_menu', function () {
    add_menu_page('出勤管理','出勤管理','read','salon-shifts','salon_render_shifts_page','dashicons-groups',26);
});

function salon_render_shifts_page() {
    $current  = wp_get_current_user();
    $is_admin = in_array('administrator', (array)$current->roles, true);

    $uid = $is_admin ? intval($_GET['user'] ?? $_POST['user'] ?? $current->ID) : $current->ID;
    $ym  = preg_replace('/[^0-9]/', '', ($_GET['ym'] ?? $_POST['ym'] ?? date('Ym')));

    if (isset($_POST['save_shift'])) {
        if ($is_admin && !empty($_POST['user'])) $uid = intval($_POST['user']);
        check_admin_referer('save_shift_'.$ym);
        $starts = $_POST['start'] ?? []; $ends = $_POST['end'] ?? [];
        $days = (int)date('t', strtotime(substr($ym,0,4).'-'.substr($ym,4,2).'-01'));
        $save = [];
        for($d=1;$d<=$days;$d++){
          $s = sanitize_text_field($starts[$d] ?? '');
          $e = sanitize_text_field($ends[$d]   ?? '');
          if($s && $e && salon_time_to_min($e) > salon_time_to_min($s)){ $save[$d]=['s'=>$s,'e'=>$e]; }
        }
        update_user_meta($uid, salon_shift_meta_key($ym), $save);
        $redir = add_query_arg(['page'=>'salon-shifts','user'=>$uid,'ym'=>$ym,'saved'=>1], admin_url('admin.php'));
        if(!headers_sent()){ wp_safe_redirect($redir); exit; }
    }

    $meta_raw = get_user_meta($uid, salon_shift_meta_key($ym), true);
    $shift    = salon_normalize_shift_meta((array)$meta_raw, $ym);

    echo '<div class="wrap"><h1>出勤管理</h1>';
    if (!empty($_GET['saved'])) echo '<div class="notice notice-success is-dismissible"><p>保存しました ✅</p></div>';

    echo '<form method="get" style="margin-bottom:10px"><input type="hidden" name="page" value="salon-shifts">';
    if ($is_admin) {
        echo 'スタッフ：<select name="user">';
        foreach (salon_get_staff_users() as $u) printf('<option value="%d"%s>%s</option>', $u->ID, selected($uid, $u->ID, false), esc_html($u->display_name));
        echo '</select> <button class="button">変更</button>';
    } else {
        echo '<strong>'. esc_html($current->display_name) .'</strong> <input type="hidden" name="user" value="'. esc_attr($uid) .'">';
    }
    echo '</form>';

    $dt=DateTime::createFromFormat('Ym',$ym); $prev=$dt->modify('-1 month')->format('Ym'); $dt=DateTime::createFromFormat('Ym',$ym); $next=$dt->modify('+1 month')->format('Ym');
    printf('<a class="button" href="?page=salon-shifts&user=%d&ym=%s">前月</a> ', $uid, $prev);
    printf('<a class="button" href="?page=salon-shifts&user=%d&ym=%s">今月</a> ', $uid, date('Ym'));
    printf('<a class="button" href="?page=salon-shifts&user=%d&ym=%s">次月</a>', $uid, $next);

    $year=(int)substr($ym,0,4); $month=(int)substr($ym,4,2); $days=(int)date('t',strtotime("$year-$month-01")); $times=salon_time_slots();

    echo '<form method="post" class="salon-shift-form" style="margin-top:14px;">'; wp_nonce_field('save_shift_'.$ym);
    echo '<input type="hidden" name="user" value="'.esc_attr($uid).'"><input type="hidden" name="ym" value="'.esc_attr($ym).'">';
    echo "<h2>{$year}年 {$month}月</h2><div class=\"salon-shift-grid\">";
    for($d=1;$d<=$days;$d++){
        $w=(int)date('w',strtotime(sprintf('%04d-%02d-%02d',$year,$month,$d))); $jp=['日','月','火','水','木','金','土'][$w];
        $cur=$shift[$d]??['s'=>'','e'=>''];
        echo '<div class="salon-shift-cell"><div class="salon-shift-date">'.$d.'日（'.$jp.'）</div>';
        echo '<div class="time-row"><label>開始</label><select name="start['.$d.']"><option value="">—</option>';
        foreach($times as $t) printf('<option value="%s"%s>%s</option>',esc_attr($t),selected($cur['s']??'',$t,false),esc_html($t)); echo '</select></div>';
        echo '<div class="time-row"><label>終了</label><select name="end['.$d.']"><option value="">—</option>';
        foreach($times as $t) printf('<option value="%s"%s>%s</option>',esc_attr($t),selected($cur['e']??'',$t,false),esc_html($t)); echo '</select></div>';
        echo '<p class="desc">※ 空欄で休み。終了は開始より後にしてください。</p></div>';
    }
    echo '</div>'; submit_button('保存','primary','save_shift'); echo '</form></div>';
}

/* =========================================================
* 予約一覧：カラム/並び替え/絞り込み
* =======================================================*/
add_filter('manage_edit-reservation_columns', function ($columns) {
    return ['cb'=>'<input type="checkbox" />','res_datetime'=>'予約日時','res_name'=>'名前','res_tel'=>'電話番号','res_menu'=>'メニュー','res_staff'=>'担当','actions'=>'操作','date'=>'公開日'];
});
add_action('manage_reservation_posts_custom_column', function ($column, $post_id) {
    $v = get_post_meta($post_id, $column, true);
    switch ($column) {
        case 'res_datetime':
        case 'res_name':
        case 'res_menu': echo esc_html($v ?: 'ー'); break;
        case 'res_staff':
            if ($v==='0' || (int)$v===0) { echo '指名なし'; break; }
            if ($v) { $u=is_numeric($v)?get_userdata((int)$v):null; echo $u?esc_html($u->display_name):'ー'; } else { echo 'ー'; }
            break;
        case 'res_tel': echo $v ? '<a href="tel:' . esc_attr($v) . '">' . esc_html($v) . '</a>' : 'ー'; break;
        case 'actions':
            $edit = get_edit_post_link($post_id); $del  = get_delete_post_link($post_id, '', true);
            echo '<a class="button button-small" href="'.esc_url($edit).'">編集</a> ';
            echo '<a class="button button-small" href="'.esc_url($del).'" onclick="return confirm(\'本当に削除しますか？\');">削除</a>';
            break;
    }
}, 10, 2);
add_filter('manage_edit-reservation_sortable_columns', function ($columns) { $columns['res_datetime']='res_datetime'; $columns['res_staff']='res_staff'; return $columns; });
add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type')!=='reservation') return;
    $orderby = $query->get('orderby');
    if ($orderby === 'res_staff') { $query->set('meta_key','res_staff'); $query->set('orderby','meta_value'); }
    else { $query->set('meta_key','res_datetime'); $query->set('orderby','meta_value'); $query->set('order','ASC'); }
});
add_action('restrict_manage_posts', function () {
    global $typenow; if ($typenow!=='reservation') return;
    $staffs = salon_get_staff_users(); $selected_staff = $_GET['filter_staff'] ?? '';
    echo '<select name="filter_staff"><option value="">担当（すべて）</option>';
    foreach ($staffs as $s) printf('<option value="%s"%s>%s</option>',esc_attr($s->ID),selected($selected_staff,(string)$s->ID,false),esc_html($s->display_name));
    echo '</select><input type="month" name="filter_month" value="'. esc_attr($_GET['filter_month'] ?? '') .'">';
});
add_filter('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type')!=='reservation') return;
    if (!empty($_GET['filter_staff'])) { $query->set('meta_key','res_staff'); $query->set('meta_value',sanitize_text_field($_GET['filter_staff'])); }
    if (!empty($_GET['filter_month'])) { $month=sanitize_text_field($_GET['filter_month']); $query->set('meta_query', [[ 'key'=>'res_datetime','value'=>$month,'compare'=>'LIKE' ]]); }
});

/* デフォルトはサロンスタッフに */
add_action('user_register', function($user_id){ $u=get_userdata($user_id); if ($u && empty($u->roles)) $u->set_role('salon_staff'); });
add_action('admin_footer-user-new.php', function () { echo '<script>document.addEventListener("DOMContentLoaded",function(){var r=document.getElementById("role");if(r){r.value="salon_staff";}});</script>'; });
add_action('init', function () {
    if($role=get_role('salon_staff')){ $role->add_cap('read'); $role->add_cap('edit_user'); $role->add_cap('edit_users'); $role->add_cap('list_users'); }
    if($admin=get_role('administrator')){ $admin->add_cap('edit_user'); $admin->add_cap('edit_users'); }
});

/* =========================================================
 * スタッフごとの施術メニュー設定（対応可＋施術時間）
 * 保存先：user_meta「salon_menu_settings」
 * =======================================================*/
function salon_staff_menu_settings_fields($user) {
    if (!in_array('salon_staff', (array)$user->roles)) return;
    $menus = rsrv_get_menu_master(); $saved = get_user_meta($user->ID,'salon_menu_settings',true) ?: [];
    echo '<h2>施術メニュー設定</h2><table class="form-table">';
    foreach ($menus as $key => $v){
        $label=$v['label']; $def=$v['dur']; $enabled=$saved[$key]['enabled']??0; $duration=$saved[$key]['duration']??$def;
        echo '<tr><th><label>'.esc_html($label).'</label></th><td>';
        echo '<label><input type="checkbox" name="salon_menu_enabled['.esc_attr($key).']" value="1" '.checked($enabled,1,false).'> 対応可</label> ';
        echo '<select name="salon_menu_duration['.esc_attr($key).']">';
        for($m=30;$m<=180;$m+=30) echo '<option value="'.$m.'" '.selected($duration,$m,false).'>'.$m.' 分</option>';
        echo '</select></td></tr>';
    }
    echo '</table>';
}
add_action('show_user_profile','salon_staff_menu_settings_fields');
add_action('edit_user_profile','salon_staff_menu_settings_fields');

function salon_save_staff_menu_settings($user_id) {
    if (!current_user_can('edit_user', $user_id)) return;
    $enabled  = $_POST['salon_menu_enabled']  ?? [];
    $duration = $_POST['salon_menu_duration'] ?? [];
    $menus = rsrv_get_menu_master(); $save = [];
    foreach ($menus as $key => $v) $save[$key] = ['enabled'=>isset($enabled[$key])?1:0,'duration'=> isset($duration[$key])?intval($duration[$key]):$v['dur']];
    update_user_meta($user_id, 'salon_menu_settings', $save);
}
add_action('personal_options_update','salon_save_staff_menu_settings');
add_action('edit_user_profile_update','salon_save_staff_menu_settings');

/* =========================================================
 * Ajax：フロント用
 * =======================================================*/

/** メニューに対応できるスタッフ（指名候補） */
add_action('wp_ajax_nopriv_salon_get_staffs_by_menu_front','salon_get_staffs_by_menu_front');
add_action('wp_ajax_salon_get_staffs_by_menu_front','salon_get_staffs_by_menu_front');
function salon_get_staffs_by_menu_front(){
  $menu_key = sanitize_text_field($_POST['menu_key'] ?? '');
  if(!$menu_key) wp_send_json([]);
  $staffs = salon_get_staff_users(); $out=['0'=>'指名なし'];
  foreach($staffs as $u){
    $settings = get_user_meta($u->ID, 'salon_menu_settings', true) ?: [];
    if(!empty($settings[$menu_key]['enabled'])) $out[(string)$u->ID] = $u->display_name;
  }
  wp_send_json($out);
}

/** 指名なし用：空いている対応可スタッフを選ぶ */
function rsrv_pick_staff_for($menu_key, $date, $time){
  $staffs = salon_get_staff_users(); $cands=[];
  foreach($staffs as $u){
    $settings = get_user_meta($u->ID,'salon_menu_settings',true) ?: [];
    if(empty($settings[$menu_key]['enabled'])) continue;
    $duration = intval($settings[$menu_key]['duration'] ?? rsrv_menu_default_duration($menu_key));

    // シフト内?
    $ym = date('Ym', strtotime($date));
    $raw = get_user_meta($u->ID, salon_shift_meta_key($ym), true);
    $shift = salon_normalize_shift_meta((array)$raw, $ym);
    $day = (int)date('d', strtotime($date));
    if(empty($shift[$day])) continue;
    $s=$shift[$day]['s']; $e=$shift[$day]['e'];
    if(!salon_between($time,$s,$e)) continue;

    // 重複なし?
    $start = strtotime("$date $time"); $end = $start + ($duration*60);
    $existing = get_posts([
      'post_type'=>'reservation','post_status'=>'any','posts_per_page'=>-1,
      'meta_query'=>[
        ['key'=>'res_staff','value'=>$u->ID,'compare'=>'='],
        ['key'=>'res_date','value'=>$date,'compare'=>'='],
      ],
    ]);
    $ok=true;
    foreach($existing as $p){
      $ex_t = get_post_meta($p->ID,'res_time',true);
      $ex_m = get_post_meta($p->ID,'res_menu',true);
      $ex_settings = get_user_meta($u->ID,'salon_menu_settings',true) ?: [];
      $ex_dur = intval($ex_settings[$ex_m]['duration'] ?? rsrv_menu_default_duration($ex_m));
      $ex_s = strtotime("$date $ex_t"); $ex_e = $ex_s + ($ex_dur*60);
      if($start < $ex_e && $end > $ex_s){ $ok=false; break; }
    }
    if($ok) $cands[]=$u->ID;
  }
  return $cands[0] ?? 0; // 最初の人
}

/** カレンダーHTML生成（1週間／メニュー必須／スタッフ0=指名なし） */
function salon_generate_calendar_html($menu_key, $staff_id, $week = 0){
  date_default_timezone_set('Asia/Tokyo');

  // 「今週の月曜」を明示的に固定（現在時刻に依存しない）
  $today = strtotime(date('Y-m-d')); 
  $monday = strtotime('monday this week', $today);

  // week単位でずらす
  $start = strtotime("+".(7 * intval($week))." days", $monday);

  // デバッグ出力
  // error_log("[SALON DEBUG] week={$week}, start=" . date('Y-m-d', $start));

  $week_dates = [];
  for ($i = 0; $i < 7; $i++) {
    $week_dates[] = date('Y-m-d', strtotime("+$i day", $start));
  }

  // ▼ 時間スロット取得
  $times = salon_time_slots();

  // ▼ 対象スタッフ取得
  $staff_pool = [];
  if ($staff_id > 0) {
    $u = get_userdata($staff_id);
    if ($u) $staff_pool = [$u];
  } else {
    $staff_pool = salon_get_staff_users();
  }

  // ▼ 予約データ取得
  $booked = [];
  foreach ($staff_pool as $u) {
    $posts = get_posts([
      'post_type' => 'reservation',
      'post_status' => 'any',
      'numberposts' => -1,
      'meta_query' => [
        ['key' => 'res_staff', 'value' => $u->ID, 'compare' => '='],
        ['key' => 'res_date', 'value' => $week_dates, 'compare' => 'IN'],
      ],
    ]);
    foreach ($posts as $p) {
      $d = get_post_meta($p->ID, 'res_date', true);
      $t = get_post_meta($p->ID, 'res_time', true);
      $m = get_post_meta($p->ID, 'res_menu', true);
      if (!$d || !$t || !$m) continue;
      $settings = get_user_meta($u->ID, 'salon_menu_settings', true) ?: [];
      $dur = intval($settings[$m]['duration'] ?? rsrv_menu_default_duration($m));
      $ts = strtotime("$d $t"); 
      $te = $ts + ($dur * 60);
      for ($x = $ts; $x < $te; $x += (SALON_STEP * 60)) {
        $key = date('H:i', $x);
        $booked[$d][$key][$u->ID] = true;
      }
    }
  }

  // ▼ 出勤情報取得
  $shifts = [];
  foreach ($staff_pool as $u) {
    $shifts[$u->ID] = [];
    $ym_keys = [];
    foreach ($week_dates as $d) {
      $ym_keys[date('Ym', strtotime($d))] = true;
    }
    foreach (array_keys($ym_keys) as $ym) {
      $raw = get_user_meta($u->ID, salon_shift_meta_key($ym), true);
      $norm = salon_normalize_shift_meta((array)$raw, $ym);
      $y = (int)substr($ym, 0, 4);
      $m = (int)substr($ym, 4, 2);
      foreach ($norm as $day => $pair) {
        $date = sprintf('%04d-%02d-%02d', $y, $m, (int)$day);
        $shifts[$u->ID][$date] = $pair;
      }
    }
  }

  // ▼ カレンダーHTML出力
  ob_start(); ?>
  <div class="salon-calendar">
    <h3 class="cal-title">空き状況（1週間）</h3>
    <div class="cal-legend"><span>○：予約可</span><span>×：不可</span></div>
    <table class="calendar-table">
      <thead>
        <tr>
          <th class="time-col"></th>
          <?php foreach ($week_dates as $d): ?>
            <th><?= esc_html(date('n/j (D)', strtotime($d))) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($times as $time): ?>
          <tr>
            <th class="time-col"><?= esc_html($time) ?></th>
            <?php foreach ($week_dates as $d): ?>
              <?php
              $available = false; 
              $chosen_staff = 0;

              if ($staff_id > 0) {
                // 指名あり
                $u = $staff_pool[0] ?? null;
                if ($u) {
                  $shift = $shifts[$u->ID][$d] ?? null;
                  if ($shift && salon_between($time, $shift['s'], $shift['e']) && empty($booked[$d][$time][$u->ID])) {
                    $settings = get_user_meta($u->ID, 'salon_menu_settings', true) ?: [];
                    $dur = intval($settings[$menu_key]['duration'] ?? rsrv_menu_default_duration($menu_key));
                    $ok = true;
                    $ts = strtotime("$d $time");
                    $te = $ts + ($dur * 60);
                    for ($x = $ts; $x < $te; $x += (SALON_STEP * 60)) {
                      $kk = date('H:i', $x);
                      if (!salon_between($kk, $shift['s'], $shift['e']) || !empty($booked[$d][$kk][$u->ID])) {
                        $ok = false;
                        break;
                      }
                    }
                    if ($ok) { $available = true; $chosen_staff = $u->ID; }
                  }
                }
              } else {
                // 指名なし
                foreach ($staff_pool as $u) {
                  $settings = get_user_meta($u->ID, 'salon_menu_settings', true) ?: [];
                  if (empty($settings[$menu_key]['enabled'])) continue;
                  $shift = $shifts[$u->ID][$d] ?? null;
                  if (!$shift || !salon_between($time, $shift['s'], $shift['e'])) continue;
                  $dur = intval($settings[$menu_key]['duration'] ?? rsrv_menu_default_duration($menu_key));
                  $ok = true;
                  $ts = strtotime("$d $time");
                  $te = $ts + ($dur * 60);
                  for ($x = $ts; $x < $te; $x += (SALON_STEP * 60)) {
                    $kk = date('H:i', $x);
                    if (!salon_between($kk, $shift['s'], $shift['e']) || !empty($booked[$d][$kk][$u->ID])) {
                      $ok = false; break;
                    }
                  }
                  if ($ok) { $available = true; $chosen_staff = $u->ID; break; }
                }
              }
              ?>
              <td class="cell <?= $available ? 'available' : 'off' ?>">
                <?php if ($available): ?>
                  <button class="slot-btn" 
                          data-date="<?= esc_attr($d) ?>" 
                          data-time="<?= esc_attr($time) ?>" 
                          data-staff="<?= (int)$chosen_staff ?>">○</button>
                <?php else: ?>
                  ×
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php return ob_get_clean();
}


/** Ajax：カレンダー描画 */
add_action('wp_ajax_salon_render_calendar_front','salon_render_calendar_front');
add_action('wp_ajax_nopriv_salon_render_calendar_front','salon_render_calendar_front');

function salon_render_calendar_front(){
  // ▼ weekパラメータの受け取りとログ出力
  $week = isset($_POST['week']) ? intval($_POST['week']) : 0;
  $menu  = sanitize_text_field($_POST['menu'] ?? '');
  $staff = intval($_POST['staff'] ?? 0);
  if(!$menu) wp_die('メニュー未選択');

  // ▼ カレンダーHTML生成関数に week を渡すように修正
  echo salon_generate_calendar_html($menu, $staff, $week);
  wp_die();
}


/** Ajax：予約登録（フロント） */
add_action('wp_ajax_nopriv_salon_customer_reserve','salon_customer_reserve');
add_action('wp_ajax_salon_customer_reserve','salon_customer_reserve');
function salon_customer_reserve(){
  $name  = sanitize_text_field($_POST['res_name']  ?? '');
  $email = sanitize_email($_POST['res_email']      ?? '');
  $tel   = sanitize_text_field($_POST['res_tel']   ?? '');
  $menu  = sanitize_text_field($_POST['res_menu']  ?? '');
  $date  = sanitize_text_field($_POST['res_date']  ?? '');
  $time  = sanitize_text_field($_POST['res_time']  ?? '');
  $staff = intval($_POST['res_staff'] ?? 0); // 0=指名なし

  // ✅メール以外が必須
  if(!$name || !$tel || !$menu || !$date || !$time){
    wp_send_json(['ok'=>false,'msg'=>'必須項目を入力してください。']);
  }

  if($staff===0){
    $staff = rsrv_pick_staff_for($menu,$date,$time);
    if(!$staff){ wp_send_json(['ok'=>false,'msg'=>'該当の時間に対応可能なスタッフが見つかりませんでした。']); }
  }

  $settings = get_user_meta($staff,'salon_menu_settings',true) ?: [];
  $duration = intval($settings[$menu]['duration'] ?? rsrv_menu_default_duration($menu));
  $new_start = strtotime("$date $time"); $new_end = $new_start + ($duration*60);

  // ✅予約重複チェック
  $existing = get_posts([
    'post_type'=>'reservation','post_status'=>'any','numberposts'=>-1,
    'meta_query'=>[
      ['key'=>'res_staff','value'=>$staff,'compare'=>'='],
      ['key'=>'res_date','value'=>$date,'compare'=>'='],
    ],
  ]);
  foreach($existing as $p){
    $ex_t = get_post_meta($p->ID,'res_time',true);
    $ex_m = get_post_meta($p->ID,'res_menu',true);
    $ex_settings = get_user_meta($staff,'salon_menu_settings',true) ?: [];
    $ex_dur = intval($ex_settings[$ex_m]['duration'] ?? rsrv_menu_default_duration($ex_m));
    $ex_s = strtotime("$date $ex_t"); $ex_e = $ex_s + ($ex_dur*60);
    if($new_start < $ex_e && $new_end > $ex_s){
      wp_send_json(['ok'=>false,'msg'=>'選択した時間帯は既に予約が入っています。']);
    }
  }

  // ✅予約登録
  $post_id = wp_insert_post([
    'post_type'=>'reservation','post_status'=>'publish',
    'post_title'=>sprintf('%s %s / %s（%s）',$date,$time,$name,$menu),
  ]);
  if(!$post_id){ wp_send_json(['ok'=>false,'msg'=>'保存中にエラーが発生しました。']); }

  update_post_meta($post_id,'res_name',$name);
  update_post_meta($post_id,'res_tel',$tel);
  update_post_meta($post_id,'res_email',$email);
  update_post_meta($post_id,'res_menu',$menu);
  update_post_meta($post_id,'res_date',$date);
  update_post_meta($post_id,'res_time',$time);
  update_post_meta($post_id,'res_staff',$staff);
  update_post_meta($post_id,'res_datetime',"$date $time:00");

  // ✅メール通知
  $admin_email = get_option('admin_email');
  $staff_user  = get_userdata($staff);
  $staff_name  = $staff_user ? $staff_user->display_name : '指名なし（自動割当）';

  // 管理者通知（常に送信）
  $subject_admin = "【新規予約】$name 様より予約がありました";
  $body_admin = "以下のご予約を受け付けました。\n\n".
                "■日時：$date $time\n".
                "■メニュー：$menu\n".
                "■担当：$staff_name\n".
                "■お名前：$name\n".
                "■メール：$email\n".
                "■電話：$tel\n";
  wp_mail($admin_email, $subject_admin, $body_admin);

  // ✅お客様メールは「メール記入時のみ送信」
  if($email && is_email($email)) {
    $subject_user = "【予約完了】ご予約ありがとうございます";
    $body_user = "{$name} 様\n\n".
                 "ご予約ありがとうございます。以下の内容で承りました。\n\n".
                 "■日時：$date $time\n".
                 "■メニュー：$menu\n".
                 "■担当：$staff_name\n\n".
                 "当日お待ちしております。";
    wp_mail($email, $subject_user, $body_user);
  }

  wp_send_json(['ok'=>true,'msg'=>'ご予約を受け付けました！']);
}


/* === メールアドレスカラム追加 === */
add_filter('manage_edit-reservation_columns', function ($columns) {

  // 表の並びを整理
  $new = [];
  foreach($columns as $key =>$label){
      if($key === 'res_tel'){
          // 電話番号の後に「メール」を挿入
          $new['res_email'] = 'メール';
      }
      $new[$key] = $label;
  }
  return $new;
});

/* === メール欄の表示処理 === */
add_action('manage_reservation_posts_custom_column', function ($column, $post_id) {
  if ($column === 'res_email') {
      $email = get_post_meta($post_id, 'res_email', true);
      if ($email) {
          echo '<a href="mailto:'.esc_attr($email).'">'.esc_html($email).'</a>';
      } else {
          echo 'ー';
      }
  }
}, 10, 2);



/* ============================================
 * Ajax: 公開ページ用「確認カレンダー（読み取り専用）」
 * - 本日が一番左
 * - 週ナビ（week=±n）
 * - その日の出勤スタッフのみ列表示
 * - ○=シフト内＆未予約 / ×=予約あり / —=シフト外
 * - クリック不可
 * ============================================ */
add_action('wp_ajax_salon_render_calendar_public_readonly','salon_render_calendar_public_readonly');
add_action('wp_ajax_nopriv_salon_render_calendar_public_readonly','salon_render_calendar_public_readonly');

function salon_render_calendar_public_readonly(){
  $week = intval($_POST['week'] ?? 0);
  echo salon_generate_readonly_calendar($week);
  wp_die();
}

function salon_generate_readonly_calendar($week_shift = 0){
  date_default_timezone_set('Asia/Tokyo');

  // 週の開始：本日（質問Q1の要望どおり「現在が一番左」）
  $start_base = strtotime('today');
  if($week_shift !== 0){
    $start_base = strtotime(($week_shift>0?"+$week_shift week":"$week_shift week"), $start_base);
  }

  // 7日ぶん
  $week_dates = [];
  for($i=0;$i<7;$i++) $week_dates[] = date('Y-m-d', strtotime("+$i day", $start_base));

  $times = salon_time_slots();

  // スタッフ一覧
  $staff_users = salon_get_staff_users(); // fields: ID, display_name, user_login
  $staff_name_map = [];
  foreach($staff_users as $u) $staff_name_map[$u->ID] = $u->display_name;

  // 1週間ぶんの「その日に出勤しているスタッフリスト」
  $day_staffs = []; // [$date] = [staff_id,...]
  foreach($week_dates as $d){
    $ym  = date('Ym', strtotime($d));
    $day = (int)date('d', strtotime($d));
    $list = [];
    foreach($staff_users as $u){
      $raw  = get_user_meta($u->ID, salon_shift_meta_key($ym), true);
      $norm = salon_normalize_shift_meta((array)$raw, $ym);
      if(!empty($norm[$day])) $list[] = $u->ID;
    }
    $day_staffs[$d] = $list; // 出勤者のみ
  }

  // 予約を集計（施術時間ぶんブロック）
  $booked = []; // [$date][$H:i][$staff_id] = true
  $q = new WP_Query([
    'post_type'      => 'reservation',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'meta_query'     => [
      ['key'=>'res_date','value'=>$week_dates,'compare'=>'IN']
    ]
  ]);
  if($q->have_posts()){
    while($q->have_posts()){ $q->the_post();
      $pid  = get_the_ID();
      $date = get_post_meta($pid,'res_date',true);
      $time = get_post_meta($pid,'res_time',true);
      $stfv = get_post_meta($pid,'res_staff',true);
      if(!$date || !$time) continue;

      // スタッフID化（後方互換：display_name保存だった時のため）
      $sid = 0;
      if(is_numeric($stfv)) $sid = intval($stfv);
      else {
        foreach($staff_users as $u){ if($u->display_name === $stfv){ $sid=$u->ID; break; } }
      }
      if(!$sid) continue;

      $menu = get_post_meta($pid,'res_menu',true);
      $settings = get_user_meta($sid,'salon_menu_settings',true) ?: [];
      $dur = intval($settings[$menu]['duration'] ?? rsrv_menu_default_duration($menu));

      $ts = strtotime("$date $time"); $te = $ts + ($dur * 60);
      for($t=$ts; $t<$te; $t += (SALON_STEP*60)){
        $k = date('H:i', $t);
        $booked[$date][$k][$sid] = true;
      }
    }
    wp_reset_postdata();
  }

  // 当日ごとのシフト（範囲参照用）
  $shift_map = []; // [$sid][$date] = ['s'=>'HH:MM','e'=>'HH:MM']
  foreach($staff_users as $u){
    $shift_map[$u->ID] = [];
    $ym_keys = [];
    foreach($week_dates as $d) $ym_keys[ date('Ym', strtotime($d)) ] = true;
    foreach(array_keys($ym_keys) as $ym){
      $raw = get_user_meta($u->ID, salon_shift_meta_key($ym), true);
      $norm= salon_normalize_shift_meta((array)$raw, $ym);
      $y=(int)substr($ym,0,4); $m=(int)substr($ym,4,2);
      foreach($norm as $day=>$pair){
        $date = sprintf('%04d-%02d-%02d', $y, $m, (int)$day);
        $shift_map[$u->ID][$date] = $pair;
      }
    }
  }

  // 出力
  $week_days = ['日','月','火','水','木','金','土'];
  ob_start(); ?>
  <table class="calendar-table">
    <thead>
    <tr>
      <th class="time-col"></th>
      <?php foreach($week_dates as $d): $w = $week_days[(int)date('w', strtotime($d))]; $cnt = max(1, count($day_staffs[$d])); ?>
        <th class="day-group" colspan="<?php echo (int)$cnt; ?>">
          <?php echo esc_html(date('n/j', strtotime($d))); ?>（<?php echo esc_html($w); ?>）
        </th>
      <?php endforeach; ?>
    </tr>
    <tr>
      <th class="time-col"></th>
      <?php foreach($week_dates as $d):
        $cols = $day_staffs[$d];
        if(!$cols){ echo '<th class="staff-col">出勤なし</th>'; continue; }
        $last = end($cols);
        foreach($cols as $sid){
          $cls = ($sid===$last)?'staff-col sep':'staff-col';
          echo '<th class="'.$cls.'">'. esc_html($staff_name_map[$sid] ?? '—') .'</th>';
        }
      endforeach; ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach($times as $time): ?>
      <tr>
        <th class="time-col"><?php echo esc_html($time); ?></th>
        <?php foreach($week_dates as $d):
          $cols = $day_staffs[$d];
          if(!$cols){
            // 出勤者ゼロ日のダミー1列
            echo '<td class="cell off">—</td>';
            continue;
          }
          $last = end($cols);
          foreach($cols as $sid):
            $shift = $shift_map[$sid][$d] ?? null;
            $within= $shift ? salon_between($time, $shift['s'], $shift['e']) : false;
            $isBooked = !empty($booked[$d][$time][$sid]);

            $cls = 'off'; $mark='—';
            if($within){
              if($isBooked){ $cls='booked'; $mark='×'; }
              else { $cls='available'; $mark='○'; }
            }
            $sep = ($sid===$last) ? ' sep' : '';
            echo '<td class="cell '.$cls.$sep.'">'. $mark .'</td>';
          endforeach;
        endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php
  return ob_get_clean();
}



// functions.php
add_action('wp_enqueue_scripts', function() {
  // すでに読み込んでいる theme-script に admin-ajax.php のURLを渡す
  wp_localize_script('theme-script', 'salon_ajax', [
    'url' => admin_url('admin-ajax.php'),
  ]);
});
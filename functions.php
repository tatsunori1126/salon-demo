<?php
/***********************************************************
 * 1️⃣ テーマ基本設定・パフォーマンス最適化
 ***********************************************************/

/** テーマサポート */
add_theme_support('html5', ['comment-list','comment-form','search-form','gallery','caption']);
add_theme_support('title-tag');
add_theme_support('post-thumbnails');
add_theme_support('automatic-feed-links');
add_theme_support('custom-logo');
add_theme_support('wp-block-styles');
add_theme_support('responsive-embeds');
add_theme_support('align-wide');

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
  wp_enqueue_style('fontawesome','https://use.fontawesome.com/releases/v6.6.0/css/all.css',[],null);
  wp_enqueue_style('google-fonts','https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100..900&display=swap',[],null);

  // JS
  wp_enqueue_script('jquery');
  wp_enqueue_script('swiper','https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js',[],null,true);
  wp_enqueue_script('gsap','https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js',[],null,true);
  wp_enqueue_script('gsap-scrolltrigger','https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js',['gsap'],null,true);
  // JS読込
wp_enqueue_script('salon-script', get_template_directory_uri().'/js/script.min.js',['jquery'],filemtime(get_template_directory().'/js/script.min.js'),true);

// AjaxURL共有
wp_localize_script('salon-script','salon_ajax',[
  'url'   => admin_url('admin-ajax.php'),
  'nonce' => wp_create_nonce('salon_reservation_nonce')
]);
}
add_action('wp_enqueue_scripts','salon_enqueue_assets');

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



/***********************************************************
 * 2️⃣ サロン基盤機能（営業時間・共通関数・ロール）
 ***********************************************************/

/** 店舗設定取得 */
function salon_get_store_settings(){
  $defaults = [
    'open_time'  => '09:00',
    'close_time' => '19:30',
    'time_step'  => 30,
    'holidays'   => [],
    'menus'      => []
  ];
  $opt = get_option('salon_store_settings',[]);
  return wp_parse_args($opt,$defaults);
}

/** 営業時間→タイムスロット生成 */
function salon_time_slots($from=null,$to=null,$step=null){
  $s = salon_get_store_settings();
  $from = $from ?: $s['open_time'];
  $to   = $to   ?: $s['close_time'];
  $step = $step ?: intval($s['time_step']);
  $out = [];
  $t = strtotime($from);
  $end = strtotime($to);
  while($t <= $end){ $out[] = date('H:i',$t); $t += $step*60; }
  return $out;
}

/** 時刻文字列→分換算 */
function salon_time_to_min($hhmm){
  if(!$hhmm) return null;
  [$h,$m] = array_map('intval',explode(':',$hhmm));
  return $h*60 + $m;
}

/** 時刻範囲内判定 */
function salon_between($time,$start,$end){
  $t=salon_time_to_min($time); $s=salon_time_to_min($start); $e=salon_time_to_min($end);
  if($t===null||$s===null||$e===null) return false;
  return ($t >= $s) && ($t < $e);
}

/** 出勤メタキー生成 */
function salon_shift_meta_key($ym){ return 'salon_shift_'.$ym; }

/** シフトメタ正規化 */
function salon_normalize_shift_meta($raw,$ym){
  if(!$raw) return [];
  if(array_values($raw)===$raw && is_int(reset($raw))){
    $store = salon_get_store_settings();
    $open=$store['open_time']; $close=$store['close_time']; $out=[];
    foreach((array)$raw as $d){ $out[$d]=['s'=>$open,'e'=>$close]; }
    return $out;
  }
  $out=[];
  foreach((array)$raw as $day=>$pair){
    $s=$pair['s']??''; $e=$pair['e']??'';
    if($s && $e && salon_time_to_min($e) > salon_time_to_min($s)){
      $out[(int)$day]=['s'=>$s,'e'=>$e];
    }
  }
  return $out;
}

/** ロール登録＆予約CPT */
add_action('init',function(){
  register_post_type('reservation',[
    'label'=>'予約','public'=>false,'show_ui'=>true,'supports'=>[],
    'menu_icon'=>'dashicons-calendar-alt','show_in_rest'=>false,
  ]);
  if(!get_role('salon_staff')) add_role('salon_staff','サロンスタッフ',['read'=>true]);
});

/** スタッフ一覧取得 */
function salon_get_staff_users(){
  return get_users([
    'role'=>'salon_staff',
    'orderby'=>'display_name',
    'order'=>'ASC',
    'fields'=>['ID','display_name','user_login']
  ]);
}



/***********************************************************
 * 3️⃣ 店舗設定（営業時間・定休日・メニュー設定）
 ***********************************************************/

add_action('admin_menu',function(){
  add_menu_page(
    '店舗設定',
    '店舗設定',
    'manage_options',
    'salon-store-settings',
    'salon_render_store_settings_page',
    'dashicons-store',
    25
  );
});

/** 店舗設定ページ本体 */
function salon_render_store_settings_page(){
  if(!current_user_can('manage_options')) return;

  // 保存処理
  if(isset($_POST['salon_store_save'])){
    check_admin_referer('salon_store_save_action');

    $open = sanitize_text_field($_POST['open_time'] ?? '');
    $close= sanitize_text_field($_POST['close_time'] ?? '');
    $step = intval($_POST['time_step'] ?? 30);
    $holidays = array_map('sanitize_text_field', $_POST['holidays'] ?? []);

    // メニュー保存処理
    $menu_names  = $_POST['menu_name'] ?? [];
    $menu_prices = $_POST['menu_price'] ?? [];
    $menus=[];
    foreach($menu_names as $i=>$name){
      $name = trim(sanitize_text_field($name));
      if($name==='') continue;
      $menus[]=['name'=>$name,'price'=>intval($menu_prices[$i]??0)];
    }

    $data=[
      'open_time'=>$open,'close_time'=>$close,'time_step'=>$step,
      'holidays'=>$holidays,'menus'=>$menus,
      'nomination_fee' => intval($_POST['nomination_fee'] ?? 0),
    ];
    update_option('salon_store_settings',$data);

    echo '<div class="notice notice-success is-dismissible"><p>店舗設定を保存しました ✅</p></div>';
  }

  $settings=salon_get_store_settings();
  $weekdays=['日','月','火','水','木','金','土'];
  ?>
  <div class="wrap">
    <h1>店舗設定</h1>
    <form method="post">
      <?php wp_nonce_field('salon_store_save_action'); ?>
      <table class="form-table">
        <tr>
          <th>営業時間</th>
          <td>
            <input type="time" name="open_time" value="<?=esc_attr($settings['open_time']);?>"> 〜
            <input type="time" name="close_time" value="<?=esc_attr($settings['close_time']);?>">
          </td>
        </tr>
        <tr>
          <th>予約間隔</th>
          <td>
            <select name="time_step">
              <?php foreach([15,30,45,60] as $v): ?>
                <option value="<?=$v?>" <?=selected($settings['time_step'],$v,false)?>><?=$v?>分刻み</option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
        <tr>
          <th>定休日</th>
          <td>
            <?php foreach($weekdays as $i=>$w): ?>
              <label><input type="checkbox" name="holidays[]" value="<?=$i?>" <?=checked(in_array((string)$i,(array)$settings['holidays'],true),true,false)?>><?=$w?>曜</label>
            <?php endforeach; ?>
          </td>
        </tr>

        <tr>
          <th>指名料</th>
          <td>
            <input type="number" name="nomination_fee" value="<?=esc_attr($settings['nomination_fee'] ?? 0);?>" min="0" step="100"> 円
            <p class="description">※0円の場合は自動的に指名料なし扱いになります</p>
          </td>
        </tr>

        <tr>
          <th>メニュー設定</th>
          <td>
            <div id="menu-list">
              <?php if(!empty($settings['menus'])): foreach($settings['menus'] as $m): ?>
                <p><input type="text" name="menu_name[]" value="<?=esc_attr($m['name']);?>" placeholder="メニュー名">
                   <input type="number" name="menu_price[]" value="<?=esc_attr($m['price']);?>" placeholder="金額（円）">
                   <button type="button" class="button remove-menu">削除</button></p>
              <?php endforeach; else: ?>
                <p><input type="text" name="menu_name[]" placeholder="メニュー名">
                   <input type="number" name="menu_price[]" placeholder="金額（円）">
                   <button type="button" class="button remove-menu">削除</button></p>
              <?php endif; ?>
            </div>
            <button type="button" class="button" id="add-menu-row">＋ メニュー追加</button>

            <script>
            jQuery(function($){
              $('#add-menu-row').on('click',()=>$('#menu-list').append(
                '<p><input type="text" name="menu_name[]" placeholder="メニュー名"> '+
                '<input type="number" name="menu_price[]" placeholder="金額（円）"> '+
                '<button type="button" class="button remove-menu">削除</button></p>'
              ));
              $(document).on('click','.remove-menu',function(){ $(this).closest('p').remove(); });
            });
            </script>
          </td>
        </tr>
      </table>
      <?php submit_button('保存','primary','salon_store_save'); ?>
    </form>
  </div>
  <?php
}
/***********************************************************
 * 4️⃣ 出勤管理（管理画面・保存）
 ***********************************************************/
add_action('admin_menu', function(){
  add_menu_page(
    '出勤管理',
    '出勤管理',
    'read',
    'salon-shifts',
    'salon_render_shifts_page',
    'dashicons-groups',
    26
  );
});

/** 出勤管理ページ表示 */
function salon_render_shifts_page() {
  $current = wp_get_current_user();
  $is_admin = in_array('administrator', (array)$current->roles, true);
  $uid = $is_admin ? intval($_GET['user'] ?? $_POST['user'] ?? $current->ID) : $current->ID;
  $ym  = preg_replace('/[^0-9]/', '', ($_GET['ym'] ?? $_POST['ym'] ?? date('Ym')));

  // ✅ 保存処理
  if (isset($_POST['save_shift'])) {
    check_admin_referer('save_shift_' . $ym);
    if ($is_admin && !empty($_POST['user'])) $uid = intval($_POST['user']);

    $starts = $_POST['start'] ?? [];
    $ends   = $_POST['end'] ?? [];

    $year  = (int)substr($ym, 0, 4);
    $month = (int)substr($ym, 4, 2);
    $days_in_month = date('t', strtotime("{$year}-{$month}-01"));

    $save = [];
    for ($d = 1; $d <= $days_in_month; $d++) {
      $s = sanitize_text_field($starts[$d] ?? '');
      $e = sanitize_text_field($ends[$d] ?? '');
      $save[$d] = [
        's' => $s,
        'e' => $e,
        'work' => ($s && $e) ? 1 : 0,
      ];
    }

    $meta_key = salon_shift_meta_key($ym);
    update_user_meta($uid, $meta_key, $save);

    echo "<script>location.href='?page=salon-shifts&user={$uid}&ym={$ym}&saved=1';</script>";
    exit;
  }

  // ✅ 出勤データ読み込み（当月キーを参照）
  $meta_key = salon_shift_meta_key($ym);
  $shift = get_user_meta($uid, $meta_key, true);

  // 後方互換：旧形式（salon_staff_info）を参照
  if (empty($shift)) {
    $shift = get_user_meta($uid, 'salon_staff_info', true);
  }

  // 🔧 フォーマット正規化（s/e → start/end）
  $fixed_shift = [];
  foreach ((array)$shift as $k => $v) {
    if (isset($v['s']) || isset($v['e'])) {
      $fixed_shift[(int)$k] = [
        'start' => $v['s'] ?? '',
        'end'   => $v['e'] ?? ''
      ];
    } elseif (isset($v['start']) || isset($v['end'])) {
      $fixed_shift[(int)$k] = $v;
    }
  }
  $shift = $fixed_shift;

  // ===== カレンダー描画 =====
  $times = salon_time_slots();
  $year  = (int)substr($ym, 0, 4);
  $month = (int)substr($ym, 4, 2);
  $days  = (int)date('t', strtotime("$year-$month-01"));

  echo '<div class="wrap"><h1>出勤管理</h1>';
  if (!empty($_GET['saved'])) echo '<div class="notice notice-success"><p>保存しました ✅</p></div>';

  echo '<form method="get"><input type="hidden" name="page" value="salon-shifts">';
  if ($is_admin) {
    echo 'スタッフ：<select name="user">';
    foreach (salon_get_staff_users() as $u) {
      printf('<option value="%d"%s>%s</option>', $u->ID, selected($uid, $u->ID, false), esc_html($u->display_name));
    }
    echo '</select><button class="button">変更</button>';
  } else {
    echo '<strong>' . esc_html($current->display_name) . '</strong>';
  }
  echo '</form>';

  // 月ナビゲーション
  $dt = DateTime::createFromFormat('Ym', $ym);
  $prev = $dt->modify('-1 month')->format('Ym');
  $next = DateTime::createFromFormat('Ym', $ym)->modify('+1 month')->format('Ym');

  printf('<p><a class="button" href="?page=salon-shifts&user=%d&ym=%s">前月</a> ', $uid, $prev);
  printf('<a class="button" href="?page=salon-shifts&user=%d&ym=%s">今月</a> ', $uid, date('Ym'));
  printf('<a class="button" href="?page=salon-shifts&user=%d&ym=%s">次月</a></p>', $uid, $next);

  echo '<form method="post">';
  wp_nonce_field('save_shift_' . $ym);
  echo '<input type="hidden" name="user" value="' . $uid . '">';
  echo '<input type="hidden" name="ym" value="' . $ym . '">';
  echo "<h2>{$year}年 {$month}月</h2><div class='salon-shift-grid'>";

  // ===== 日ごとの行を描画 =====
  for ($d = 1; $d <= $days; $d++) {
    $w = (int)date('w', strtotime("$year-$month-$d"));
    $jp = ['日','月','火','水','木','金','土'][$w];
    $cur = $shift[$d] ?? ['start' => '', 'end' => ''];

    echo "<div class='salon-shift-cell'><div class='salon-shift-date'>{$d}日（{$jp}）</div>";

    // 開始時間
    echo "<div class='time-row'><label>開始</label><select name='start[{$d}]'><option value=''>—</option>";
    foreach ($times as $t) {
      printf('<option value="%s"%s>%s</option>', esc_attr($t), selected($cur['start'], $t, false), $t);
    }
    echo "</select></div>";

    // 終了時間
    echo "<div class='time-row'><label>終了</label><select name='end[{$d}]'><option value=''>—</option>";
    foreach ($times as $t) {
      printf('<option value="%s"%s>%s</option>', esc_attr($t), selected($cur['end'], $t, false), $t);
    }
    echo "</select></div>";

    echo "</div>";
  }

  echo '</div>';
  submit_button('保存', 'primary', 'save_shift');
  echo '</form></div>';
}




/***********************************************************
 * 5️⃣ 予約管理（CPT・メタ保存・一覧・通知）
 ***********************************************************/

/** メタボックス追加 */
add_action('add_meta_boxes', function(){
  add_meta_box('reservation_fields','予約情報','salon_reservation_mb','reservation','normal','high');
});

/** メタボックスHTML */
/** メタボックスHTML（出勤・メニュー対応可のみ表示） */
function salon_reservation_mb($post){
  wp_nonce_field('salon_reservation_save','salon_reservation_nonce');
  $meta=['name','tel','email','date','time','menu','staff'];
  foreach($meta as $m){ $$m = get_post_meta($post->ID, 'res_'.$m, true); }

  $store  = salon_get_store_settings();
  $menus  = $store['menus'] ?? [];
  $staffs = salon_get_staff_users();

  // --- 日付・時間・メニューが揃っているときだけ条件判定 ---
  $filtered_staffs = [];
  if ($date && $time && $menu) {
    foreach ($staffs as $s) {
      $uid = $s->ID;

      // ① メニュー対応可？
      $menu_settings = get_user_meta($uid, 'salon_menu_settings', true) ?: [];
      $enabled = !empty($menu_settings[$menu]['enabled']);

      // ② 出勤中？
      $available = salon_is_staff_available($uid, $date, $time);

      if ($enabled && $available) {
        $filtered_staffs[] = $s;
      }
    }
  } else {
    // 日時が未選択のときは全員（既存動作維持）
    $filtered_staffs = $staffs;
  }

  ?>
  <table class="form-table">
    <tr><th>お名前*</th><td><input name="res_name" type="text" value="<?=esc_attr($name)?>" required></td></tr>
    <tr><th>電話*</th><td><input name="res_tel" type="text" value="<?=esc_attr($tel)?>" required></td></tr>
    <tr><th>メール</th><td><input name="res_email" type="email" value="<?=esc_attr($email)?>"></td></tr>
    <tr><th>日付*</th><td><input name="res_date" type="date" value="<?=esc_attr($date)?>" required></td></tr>
    <tr><th>時間*</th><td><input name="res_time" type="time" value="<?=esc_attr($time)?>" required></td></tr>
    <tr><th>メニュー*</th>
      <td><select name="res_menu" required><option value="">— 選択 —</option>
        <?php foreach($menus as $m): ?>
          <option value="<?=esc_attr($m['name'])?>" <?=selected($menu,$m['name'],false)?>><?=esc_html($m['name'])?></option>
        <?php endforeach; ?>
      </select></td>
    </tr>
    <tr><th>担当*</th>
      <td>
        <select name="res_staff" required>
          <option value="">— 選択 —</option>
          <option value="0" <?=selected($staff,'0',false)?>>指名なし（自動割当）</option>
          <?php if (!empty($filtered_staffs)): ?>
            <?php foreach($filtered_staffs as $s): ?>
              <option value="<?=$s->ID?>" <?=selected($staff,$s->ID,false)?>><?=$s->display_name?></option>
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


/** 保存処理 */
add_action('save_post_reservation', function($post_id){
  // ✅ 無限ループ防止フラグ
  if (defined('SALON_SAVE_RUNNING')) return;
  define('SALON_SAVE_RUNNING', true);

  // ✅ nonce確認
  if(!isset($_POST['salon_reservation_nonce']) || !wp_verify_nonce($_POST['salon_reservation_nonce'],'salon_reservation_save')) return;
  if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

  $fields=['name','tel','email','date','time','menu'];
  foreach($fields as $f){
    update_post_meta($post_id, 'res_'.$f, sanitize_text_field($_POST['res_'.$f]??''));
  }

  $staff=intval($_POST['res_staff']??0);
  update_post_meta($post_id,'res_staff',$staff);
  update_post_meta($post_id,'res_datetime',($_POST['res_date']??'').' '.($_POST['res_time']??'').':00');

  // ✅ タイトル更新（再帰防止つき）
  remove_action('save_post_reservation', __FUNCTION__);
  wp_update_post([
    'ID'=>$post_id,
    'post_title'=>sprintf('%s %s / %s（%s）',
      $_POST['res_date'],
      $_POST['res_time'],
      $_POST['res_name'],
      $_POST['res_menu']
    )
  ]);
  add_action('save_post_reservation', __FUNCTION__);
},10,1);

// =========================================================
// 担当変更の安全保存（メモリリーク完全防止版）
// =========================================================
add_action('save_post_reservation', function($post_id) {
  // 自動保存・ゴミ箱・権限チェック回避
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;

  // POSTデータが存在しない場合は終了
  if (!isset($_POST['res_staff'])) return;

  // 現在の担当と変更後の担当を比較
  $old_staff = intval(get_post_meta($post_id, '_old_res_staff', true));
  $new_staff = intval($_POST['res_staff']);

  // 担当が変わったときのみ処理
  if ($new_staff !== $old_staff) {
    error_log("=== 担当変更 === post_id={$post_id} old={$old_staff} new={$new_staff}");
    update_post_meta($post_id, 'res_staff', $new_staff);
    update_post_meta($post_id, '_old_res_staff', $new_staff);
  }
}, 20);



/** 管理画面リストカスタマイズ */
add_filter('manage_edit-reservation_columns', function($cols) {
  return [
    'cb'          => '<input type="checkbox">',
    'res_datetime'=> '日時',
    'res_name'    => 'お名前',
    'res_tel'     => '電話',
    'res_email'   => 'メール',
    'res_menu'    => 'メニュー',
    'res_staff'   => '担当',
    'res_actions' => '操作', // ← 新しく追加（ボタン列）
    'date'        => '登録日',
  ];
});

add_action('manage_reservation_posts_custom_column', function($col, $id) {
  $v = get_post_meta($id, $col, true);

  switch ($col) {

    // ▼ 電話番号
    case 'res_tel':
      if ($v) {
        echo '<a href="tel:' . esc_attr($v) . '">' . esc_html($v) . '</a>';
      } else {
        echo ''; // ← 全角「ー」削除、空欄に
      }
      break;

    // ▼ メールアドレス
    case 'res_email':
      if ($v) {
        echo '<a href="mailto:' . esc_attr($v) . '">' . esc_html($v) . '</a>';
      } else {
        echo ''; // ← 全角「ー」削除、空欄に
      }
      break;

    // ▼ 担当スタッフ
    case 'res_staff':
      $v = intval($v);
      $u = $v ? get_userdata($v) : null;
      $auto = intval(get_post_meta($id, 'res_auto_assigned', true));
      if ($u) {
        echo esc_html($u->display_name);
        if ($auto) echo '（指名なし）';
      } else {
        echo '指名なし'; // ← 全角「ー」削除済
      }
      break;

    // ▼ 操作欄
    case 'res_actions':
      $edit_url  = get_edit_post_link($id);
      $trash_url = get_delete_post_link($id);
      echo '<div style="display:flex;gap:6px;">';
      echo '<a href="' . esc_url($edit_url) . '" class="button button-small">編集</a>';
      echo '<a href="' . esc_url($trash_url) . '" class="button button-small" style="color:#a00;">削除</a>';
      echo '</div>';
      break;

    // ▼ その他（メニュー・日時など）
    default:
      echo esc_html($v ?: ''); // ← 全角「ー」削除済
  }
}, 10, 2);



/***********************************************************
 * 6️⃣ スタッフ設定（施術メニュー対応可・施術時間）
 ***********************************************************/

/** プロフィール画面に「施術メニュー設定」追加 */
function salon_staff_menu_settings_fields($user){
  if (!in_array('salon_staff',(array)$user->roles) && !current_user_can('manage_options')) return;

  $store = salon_get_store_settings();
  $menus = $store['menus'] ?? [];
  $saved = get_user_meta($user->ID,'salon_menu_settings',true) ?: [];

  echo '<h2>施術メニュー設定</h2>';
  if(empty($menus)){
    echo '<p style="color:#666;">※ 店舗設定でメニューを追加してください。</p>';
    return;
  }

  echo '<table class="form-table">';
  foreach($menus as $m){
    $key = $m['name'];
    $price = intval($m['price']);
    $enabled = $saved[$key]['enabled'] ?? 0;
    $duration = $saved[$key]['duration'] ?? 60;
    echo '<tr>';
    echo '<th><label>'.esc_html($key).'</label><br><small>¥'.number_format($price).'</small></th>';
    echo '<td>';
    echo '<label><input type="checkbox" name="salon_menu_enabled['.esc_attr($key).']" value="1" '.checked($enabled,1,false).'> 対応可</label> ';
    echo '<select name="salon_menu_duration['.esc_attr($key).']">';
    for($m=30;$m<=180;$m+=15){
      echo '<option value="'.$m.'" '.selected($duration,$m,false).'>'.$m.'分</option>';
    }
    echo '</select>';
    echo '</td></tr>';
  }
  echo '</table>';
}
add_action('show_user_profile','salon_staff_menu_settings_fields');
add_action('edit_user_profile','salon_staff_menu_settings_fields');

/** 保存処理 */
function salon_save_staff_menu_settings($user_id){
  if(!current_user_can('edit_user',$user_id)) return;

  $enabled=$_POST['salon_menu_enabled']??[];
  $duration=$_POST['salon_menu_duration']??[];
  $store = salon_get_store_settings();
  $menus = $store['menus'] ?? [];
  $save=[];
  foreach($menus as $m){
    $key=$m['name'];
    $save[$key]=[
      'enabled'=>isset($enabled[$key])?1:0,
      'duration'=>isset($duration[$key])?intval($duration[$key]):60
    ];
  }
  update_user_meta($user_id,'salon_menu_settings',$save);
}
add_action('personal_options_update','salon_save_staff_menu_settings');
add_action('edit_user_profile_update','salon_save_staff_menu_settings');



/***********************************************************
 * 7️⃣ フロント機能（HotPepper風カレンダー・Ajax）
 ***********************************************************/

/** カレンダー（指名なし・スタッフ切替式） */
function salon_generate_calendar_html_wrapper($menu_key,$week=0){
  $staffs = salon_get_staff_users();
  ob_start(); ?>
  <div class="salon-calendar-wrapper" data-menu="<?=esc_attr($menu_key)?>" data-week="<?=esc_attr($week)?>">
    <h3 class="cal-title">空き状況（1週間）</h3>
    <div class="salon-calendar-tabs">
      <button class="tab active" data-staff="0">指名なし</button>
      <?php foreach($staffs as $s): ?>
        <button class="tab" data-staff="<?=$s->ID?>"><?=esc_html($s->display_name)?></button>
      <?php endforeach; ?>
    </div>
    <div id="salon-calendar-content">
      <?=salon_generate_calendar_html_all_staff($menu_key,$week);?>
    </div>
  </div>
  <?php
  return ob_get_clean();
}
/** 指名なし：全スタッフ統合カレンダー */
function salon_generate_calendar_html_all_staff($menu_key, $week = 0) {
  date_default_timezone_set('Asia/Tokyo');
  $store     = salon_get_store_settings();
  $holidays  = $store['holidays'] ?? [];
  $time_step = intval($store['time_step'] ?? 30);

  $today = strtotime('today');
  $start = strtotime("+".(7 * intval($week))." days", $today);
  $week_dates = [];
  for ($i = 0; $i < 7; $i++) $week_dates[] = date('Y-m-d', strtotime("+$i day", $start));

  $times  = salon_time_slots();
  $staffs = salon_get_staff_users(); // 全スタッフ取得

  ob_start(); ?>
  <div class="salon-calendar">
    <h3 class="cal-title">空き状況（1週間）</h3>
    <div class="cal-legend">
      <span>○：予約可</span>
      <span>×：予約済</span>
      <span>—：出勤なし</span>
    </div>

    <table class="cal-table">
      <thead>
        <tr>
          <th>時間</th>
          <?php foreach ($week_dates as $d): ?>
            <th><?php echo date('n/j', strtotime($d)); ?>
              (<?php echo ['日','月','火','水','木','金','土'][date('w', strtotime($d))]; ?>)
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>

      <tbody>
      <?php foreach ($times as $time): ?>
        <tr>
          <th><?php echo esc_html($time); ?></th>

          <?php foreach ($week_dates as $d): ?>
            <?php
            $w = date('w', strtotime($d));
            $is_holiday = in_array((string)$w, $holidays, true);
            if ($is_holiday) {
              echo '<td class="holiday">休</td>';
              continue;
            }

            // --- 出勤スタッフを取得 ---
            $available_staffs = [];
            foreach ($staffs as $u) {
              if (salon_is_staff_available($u->ID, $d, $time)) {
                $available_staffs[] = $u->ID;
              }
            }

            if (empty($available_staffs)) {
              echo '<td class="off">—</td>';
              continue;
            }

            // --- この日の全予約を一括取得（res_staff=0 も含む） ---
            $q = new WP_Query([
              'post_type'      => 'reservation',
              'post_status'    => 'any',
              'posts_per_page' => -1,
              'meta_query'     => [
                'relation' => 'AND',
                [
                  'key'     => 'res_date',
                  'value'   => $d,
                  'compare' => '=',
                ],
                [
                  'relation' => 'OR',
                  // 指名なし（0 または '0'）も含む
                  [
                    'key'     => 'res_staff',
                    'value'   => 0,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                  ],
                  [
                    'key'     => 'res_staff',
                    'value'   => '0',
                    'compare' => '=',
                  ],
                  [
                    'key'     => 'res_staff',
                    'compare' => 'EXISTS',
                  ],
                ],
              ],
            ]);
            


$reservations = [];
if ($q->have_posts()) {
  while ($q->have_posts()) {
    $q->the_post();

    // res_staffを安全に数値化（空文字や"0"も0として扱う）
    $rstaff = get_post_meta(get_the_ID(), 'res_staff', true);
    $rstaff = is_numeric($rstaff) ? intval($rstaff) : 0;

    // res_timeも安全にフォーマット
    $rtime = get_post_meta(get_the_ID(), 'res_time', true);
    $rtime = $rtime ? date('H:i', strtotime($rtime)) : '';

    $reservations[] = [
      'staff' => $rstaff,
      'time'  => $rtime,
    ];
  }
}
wp_reset_postdata();


            // --- デバッグ付き：この時間に全員埋まっているかチェック（ログ出力版） ---
$available_count = count($available_staffs);
$booked_count = 0;
$current_time = date('H:i', strtotime($time));

// ログ出力開始
error_log("=== {$d} {$current_time} 判定開始 ===");
error_log("出勤スタッフ: " . implode(',', $available_staffs));

// 予約リスト出力
foreach ($reservations as $r) {
  error_log("予約 -> staff={$r['staff']} time={$r['time']}");
}

// 各スタッフの埋まり判定
foreach ($available_staffs as $sid) {
  $is_booked = false;

  foreach ($reservations as $r) {
    if ($r['staff'] === $sid && $r['time'] === $current_time) {
      $is_booked = true;
      break;
    }
  }

  if ($is_booked) {
    $booked_count++;
    error_log("スタッフ{$sid} は埋まり");
  } else {
    error_log("スタッフ{$sid} は空き");
  }
}

// 指名なし予約チェック
$has_nominationless = false;
foreach ($reservations as $r) {
  if ($r['staff'] === 0 && $r['time'] === $current_time) {
    $has_nominationless = true;
    error_log("指名なし予約あり");
    break;
  }
}

error_log("出勤={$available_count} / 埋まり={$booked_count} / 指名なし={$has_nominationless}");

// --- 判定出力 ---
if ($booked_count < $available_count) {
  echo '<td class="available">○</td>';
  error_log("→ 判定: ○（まだ空きあり）");
} else {
  if ($has_nominationless) {
    echo '<td class="booked">×</td>';
    error_log("→ 判定: ×（全員埋まり＋指名なし予約）");
  } else {
    echo '<td class="available">○</td>';
    error_log("→ 判定: ○（全員埋まりだが指名なし無し）");
  }
}

            ?>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php
  return ob_get_clean();
}


// =========================================================
// カレンダー生成（フロント）
// =========================================================
function salon_generate_calendar_html($menu_key, $staff_id = 0, $week = 0, $mode = 'front') {
  date_default_timezone_set('Asia/Tokyo');
  $store     = salon_get_store_settings();
  $holidays  = $store['holidays'] ?? [];
  $time_step = intval($store['time_step'] ?? 30);
  $times     = salon_time_slots();

  // ===== 週の日付一覧 =====
  $today = strtotime('today');
  $start = strtotime("+".(7 * intval($week))." days", $today);
  $week_dates = [];
  for ($i = 0; $i < 7; $i++) $week_dates[] = date('Y-m-d', strtotime("+$i day", $start));

  // ===== スタッフ対象 =====
  $staffs = [];
if ($staff_id > 0) {
  // 指名予約：特定スタッフのみ
  $u = get_userdata($staff_id);
  if ($u) $staffs = [$u];
} else {
  // 指名なし予約：対応可能スタッフのみ
  $all_staffs = salon_get_staff_users();

  foreach ($all_staffs as $s) {
    $uid = $s->ID;

    // ✅ 施術メニュー設定を取得
    $menu_settings = get_user_meta($uid, 'salon_menu_settings', true);

    // 有効フラグチェック（enabled=1 のみ許可）
    if (!empty($menu_settings[$menu_key]['enabled']) && intval($menu_settings[$menu_key]['enabled']) === 1) {
      $staffs[] = $s;
    }
  }
}

  // ====== 予約情報の取得 ======
  $booked = [];
$posts = get_posts([
  'post_type'   => 'reservation',
  'post_status' => 'publish',
  'numberposts' => -1,
  'meta_query'  => [
    ['key' => 'res_date', 'value' => $week_dates, 'compare' => 'IN']
  ]
]);

foreach ($posts as $p) {
  $pid   = $p->ID;
  $date  = get_post_meta($pid, 'res_date', true);
  $time  = get_post_meta($pid, 'res_time', true);
  $sid   = intval(get_post_meta($pid, 'res_staff', true)); // 指名なしは 0
  $menu  = get_post_meta($pid, 'res_menu', true);

  if (!$date || !$time) continue;

  // --- 対応時間（duration）取得 ---
  $menu_duration = 60;
  if ($sid > 0) {
    // 指名予約：スタッフ個別設定
    $menu_settings = get_user_meta($sid, 'salon_menu_settings', true);
    $menu_duration = intval($menu_settings[$menu]['duration'] ?? 60);
  } else {
    // 指名なし：予約されたメニュー名から duration を推定
    $first_staff = current(salon_get_staff_users());
    $menu_settings = get_user_meta($first_staff->ID, 'salon_menu_settings', true);
    $menu_duration = intval($menu_settings[$menu]['duration'] ?? 60);
  }

  $time_step = intval($store['time_step'] ?? 30);
  $start_ts = strtotime("$date $time");
  $before_minutes = $menu_duration - $time_step;
  $block_start_ts = strtotime("-{$before_minutes} minutes", $start_ts);
  $block_end_ts   = strtotime("+{$menu_duration} minutes", $start_ts);

  // --- ここからブロック登録 ---
  if ($sid === 0) {
    // 🔸 指名なし予約：対応可スタッフ全員をブロック
    foreach (salon_get_staff_users() as $staff) {
      $menu_settings = get_user_meta($staff->ID, 'salon_menu_settings', true);
      if (!empty($menu_settings[$menu]['enabled']) && intval($menu_settings[$menu]['enabled']) === 1) {
        for ($t = $block_start_ts; $t < $block_end_ts; $t += ($time_step * 60)) {
          $block_time = date('H:i', $t);
          $booked[$staff->ID][$date][$block_time] = true;
        }
      }
    }
  } else {
    // 🔹 指名予約：そのスタッフのみブロック
    for ($t = $block_start_ts; $t < $block_end_ts; $t += ($time_step * 60)) {
      $block_time = date('H:i', $t);
      $booked[$sid][$date][$block_time] = true;
    }
  }
}

  

  // ===== 出勤データの取得 =====
  $shifts = [];
  foreach ($staffs as $s) {
    $uid = $s->ID;
    $ym = date('Ym');
    $meta_key = salon_shift_meta_key($ym);
    $shift_data = get_user_meta($uid, $meta_key, true);

    // s/e → start/end に統一
    $fixed = [];
    foreach ((array)$shift_data as $k => $v) {
      if (isset($v['s']) || isset($v['e'])) {
        $fixed[(int)$k] = [
          'start' => $v['s'] ?? '',
          'end'   => $v['e'] ?? ''
        ];
      } elseif (isset($v['start']) || isset($v['end'])) {
        $fixed[(int)$k] = $v;
      }
    }
    $shifts[$uid] = $fixed;
  }

  // ===== 出力 =====
  ob_start(); ?>
  <table class="calendar-table">
    <thead>
      <tr>
        <th class="time-col">時間</th>
        <?php foreach ($week_dates as $d): ?>
          <th><?php echo esc_html(date('n/j (D)', strtotime($d))); ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($times as $time): ?>
        <tr>
          <td class="time-col"><?php echo esc_html($time); ?></td>
          <?php foreach ($week_dates as $d):
  $wd = date('w', strtotime($d));
  if (in_array((string)$wd, $holidays, true)) {
    echo '<td class="holiday">休</td>';
    continue;
  }

  $is_booked = false;
  $is_available = false;
  $available_staff_id = 0; // 指名スタッフがいる場合に保持

  foreach ($staffs as $s) {
    $uid = $s->ID;
    $ym  = date('Ym', strtotime($d));
    $day = (int)date('j', strtotime($d));
    $shift = $shifts[$uid][$day] ?? null;

    if (!$shift || empty($shift['start']) || empty($shift['end'])) continue;

    // 出勤中か？
    if (salon_between($time, $shift['start'], $shift['end'])) {
      $is_available = true;
      $available_staff_id = $uid;

      // 予約ありか？
      if (!empty($booked[$uid][$d][$time])) {
        $is_booked = true;
        break;
      }
    }
  }

  if ($is_booked) {
    echo '<td class="booked">×</td>';
  } elseif ($is_available) {
    // ✅ クリックできるボタンを追加
    printf(
      '<td class="available"><button type="button" class="slot-btn" data-date="%s" data-time="%s" data-staff="%d">○</button></td>',
      esc_attr($d),
      esc_attr($time),
      intval($available_staff_id)
    );
  } else {
    echo '<td class="off">—</td>';
  }
endforeach; ?>

        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php
  return ob_get_clean();
}


/**
 * スタッフが指定日時に出勤しているか判定（新バージョン）
 * 保存形式：user_meta に月単位で "salon_shift_YYYYMM" が格納されている
 * 例：$shift_data['2025-11-09'] = ['start' => '10:00', 'end' => '17:00', 'work' => 1]
 */
/** スタッフ空き判定（予約・シフト確認） */
function salon_is_staff_available($staff_id, $date, $time) {
  $ym = date('Ym', strtotime($date));
  $shift_key = salon_shift_meta_key($ym);
  $shift_meta = get_user_meta($staff_id, $shift_key, true);

  // ✅ salon_shift_YYYYMM にデータがない場合、salon_staff_info を代替参照
  if (empty($shift_meta)) {
    $shift_meta = get_user_meta($staff_id, 'salon_staff_info', true);
  }

  // 🔄 フォーマット統一（start/end/work → s/e）
  if (is_array($shift_meta)) {
    foreach ($shift_meta as $k => $v) {
      if (isset($v['start']) || isset($v['end'])) {
        $shift_meta[$k] = [
          's' => $v['start'] ?? ($v['s'] ?? ''),
          'e' => $v['end']   ?? ($v['e'] ?? ''),
        ];
      }
    }
  }


  // 正常化処理
  $shift_norm = salon_normalize_shift_meta((array)$shift_meta, $ym);

  // 該当日を取得
  $day_key = date('j', strtotime($date));
  $shift = $shift_norm[$day_key] ?? null;

  if (!$shift || empty($shift['s']) || empty($shift['e'])) {
    return false;
  }

  $t = salon_time_to_min($time);
  $s = salon_time_to_min($shift['s']);
  $e = salon_time_to_min($shift['e']);

  if ($t < $s || $t >= $e) {
    error_log("⛔ out of range for $staff_id on $date ($time)");
    return false;
  }

  return true;
}





/** Ajax：カレンダー切替 */
/** Ajax：カレンダー切替 */
add_action('wp_ajax_salon_load_calendar','salon_ajax_load_calendar');
add_action('wp_ajax_nopriv_salon_load_calendar','salon_ajax_load_calendar');
function salon_ajax_load_calendar(){
  $menu_key = sanitize_text_field($_POST['menu_key'] ?? '');
  $staff_id = intval($_POST['staff_id'] ?? 0);
  $week     = intval($_POST['week'] ?? 0);

  if ($staff_id === 0) {
    // 指名なし → 全スタッフ分まとめて生成
    echo salon_generate_calendar_html_all_staff($menu_key, $week);
  } else {
    // 指名あり → 共有ブロックも反映
    echo salon_generate_calendar_html_with_shared_blocks($menu_key, $staff_id, $week);
  }

  wp_die();
}



/** ショートコード */
add_shortcode('salon_calendar',function($atts){
  $menu=$atts['menu'] ?? 'default';
  return salon_generate_calendar_html_wrapper($menu);
});

/***********************************************************
 * 🧩 Ajax：選択メニューに対応するスタッフを取得
 ***********************************************************/
add_action('wp_ajax_salon_get_staffs_by_menu_front', 'salon_get_staffs_by_menu_front');
add_action('wp_ajax_nopriv_salon_get_staffs_by_menu_front', 'salon_get_staffs_by_menu_front');

function salon_get_staffs_by_menu_front() {
  $menu_key = sanitize_text_field($_POST['menu_key'] ?? '');
  $staffs = salon_get_staff_users();
  $list = [];

  // まず「指名なし」を常に先頭に追加
  $list[0] = '指名なし';

  foreach ($staffs as $s) {
    $settings = get_user_meta($s->ID, 'salon_menu_settings', true) ?: [];
    if (!empty($settings[$menu_key]['enabled'])) {
      $list[$s->ID] = $s->display_name;
    }
  }

  wp_send_json($list);
}

/***********************************************************
 * 8️⃣ フロント予約登録 + 確認カレンダー + 通知
 ***********************************************************/

/**
 * ▼ 予約フォーム処理（Ajax対応）
 */
add_action('wp_ajax_salon_submit_reservation', 'salon_submit_reservation');
add_action('wp_ajax_nopriv_salon_submit_reservation', 'salon_submit_reservation');

function salon_submit_reservation(){


  // ✅ nonce検証（完全一致すること）
  check_ajax_referer('salon_reservation_nonce', 'nonce');

  $name   = sanitize_text_field($_POST['name']   ?? '');
  $tel    = sanitize_text_field($_POST['tel']    ?? '');
  $email  = sanitize_email($_POST['email']       ?? '');
  $date   = sanitize_text_field($_POST['date']   ?? '');
  $time   = sanitize_text_field($_POST['time']   ?? '');
  $menu   = sanitize_text_field($_POST['menu']   ?? '');
  $staff  = intval($_POST['staff'] ?? 0);

  // バリデーション
  $errors = [];
  if(!$name)  $errors[]='お名前を入力してください。';
  if(!$tel)   $errors[]='電話番号を入力してください。';
  if(!$date)  $errors[]='日付を選択してください。';
  if(!$time)  $errors[]='時間を選択してください。';
  if(!$menu)  $errors[]='メニューを選択してください。';
  if(!empty($errors)){
    error_log('❌ バリデーションエラー: ' . implode(' / ', $errors));
    wp_send_json_error(['msg'=>implode('<br>',$errors)]);
  }

  // スタッフ空き確認
  if($staff>0 && !salon_is_staff_available($staff,$date,$time)){
    error_log('❌ スタッフ空きなし: staff='.$staff.' date='.$date.' time='.$time);
    wp_send_json_error(['msg'=>'申し訳ありません。この時間はすでに予約が埋まっています。']);
  }

  // ✅ STEP2：指名なし → 自動担当割当
  if ($staff === 0) {
    $staffs = salon_get_staff_users();
    $assigned = 0;

    foreach ($staffs as $s) {
      $uid = $s->ID;
      $menu_settings = get_user_meta($uid, 'salon_menu_settings', true);

      // メニュー対応可 && 出勤中 && 空き時間なら採用
      if (!empty($menu_settings[$menu]['enabled']) && salon_is_staff_available($uid, $date, $time)) {
        $staff = $uid;
        $assigned = 1;
        break;
      }
    }

    update_post_meta($post_id, 'res_auto_assigned', $assigned ? 1 : 0); // 自動割当フラグ
  }

  // ✅ 指名料処理
  $store = salon_get_store_settings();
  $nomination_fee = intval($store['nomination_fee'] ?? 0);
  $total_price = 0;

  $menus = $store['menus'] ?? [];
  foreach ($menus as $m) {
    if ($m['name'] === $menu) {
      $total_price = intval($m['price']);
      break;
    }
  }

  // 指名予約で指名料ありなら加算
  if ($staff > 0 && intval($_POST['staff'] ?? 0) > 0 && $nomination_fee > 0) {
    $total_price += $nomination_fee;
  }

  update_post_meta($post_id, 'res_nomination_fee', ($staff > 0 && intval($_POST['staff'] ?? 0) > 0) ? $nomination_fee : 0);
  update_post_meta($post_id, 'res_total', $total_price);

  // 予約登録
$post_id = wp_insert_post([
  'post_type'   => 'reservation',
  'post_status' => 'publish',
  'post_title'  => sprintf('%s %s %s（%s）', $date, $time, $name, $menu),
]);

if (is_wp_error($post_id)) {
  error_log('❌ wp_insert_post失敗: ' . $post_id->get_error_message());
  wp_send_json_error(['msg' => '予約の登録に失敗しました。']);
}

if (!$post_id) {
  error_log('❌ wp_insert_post から false が返却されました');
  wp_send_json_error(['msg' => '予約の登録に失敗しました。']);
}

// === ここでメタ保存 ===
update_post_meta($post_id, 'res_name', $name);
update_post_meta($post_id, 'res_tel', $tel);
update_post_meta($post_id, 'res_email', $email);
update_post_meta($post_id, 'res_date', $date);
update_post_meta($post_id, 'res_time', $time);
update_post_meta($post_id, 'res_menu', $menu);
update_post_meta($post_id, 'res_staff', $staff);
update_post_meta($post_id, 'res_datetime', "$date $time:00");

// ✅ 自動割当フラグをここで確実に保存
update_post_meta($post_id, 'res_auto_assigned', ($staff > 0 && intval($_POST['staff'] ?? 0) === 0) ? 1 : 0);

salon_send_reservation_mail($post_id);
wp_send_json_success(['msg' => 'ご予約を受け付けました。']);

}


/**
 * ▼ メール送信
 */
function salon_send_reservation_mail($post_id){
  $admin = get_option('admin_email');
  $site  = get_bloginfo('name');
  $to_user = get_post_meta($post_id,'res_email',true);
  $name = get_post_meta($post_id,'res_name',true);
  $date = get_post_meta($post_id,'res_date',true);
  $time = get_post_meta($post_id,'res_time',true);
  $menu = get_post_meta($post_id,'res_menu',true);
  $staff_id = get_post_meta($post_id,'res_staff',true);
  $staff_name = ($staff_id>0 && $u=get_userdata($staff_id)) ? $u->display_name : '指名なし';

  $subject_admin = "【$site】新規予約が入りました";
  $subject_user  = "【$site】ご予約ありがとうございます";

  $body_admin = <<<EOM
以下の内容で新規予約が入りました。

■ お名前：{$name}
■ 日時：{$date} {$time}
■ メニュー：{$menu}
■ 担当：{$staff_name}
■ 電話番号：{$_POST['tel']}
■ メール：{$to_user}
EOM;

  $body_user = <<<EOM
{$name} 様

このたびはご予約いただきありがとうございます。
以下の内容で承りました。

■ 日時：{$date} {$time}
■ メニュー：{$menu}
■ 担当：{$staff_name}

当日はお気をつけてお越しくださいませ。
キャンセルや変更がある場合はご連絡ください。

────────────────────
{$site}
EOM;

  // 管理者宛て
  wp_mail($admin,$subject_admin,$body_admin);
  // ユーザー宛て（メール入力がある場合）
  if($to_user) wp_mail($to_user,$subject_user,$body_user);
}


/**
 * ▼ 予約確認用カレンダー（readonly）
 *    - 出勤なし → 「—」
 *    - 出勤中・予約なし → 「○」
 *    - 出勤中・予約あり → 「×」
 */
function salon_generate_readonly_calendar($menu_key, $staff_id = 0, $week = 0) {
  date_default_timezone_set('Asia/Tokyo');

  $store     = salon_get_store_settings();
  $holidays  = $store['holidays'] ?? [];
  $times     = salon_time_slots();
  $staffs    = salon_get_staff_users();

  $today = strtotime('today');
  $start = strtotime('+' . (7 * intval($week)) . ' days', $today);
  $week_dates = [];
  for ($i = 0; $i < 7; $i++) $week_dates[] = date('Y-m-d', strtotime("+$i day", $start));

  ob_start(); ?>
  <div class="salon-calendar readonly">
    <table class="calendar-table">
      <thead>
        <tr>
          <th class="time-col">時間</th>
          <?php foreach ($week_dates as $d): ?>
            <th><?= esc_html(date('n/j (D)', strtotime($d))) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($times as $time): ?>
          <tr>
            <th class="time-col"><?= esc_html($time) ?></th>
            <?php foreach ($week_dates as $d):
              $w = date('w', strtotime($d));
              $is_holiday = in_array((string)$w, $holidays, true);

              if ($is_holiday) {
                echo '<td class="holiday">休</td>';
                continue;
              }

              // 🔸 出勤チェック
              $available_staffs = [];
              foreach ($staffs as $u) {
                if (salon_is_staff_available($u->ID, $d, $time)) {
                  $available_staffs[] = $u->ID;
                }
              }

              if (empty($available_staffs)) {
                // 出勤なし
                echo '<td class="off">—</td>';
              } else {
                // 出勤中 → 予約状況を確認
                $is_booked = false;
foreach ($available_staffs as $sid) {
  $q = new WP_Query([
    'post_type'      => 'reservation',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'meta_query'     => [
      'relation' => 'AND',
      [
        'key'   => 'res_date',
        'value' => $d,
      ],
      [
        'relation' => 'OR',
        ['key' => 'res_staff', 'value' => (string)$sid, 'compare' => '='],
        ['key' => 'res_staff', 'value' => '0', 'compare' => '='], // ← 指名なしも含める！
      ],
    ],
  ]);
  if ($q->have_posts()) {
    while ($q->have_posts()) {
      $q->the_post();
      $res_time = get_post_meta(get_the_ID(), 'res_time', true);
      $menu     = get_post_meta(get_the_ID(), 'res_menu', true);
      $settings = get_user_meta($sid, 'salon_menu_settings', true) ?: [];
      $dur      = intval($settings[$menu]['duration'] ?? 60);
      $start_ts = strtotime("$d $res_time");
      $end_ts   = $start_ts + ($dur * 60);
      $chk_ts   = strtotime("$d $time");
      if ($chk_ts >= $start_ts && $chk_ts < $end_ts) {
        $is_booked = true;
        break 2; // 予約あり → ループ終了
      }
    }
    wp_reset_postdata();
  }
}

                if ($is_booked) {
                  echo '<td class="booked">×</td>';
                } else {
                  echo '<td class="available">○</td>';
                }
              }
            endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php
  return ob_get_clean();
}

/**
 * ▼ ショートコード（確認用）
 * [salon_calendar_readonly staff="3"]
 */
add_shortcode('salon_calendar_readonly',function($atts){
  $staff = intval($atts['staff'] ?? 0);
  return salon_generate_readonly_calendar('default',$staff);
});


/***********************************************************
 * 9️⃣ フロント：readonlyカレンダーAjax対応
 ***********************************************************/
add_action('wp_ajax_salon_render_readonly_calendar_ajax', 'salon_render_readonly_calendar_ajax');
add_action('wp_ajax_nopriv_salon_render_readonly_calendar_ajax', 'salon_render_readonly_calendar_ajax');

function salon_render_readonly_calendar_ajax() {
  // POST値 week が存在しない or 空文字のときは 0 にする
  $week = isset($_POST['week']) && $_POST['week'] !== '' ? intval($_POST['week']) : 0;

  // 念のため週数が不正値の場合も 0 に戻す
  if ($week < 0 || $week > 52) {
    $week = 0;
  }

  // 🔹 カレンダーを生成（デフォルト：指名なし、今週）
  $html = salon_generate_readonly_calendar('default', 0, $week);

  echo $html ?: '<div style="padding:10px;color:#999;">表示できるカレンダーがありません。</div>';
  wp_die();
}

/***********************************************************
 * 🧩 9️⃣ フロント：モーダルカレンダーAjax対応（指名・指名なし対応）
 ***********************************************************/
add_action('wp_ajax_salon_render_calendar_front', 'salon_render_calendar_front');
add_action('wp_ajax_nopriv_salon_render_calendar_front', 'salon_render_calendar_front');

function salon_render_calendar_front() {
  // ===== リクエスト受取 =====
  $menu_key = sanitize_text_field($_POST['menu'] ?? '');
  $staff_id = $_POST['staff'] ?? '';
  $week     = intval($_POST['week'] ?? 0);
  $mode     = sanitize_text_field($_POST['mode'] ?? 'front');

  // ===== スタッフ抽出 =====
  if ($staff_id !== '' && $staff_id !== null && intval($staff_id) > 0) {
    // 指定スタッフのみ
    $staffs = [get_userdata(intval($staff_id))];
  } else {
    // 指名なし（0 または空文字）→ 全スタッフ
    $staffs = salon_get_staff_users();
  }

  // ===== 取得結果が空の場合は明示的にエラーを返す（安全策） =====
  if (empty($staffs)) {
    echo '<div style="padding:10px;color:#999;">スタッフ情報が取得できませんでした。</div>';
    wp_die();
  }

  // ===== カレンダーHTML生成 =====
  $html = salon_generate_calendar_html($menu_key, intval($staff_id), $week, $mode);
  echo $html ?: '<div style="padding:10px;color:#999;">カレンダーの生成に失敗しました。</div>';
  wp_die();
}


/**
 * 出勤情報を取得（管理画面・フロント共通）
 * 保存形式：user_meta に "salon_shift_YYYYMM"
 */
function salon_get_staff_shifts($user_id, $ym = '') {
  if (empty($ym)) $ym = date('Ym');
  $meta_key = salon_shift_meta_key($ym);

  // まず当月キーを読む
  $shift_meta = get_user_meta($user_id, $meta_key, true);

  // ⚡ salon_shift_YYYYMM が空なら salon_staff_info を参照（後方互換）
  if (empty($shift_meta)) {
    $alt = get_user_meta($user_id, 'salon_staff_info', true);
    if (!empty($alt) && is_array($alt)) {
      $shift_meta = [];
      foreach ($alt as $date => $v) {
        if (strpos($date, '-') !== false) {
          $d_ym = date('Ym', strtotime($date));
          if ($d_ym == $ym) {
            $day = (int)date('j', strtotime($date));
            $shift_meta[$day] = [
              's' => $v['start'] ?? '',
              'e' => $v['end'] ?? '',
            ];
          }
        }
      }
    }
  }

  // 🔄 start/end → s/e に統一
  if (is_array($shift_meta)) {
    foreach ($shift_meta as $k => $v) {
      if (isset($v['start']) || isset($v['end'])) {
        $shift_meta[$k] = [
          's' => $v['start'] ?? ($v['s'] ?? ''),
          'e' => $v['end']   ?? ($v['e'] ?? ''),
        ];
      }
    }
  }


  return $shift_meta;
}


/**
 * --------------------------------------------------
 * 公開用カレンダー描画（salon_render_calendar_public_readonly）
 * --------------------------------------------------
 */
add_action('wp_ajax_salon_render_calendar_public_readonly', 'salon_render_calendar_public_readonly');
add_action('wp_ajax_nopriv_salon_render_calendar_public_readonly', 'salon_render_calendar_public_readonly');

function salon_render_calendar_public_readonly() {

    $menu_key = sanitize_text_field($_POST['menu_key'] ?? '');
    $staff_id = intval($_POST['staff_id'] ?? 0);
    $week     = intval($_POST['week'] ?? 0);

    // カレンダーHTML生成関数（既に存在してるはず）
    if (function_exists('salon_generate_calendar_html_all_staff')) {
        $html = salon_generate_calendar_html_all_staff($menu_key, $week);
    } elseif (function_exists('salon_generate_calendar_html')) {
        $html = salon_generate_calendar_html($menu_key, $staff_id, $week, 'front');
    } else {
        $html = '<p>カレンダー生成関数が見つかりません。</p>';
    }

    echo $html;
    wp_die(); // ← WordPressのAjaxはこれで完了
}

add_action('wp_ajax_salon_get_calendar_html', 'salon_get_calendar_html');
add_action('wp_ajax_nopriv_salon_get_calendar_html', 'salon_get_calendar_html');

function salon_get_calendar_html() {
  $menu_key = sanitize_text_field($_GET['menu_key'] ?? '');
  $staff_id = intval($_GET['staff_id'] ?? 0);
  echo salon_generate_calendar_html($menu_key, $staff_id);
  wp_die();
}

/**
 * 指名スタッフ専用カレンダー（指名なし予約も考慮）
 */
function salon_generate_calendar_html_with_shared_blocks($menu_key, $staff_id, $week = 0) {
  date_default_timezone_set('Asia/Tokyo');

  $store     = salon_get_store_settings();
  $holidays  = $store['holidays'] ?? [];
  $time_step = intval($store['time_step'] ?? 30);

  $today = strtotime('today');
  $start = strtotime("+".(7 * intval($week))." days", $today);
  $week_dates = [];
  for ($i = 0; $i < 7; $i++) $week_dates[] = date('Y-m-d', strtotime("+$i day", $start));

  $times = salon_time_slots();

  ob_start(); ?>
  <div class="salon-calendar">
    <h3 class="cal-title">空き状況（1週間）</h3>
    <div class="cal-legend"><span>○：予約可</span><span>×：予約済</span><span>—：出勤なし</span></div>

    <table class="cal-table">
      <thead>
        <tr>
          <th>時間</th>
          <?php foreach ($week_dates as $d): ?>
            <th><?php echo date('n/j', strtotime($d)); ?>(<?php echo ['日','月','火','水','木','金','土'][date('w', strtotime($d))]; ?>)</th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($times as $time): ?>
          <tr>
            <th><?php echo esc_html($time); ?></th>
            <?php foreach ($week_dates as $d): ?>
              <?php
              $w = date('w', strtotime($d));
              $is_holiday = in_array((string)$w, $holidays, true);
              if ($is_holiday) {
                echo '<td class="holiday">休</td>';
                continue;
              }

              // --- 出勤確認 ---
              if (!salon_is_staff_available($staff_id, $d, $time)) {
                echo '<td class="off">—</td>';
                continue;
              }

              // --- 予約状況を確認 ---
              $q = new WP_Query([
                'post_type'      => 'reservation',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'meta_query'     => [
                  'relation' => 'AND',
                  [
                    'key'   => 'res_date',
                    'value' => $d,
                  ],
                  [
                    'relation' => 'OR',
                    ['key' => 'res_staff', 'value' => (string)$staff_id, 'compare' => '='],
                    ['key' => 'res_staff', 'value' => '0', 'compare' => '='], // ← 指名なしを含める
                  ],
                ],
              ]);

              $is_booked = false;
              if ($q->have_posts()) {
                while ($q->have_posts()) {
                  $q->the_post();
                  $res_time = get_post_meta(get_the_ID(), 'res_time', true);
                  if ($res_time === $time) {
                    $is_booked = true;
                    break;
                  }
                }
              }
              wp_reset_postdata();

              echo $is_booked
                ? '<td class="booked">×</td>'
                : '<td class="available">○</td>';
              ?>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php
  return ob_get_clean();
}


/**
 * ▼ 予約編集画面：担当スタッフ（完全固定・指名なし除外版）
 */
/**
 * =========================================================
 * 予約保存時：「_old_res_staff」を正しく保持（初回から保存）
 * =========================================================
 */
add_action('save_post_reservation', function($post_id, $post, $update) {

  // ✅ 無限ループ防止
  if (defined('SALON_SAVE_RUNNING')) return;
  define('SALON_SAVE_RUNNING', true);

  // ✅ 自動保存をスキップ
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;

  // ✅ POSTまたはメタから担当スタッフを取得
  $new_staff = isset($_POST['res_staff'])
    ? intval($_POST['res_staff'])
    : intval(get_post_meta($post_id, 'res_staff', true));

  // 旧データ取得
  $old_staff = get_post_meta($post_id, '_old_res_staff', true);

  // ✅ 初回登録時または旧データが空のときだけ処理
  if ($old_staff === '' || $old_staff === null) {

    // スタッフ名を取得
    $staff_label = '';
    if (isset($_POST['res_staff_name']) && $_POST['res_staff_name'] !== '') {
      $staff_label = sanitize_text_field($_POST['res_staff_name']);
    } else {
      $user = get_userdata($new_staff);
      $staff_label = $user ? $user->display_name : '';
    }

    // 指名なし判定
    $is_no_nomination = (
      stripos($staff_label, '指名なし') !== false ||
      $new_staff === 0
    );

    if ($is_no_nomination) {
      update_post_meta($post_id, '_old_res_staff', 0);
      error_log("=== 初回保存: 指名なし post_id=$post_id ===");
    } else {
      update_post_meta($post_id, '_old_res_staff', $new_staff);
      error_log("=== 初回保存: 指名あり post_id=$post_id staff_id=$new_staff ===");
    }

  } else {
    // すでに記録済みならスキップ
    error_log("=== 更新維持: _old_res_staff=$old_staff post_id=$post_id ===");
  }

}, 20, 3);




/**
 * ▼ 予約投稿タイプの編集画面：タイトルとエディタを非表示
 */
add_action('admin_head', function() {
  global $post_type;
  if ($post_type === 'reservation') {
    echo '<style>
      #titlediv, #postdivrich { display: none !important; }
    </style>';
  }
});

/**
 * ▼ 予約投稿タイプ：右側の「担当スタッフ」メタボックスを確実に削除
 */
add_action('add_meta_boxes', function() {
  remove_meta_box('reservation_staff_box', 'reservation', 'side');
}, 9999); // ← 後から実行するための優先度

/**
 * =========================================================
 * 予約保存時：「_old_res_staff」を正しく保持（指名あり／なしを記録）
 * =========================================================
 */
add_action('save_post_reservation', function($post_id, $post, $update) {

  // ✅ 無限ループ防止
  if (defined('SALON_SAVE_RUNNING')) return;
  define('SALON_SAVE_RUNNING', true);

  // ✅ 自動保存や権限チェックをスキップ
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;
  if (!isset($_POST['res_staff'])) return;

  $new_staff = intval($_POST['res_staff']); // 現在担当スタッフ
  $old_staff = get_post_meta($post_id, '_old_res_staff', true);

  // ✅ まだ旧スタッフが保存されていない（初回保存時）
  if ($old_staff === '' || $old_staff === null) {

    // ▼ スタッフ名をPOSTまたはDBから取得
    $staff_label = '';
    if (isset($_POST['res_staff_name']) && $_POST['res_staff_name'] !== '') {
      $staff_label = sanitize_text_field($_POST['res_staff_name']);
    } else {
      $user = get_userdata($new_staff);
      $staff_label = $user ? $user->display_name : '';
    }

    // ▼ 「指名なし」判定
    $is_no_nomination = (
      stripos($staff_label, '指名なし') !== false ||
      stripos($staff_label, 'no staff') !== false ||
      $new_staff === 0
    );

    // ▼ 保存
    if ($is_no_nomination) {
      update_post_meta($post_id, '_old_res_staff', 0); // 指名なし
      error_log("=== 初回保存: 指名なし post_id=$post_id ===");
    } else {
      update_post_meta($post_id, '_old_res_staff', $new_staff); // 指名あり
      error_log("=== 初回保存: 指名あり post_id=$post_id staff_id=$new_staff ===");
    }

  } else {
    // 2回目以降の更新時は上書きしない
    error_log("=== 更新維持: _old_res_staff=$old_staff post_id=$post_id ===");
  }

}, 20, 3);



/**
 * =========================================================
 * Ajax：担当変更後にカレンダー更新をトリガー
 * =========================================================
 */
add_action('save_post_reservation', function($post_id) {
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;

  // 予約情報取得
  $date  = get_post_meta($post_id, 'res_date', true);
  $time  = get_post_meta($post_id, 'res_time', true);
  $staff = intval(get_post_meta($post_id, 'res_staff', true));

  // JavaScriptに送るイベント情報
  $data = [
    'date'  => $date,
    'time'  => $time,
    'staff' => $staff,
  ];

  // Ajax経由で通知
  update_option('salon_last_update', $data); // 最新変更を一時保存
}, 30);


/**
 * =========================================================
 * Ajax：カレンダー更新情報を取得（JSから呼び出し）
 * =========================================================
 */
add_action('wp_ajax_salon_get_last_update', function() {
  $data = get_option('salon_last_update', []);
  wp_send_json_success($data);
});
add_action('wp_ajax_nopriv_salon_get_last_update', function() {
  $data = get_option('salon_last_update', []);
  wp_send_json_success($data);
});


/**
 * ▼ 予約一覧に「指名料」「合計金額」カラムを追加
 */
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


/**
 * ▼ 「指名料」「合計金額」カラムの出力
 */
add_action('manage_reservation_posts_custom_column', function($column, $post_id) {

  if (!in_array($column, ['nomination_fee', 'total_price'], true)) {
    return;
  }

  // 店舗設定の取得
  $store        = salon_get_store_settings();
  $menus        = $store['menus'] ?? [];
  $default_fee  = intval($store['nomination_fee'] ?? 0);

  // 予約データ取得
  $menu_name    = get_post_meta($post_id, 'res_menu', true);
  $staff_id     = intval(get_post_meta($post_id, 'res_staff', true));
  $auto_assign  = intval(get_post_meta($post_id, 'res_auto_assigned', true)); // 1 = 指名なし

  // メニュー料金取得（存在しない場合は0）
  $menu_price = 0;
  foreach ($menus as $m) {
    if (!empty($m['name']) && $m['name'] === $menu_name) {
      $menu_price = intval($m['price']);
      break;
    }
  }

  // 指名料（指名ありの場合のみ加算）
  $nomination_fee = ($staff_id > 0 && $auto_assign === 0) ? $default_fee : 0;

  // 合計金額
  $total = $menu_price + $nomination_fee;

  // 出力処理
  switch ($column) {

    case 'nomination_fee':
      if ($auto_assign === 1) {
        echo '-'; // 指名なしのみハイフン表示
      } elseif ($nomination_fee > 0) {
        echo esc_html(number_format($nomination_fee)) . '円';
      } else {
        echo ''; // それ以外は空欄
      }
      break;

    case 'total_price':
      if ($total > 0) {
        echo esc_html(number_format($total)) . '円';
      }
      break;
  }

}, 10, 2);





/**
 * ▼ 予約メタデータのデバッグログ出力
 *   wp-content/debug.log に記録されます
 */
add_action('current_screen', function($screen) {
  if ($screen->post_type === 'reservation' && $screen->base === 'edit') {
    $posts = get_posts([
      'post_type' => 'reservation',
      'numberposts' => 10,
    ]);
    foreach ($posts as $p) {
      $meta = [
        'post_id'        => $p->ID,
        'res_menu'       => get_post_meta($p->ID, 'res_menu', true),
        'res_staff'      => get_post_meta($p->ID, 'res_staff', true),
        '_old_res_staff' => get_post_meta($p->ID, '_old_res_staff', true),
      ];
      error_log(print_r($meta, true));
    }
  }
});


/**
 * =========================================================
 * 【確定版】担当スタッフ（res_staff）保存後に _old_res_staff を自動同期
 * =========================================================
 */

// 🔹 既存メタ更新時
add_action('updated_post_meta', function($meta_id, $post_id, $meta_key, $meta_value) {
  if (get_post_type($post_id) !== 'reservation') return;
  if ($meta_key !== 'res_staff') return;

  $staff_id = intval($meta_value);
  $user = get_userdata($staff_id);
  $staff_label = $user ? $user->display_name : '';

  $is_no_nomination = (
    $staff_id === 0 ||
    stripos($staff_label, '指名なし') !== false
  );

  if ($is_no_nomination) {
    update_post_meta($post_id, '_old_res_staff', 0);
    error_log("=== updated_post_meta: 指名なし post_id=$post_id ===");
  } else {
    update_post_meta($post_id, '_old_res_staff', $staff_id);
    error_log("=== updated_post_meta: 指名あり post_id=$post_id staff_id=$staff_id ===");
  }

}, 10, 4);


// 🔹 初回メタ追加時
add_action('added_post_meta', function($meta_id, $post_id, $meta_key, $meta_value) {
  if (get_post_type($post_id) !== 'reservation') return;
  if ($meta_key !== 'res_staff') return;

  $staff_id = intval($meta_value);
  $user = get_userdata($staff_id);
  $staff_label = $user ? $user->display_name : '';

  $is_no_nomination = (
    $staff_id === 0 ||
    stripos($staff_label, '指名なし') !== false
  );

  if ($is_no_nomination) {
    update_post_meta($post_id, '_old_res_staff', 0);
    error_log("=== added_post_meta: 指名なし post_id=$post_id ===");
  } else {
    update_post_meta($post_id, '_old_res_staff', $staff_id);
    error_log("=== added_post_meta: 指名あり post_id=$post_id staff_id=$staff_id ===");
  }

}, 10, 4);


/**
 * =========================================================
 * 【最終確定版】投稿全体が保存完了した後に _old_res_staff を確実に登録
 * =========================================================
 */
add_action('wp_after_insert_post', function($post_id, $post, $update) {

  // 対象：予約投稿タイプのみ
  if ($post->post_type !== 'reservation') return;

  // スタッフID取得（POST優先 → メタ補完）
  $staff_id = isset($_POST['res_staff'])
    ? intval($_POST['res_staff'])
    : intval(get_post_meta($post_id, 'res_staff', true));

  if ($staff_id === 0) {
    update_post_meta($post_id, '_old_res_staff', 0);
    error_log("=== wp_after_insert_post: 指名なし post_id=$post_id ===");
    return;
  }

  // スタッフ情報取得
  $user = get_userdata($staff_id);
  $staff_label = $user ? $user->display_name : '';

  // 指名なし判定
  $is_no_nomination = (
    $staff_id === 0 ||
    stripos($staff_label, '指名なし') !== false
  );

  // 保存
  if ($is_no_nomination) {
    update_post_meta($post_id, '_old_res_staff', 0);
    error_log("=== wp_after_insert_post: 指名なし post_id=$post_id ===");
  } else {
    update_post_meta($post_id, '_old_res_staff', $staff_id);
    error_log("=== wp_after_insert_post: 指名あり post_id=$post_id staff_id=$staff_id ===");
  }

}, 10, 3);


/**
 * 指名なし予約の補正処理
 */
add_action('save_post_reservation', function($post_id, $post, $update) {
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if ($post->post_status === 'auto-draft') return;

  $staff = get_post_meta($post_id, 'res_staff', true);

  // 未設定 or 空 or 0未満の場合にのみ補完
  if ($staff === '' || $staff === null || intval($staff) < 1) {
    update_post_meta($post_id, 'res_staff', 0);
    error_log("✅ 指名なしを維持しました（post_id={$post_id}）");
  }
}, 20, 3);


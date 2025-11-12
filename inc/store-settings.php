<?php
if (!defined('ABSPATH')) exit;

/***********************************************************
 * 店舗設定（営業時間・定休日・祝日・臨時休業・指名料・メニュー・住所・電話）
 ***********************************************************/

/**
 * 店舗設定を取得
 */
function salon_get_store_settings() {
  $defaults = [
    'open_time'          => '09:00',
    'close_time'         => '19:30',
    'time_step'          => 30,
    'holidays'           => [],
    'holiday_closures'   => [], // 祝日個別休業
    'special_holidays'   => [], // 臨時休業
    'menus'              => [],
    'nomination_fee'     => 0,
    'address'            => '',
    'tel'                => '',
  ];
  $opt = get_option('salon_store_settings', []);
  return wp_parse_args($opt, $defaults);
}

/**
 * 管理画面メニュー追加
 */
add_action('admin_menu', function() {
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

/**
 * 店舗設定ページ本体
 */
function salon_render_store_settings_page() {
  if (!current_user_can('manage_options')) return;

  // 保存処理
  if (isset($_POST['salon_store_save'])) {
    check_admin_referer('salon_store_save_action');

    $open     = sanitize_text_field($_POST['open_time'] ?? '');
    $close    = sanitize_text_field($_POST['close_time'] ?? '');
    $step     = intval($_POST['time_step'] ?? 30);
    $holidays = array_map('sanitize_text_field', (array) ($_POST['holidays'] ?? []));
    $holiday_closures = array_map('sanitize_text_field', (array) ($_POST['holiday_closures'] ?? []));
    $special_holidays = array_map('sanitize_text_field', (array) ($_POST['special_holidays'] ?? []));
    $fee      = intval($_POST['nomination_fee'] ?? 0);
    $address  = sanitize_text_field($_POST['address'] ?? '');
    $tel      = sanitize_text_field($_POST['tel'] ?? '');

    // メニュー設定保存
    $menu_names  = $_POST['menu_name'] ?? [];
    $menu_prices = $_POST['menu_price'] ?? [];
    $menus = [];
    foreach ($menu_names as $i => $name) {
      $name = trim(sanitize_text_field($name));
      if ($name === '') continue;
      $menus[] = [
        'name'  => $name,
        'price' => intval($menu_prices[$i] ?? 0),
      ];
    }

    $data = [
      'open_time'        => $open,
      'close_time'       => $close,
      'time_step'        => $step,
      'holidays'         => $holidays,
      'holiday_closures' => $holiday_closures,
      'special_holidays' => $special_holidays,
      'menus'            => $menus,
      'nomination_fee'   => $fee,
      'address'          => $address,
      'tel'              => $tel,
    ];

    update_option('salon_store_settings', $data);
    echo '<div class="notice notice-success is-dismissible"><p>✅ 店舗設定を保存しました。</p></div>';
  }

  // 現在の設定を取得
  $settings = salon_get_store_settings();
  $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
  $api_holidays = salon_get_japan_holidays();
  ?>

  <div class="wrap">
    <h1>店舗設定</h1>
    <form method="post">
      <?php wp_nonce_field('salon_store_save_action'); ?>

      <table class="form-table">
        <tr>
          <th>営業時間</th>
          <td>
            <input type="time" name="open_time" value="<?= esc_attr($settings['open_time']); ?>"> 〜
            <input type="time" name="close_time" value="<?= esc_attr($settings['close_time']); ?>">
          </td>
        </tr>

        <tr>
          <th>予約間隔</th>
          <td>
            <select name="time_step">
              <?php foreach ([15, 30, 45, 60] as $v): ?>
                <option value="<?= $v ?>" <?= selected($settings['time_step'], $v, false) ?>><?= $v ?>分刻み</option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>

        <tr>
          <th>定休日</th>
          <td>
            <?php foreach ($weekdays as $i => $w): ?>
              <label style="margin-right:10px;">
                <input type="checkbox" name="holidays[]" value="<?= $i ?>" <?= checked(in_array((string)$i, (array)$settings['holidays'], true), true, false) ?>> <?= $w ?>曜
              </label>
            <?php endforeach; ?>
          </td>
        </tr>

        <tr>
          <th>祝日個別休業設定</th>
          <td>
            <p class="description">休業にしたい祝日にチェックを入れてください。</p>
            <div style="max-height:300px; overflow-y:auto; border:1px solid #ddd; padding:10px;">
              <?php foreach ($api_holidays as $date => $name): 
                $checked = in_array($date, (array)$settings['holiday_closures'], true);
                $label = date_i18n('Y年n月j日', strtotime($date)) . "（{$name}）";
              ?>
                <label style="display:block; margin-bottom:5px;">
                  <input type="checkbox" name="holiday_closures[]" value="<?= esc_attr($date); ?>" <?= checked($checked, true, false); ?>>
                  <?= esc_html($label); ?>（休業にする）
                </label>
              <?php endforeach; ?>
            </div>
          </td>
        </tr>

        <tr>
          <th>臨時休業日（手動設定）</th>
          <td>
            <div id="special-holiday-list">
              <?php if (!empty($settings['special_holidays'])): ?>
                <?php foreach ($settings['special_holidays'] as $d): ?>
                  <p><input type="date" name="special_holidays[]" value="<?= esc_attr($d); ?>"> <button type="button" class="button remove-date">削除</button></p>
                <?php endforeach; ?>
              <?php else: ?>
                <p><input type="date" name="special_holidays[]" value=""> <button type="button" class="button remove-date">削除</button></p>
              <?php endif; ?>
            </div>
            <button type="button" class="button" id="add-special-date">＋ 日付追加</button>
            <script>
              jQuery(function($){
                $('#add-special-date').on('click', function(){
                  $('#special-holiday-list').append('<p><input type="date" name="special_holidays[]" value=""> <button type="button" class="button remove-date">削除</button></p>');
                });
                $(document).on('click', '.remove-date', function(){
                  $(this).closest('p').remove();
                });
              });
            </script>
          </td>
        </tr>

        <tr>
          <th>指名料</th>
          <td>
            <input type="number" name="nomination_fee" value="<?= esc_attr($settings['nomination_fee']); ?>" min="0" step="100"> 円
            <p class="description">※0円の場合は指名料なしとして扱われます。</p>
          </td>
        </tr>

        <tr>
          <th>住所</th>
          <td><input type="text" name="address" value="<?= esc_attr($settings['address']); ?>" class="regular-text" placeholder="例）岡山県岡山市北区〇〇1-2-3"></td>
        </tr>

        <tr>
          <th>電話番号</th>
          <td><input type="text" name="tel" value="<?= esc_attr($settings['tel']); ?>" class="regular-text" placeholder="例）086-123-4567"></td>
        </tr>

        <tr>
          <th>メニュー設定</th>
          <td>
            <div id="menu-list">
              <?php if (!empty($settings['menus'])): ?>
                <?php foreach ($settings['menus'] as $m): ?>
                  <p>
                    <input type="text" name="menu_name[]" value="<?= esc_attr($m['name']); ?>" placeholder="メニュー名">
                    <input type="number" name="menu_price[]" value="<?= esc_attr($m['price']); ?>" placeholder="金額（円）">
                    <button type="button" class="button remove-menu">削除</button>
                  </p>
                <?php endforeach; ?>
              <?php else: ?>
                <p>
                  <input type="text" name="menu_name[]" placeholder="メニュー名">
                  <input type="number" name="menu_price[]" placeholder="金額（円）">
                  <button type="button" class="button remove-menu">削除</button>
                </p>
              <?php endif; ?>
            </div>
            <button type="button" class="button" id="add-menu-row">＋ メニュー追加</button>
            <script>
              jQuery(function($){
                $('#add-menu-row').on('click', function(){
                  $('#menu-list').append('<p><input type="text" name="menu_name[]" placeholder="メニュー名"> <input type="number" name="menu_price[]" placeholder="金額（円）"> <button type="button" class="button remove-menu">削除</button></p>');
                });
                $(document).on('click', '.remove-menu', function(){
                  $(this).closest('p').remove();
                });
              });
            </script>
          </td>
        </tr>
      </table>

      <?php submit_button('保存', 'primary', 'salon_store_save'); ?>
    </form>
  </div>
  <?php
}

/**
 * 日本の祝日データを取得（内閣府API）
 */
function salon_get_japan_holidays() {
  $url = 'https://holidays-jp.github.io/api/v1/date.json';
  $response = wp_remote_get($url, ['timeout' => 5]);
  if (is_wp_error($response)) return [];
  $json = json_decode(wp_remote_retrieve_body($response), true);
  return is_array($json) ? $json : [];
}

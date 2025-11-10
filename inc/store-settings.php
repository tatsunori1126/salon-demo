<?php
if (!defined('ABSPATH')) exit;

/***********************************************************
 * 店舗設定（営業時間・定休日・メニュー設定）
 ***********************************************************/

/**
 * 店舗設定取得
 */
function salon_get_store_settings() {
  $defaults = [
    'open_time'      => '09:00',
    'close_time'     => '19:30',
    'time_step'      => 30,
    'holidays'       => [],
    'menus'          => [],
    'nomination_fee' => 0,
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

  // ✅ 保存処理
  if (isset($_POST['salon_store_save'])) {
    check_admin_referer('salon_store_save_action');

    $open     = sanitize_text_field($_POST['open_time'] ?? '');
    $close    = sanitize_text_field($_POST['close_time'] ?? '');
    $step     = intval($_POST['time_step'] ?? 30);
    $holidays = array_map('sanitize_text_field', (array) ($_POST['holidays'] ?? []));
    $fee      = intval($_POST['nomination_fee'] ?? 0);

    // ✅ メニュー保存処理
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
      'open_time'      => $open,
      'close_time'     => $close,
      'time_step'      => $step,
      'holidays'       => $holidays,
      'menus'          => $menus,
      'nomination_fee' => $fee,
    ];

    update_option('salon_store_settings', $data);

    echo '<div class="notice notice-success is-dismissible"><p>✅ 店舗設定を保存しました。</p></div>';
  }

  // ✅ 現在の設定を取得
  $settings = salon_get_store_settings();
  $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
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
          <th>指名料</th>
          <td>
            <input type="number" name="nomination_fee" value="<?= esc_attr($settings['nomination_fee'] ?? 0); ?>" min="0" step="100"> 円
            <p class="description">※0円の場合は自動的に指名料なし扱いになります</p>
          </td>
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
                  $('#menu-list').append(
                    '<p><input type="text" name="menu_name[]" placeholder="メニュー名"> '+
                    '<input type="number" name="menu_price[]" placeholder="金額（円）"> '+
                    '<button type="button" class="button remove-menu">削除</button></p>'
                  );
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

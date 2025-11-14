<?php
if (!defined('ABSPATH')) exit;

/***********************************************************
 *  顧客管理システム（1ファイル版）
 *  - カスタム投稿タイプ「顧客管理」
 *  - 予約時の自動登録・更新（来店回数）
 *  - 管理画面カラム追加（電話 / メール / 来店回数 / 最終来店日）
 ***********************************************************/


/**
 * ▼ 顧客管理 CPT 作成
 */
add_action('init', function () {

    register_post_type('customer', [
        'label' => '顧客管理',
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 25,
        'menu_icon' => 'dashicons-id',
        'supports' => ['title'], // 顧客名
    ]);

});


function salon_update_customer_data($name, $tel, $email, $date, $menu, $staff, $auto = 0) {

    // 電話 or メールで既存顧客を検索
    $customers = get_posts([
        'post_type' => 'customer',
        'numberposts' => 1,
        'meta_query' => [
            'relation' => 'OR',
            ['key' => 'tel',   'value' => $tel],
            ['key' => 'email', 'value' => $email],
        ]
    ]);

    // --- 既存顧客 ---
    if ($customers) {

        $cid = $customers[0]->ID;

        $visit = intval(get_post_meta($cid, 'visit_count', true)) + 1;

        update_post_meta($cid, 'visit_count', $visit);
        update_post_meta($cid, 'last_visit_date', $date);
        update_post_meta($cid, 'last_menu', $menu);
        update_post_meta($cid, 'last_staff', $staff);

        // ★予約時に送られてきた auto_assigned を保存
        update_post_meta($cid, 'last_auto_assigned', intval($auto));

        return $cid;
    }

    // --- 新規顧客 ---
    $cid = wp_insert_post([
        'post_type'   => 'customer',
        'post_title'  => $name,
        'post_status' => 'publish',
    ]);

    update_post_meta($cid, 'name',  $name);
    update_post_meta($cid, 'tel',   $tel);
    update_post_meta($cid, 'email', $email);

    update_post_meta($cid, 'visit_count', 1);
    update_post_meta($cid, 'last_visit_date', $date);
    update_post_meta($cid, 'last_menu', $menu);
    update_post_meta($cid, 'last_staff', $staff);
    update_post_meta($cid, 'last_auto_assigned', intval($auto));

    return $cid;
}




/**
 * ▼ 管理画面：顧客一覧のカラム定義
 */
add_filter('manage_customer_posts_columns', function ($columns) {

    $new = [];

    $new['cb'] = $columns['cb'];
    $new['title'] = '顧客名';
    $new['tel'] = '電話番号';
    $new['email'] = 'メール';
    $new['visit_count'] = '来店回数';
    $new['last_visit_date'] = '最終来店日';
    $new['last_menu'] = '最終メニュー';
    $new['last_staff'] = '担当者';
    $new['actions'] = '操作';

    return $new;
});


/**
 * ▼ 日付を「YYYY年MM月DD日」形式に変換
 */
function salon_format_japanese_date($date)
{
    if (!$date) return '';

    $t = strtotime($date);
    return date('Y年n月j日', $t);
}


/**
 * ▼ 管理画面：顧客一覧のカラム表示
 */
add_action('manage_customer_posts_custom_column', function ($column, $post_id) {

    switch ($column) {

        case 'tel':
            echo esc_html(get_post_meta($post_id, 'tel', true));
            break;

        case 'email':
            echo esc_html(get_post_meta($post_id, 'email', true));
            break;

        case 'visit_count':
            $v = intval(get_post_meta($post_id, 'visit_count', true));
            echo $v . " 回";
            break;

        case 'last_visit_date':
            $d = get_post_meta($post_id, 'last_visit_date', true);
            echo salon_format_japanese_date($d);
            break;

        case 'last_menu':
            echo esc_html(get_post_meta($post_id, 'last_menu', true));
            break;

        case 'last_staff':
            $uid  = intval(get_post_meta($post_id, 'last_staff', true));
            $auto = intval(get_post_meta($post_id, 'last_auto_assigned', true));
        
            if ($uid > 0) {
                $u = get_userdata($uid);
                $name = $u ? esc_html($u->display_name) : '不明';
        
                if ($auto === 1) {
                    $name .= '（指名なし）';
                }
                echo $name;
        
            } else {
                echo '指名なし';
            }
            break;
        
        

        case 'actions':
            $edit_url = get_edit_post_link($post_id);
            $del_url  = get_delete_post_link($post_id);

            echo '<a href="'.esc_url($edit_url).'" class="button button-primary" style="margin-right:6px;">編集</a>';
            echo '<a href="'.esc_url($del_url).'" class="button button-secondary" onclick="return confirm(\'削除しますか？\');">削除</a>';
            break;
    }

}, 10, 2);

/**
 * ▼ 顧客名下のデフォルト操作リンク（編集・クイック編集・ゴミ箱）を非表示
 */
add_action('admin_head', function () {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'customer') {
        echo '<style>
            .row-actions { display: none !important; }
        </style>';
    }
});


/**
 * ▼ 顧客詳細画面に「過去の予約履歴」ボックス追加
 */
add_action('add_meta_boxes_customer', function () {

    add_meta_box(
        'customer_reservation_history',
        '過去の予約履歴',
        'salon_customer_reservation_history_box',
        'customer',
        'normal',
        'default'
    );

});

/**
 * ▼ 顧客情報編集フォーム追加
 */
add_action('add_meta_boxes_customer', function () {

    add_meta_box(
        'customer_basic_info',
        '顧客情報を編集',
        'salon_customer_basic_info_box',
        'customer',
        'normal',
        'high'
    );

});

function salon_customer_basic_info_box($post) {

    $name  = get_post_meta($post->ID, 'name', true);
    $tel   = get_post_meta($post->ID, 'tel', true);
    $email = get_post_meta($post->ID, 'email', true);

    wp_nonce_field('salon_customer_edit_nonce', 'salon_customer_edit_nonce_field');
    ?>

    <table class="form-table">
        <tr>
            <th><label>顧客名</label></th>
            <td><input type="text" name="customer_name" value="<?php echo esc_attr($name); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label>電話番号</label></th>
            <td><input type="text" name="customer_tel" value="<?php echo esc_attr($tel); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label>メールアドレス</label></th>
            <td><input type="text" name="customer_email" value="<?php echo esc_attr($email); ?>" class="regular-text"></td>
        </tr>
    </table>

    <?php
}


function salon_customer_reservation_history_box($post) {

    $tel   = get_post_meta($post->ID, 'tel', true);
    $email = get_post_meta($post->ID, 'email', true);

    if (!$tel && !$email) {
        echo '顧客情報がありません。';
        return;
    }

    // この顧客の予約を取得（新しい順）
    $reservations = get_posts([
        'post_type'   => 'reservation',
        'numberposts' => -1,
        'orderby'     => 'meta_value',
        'order'       => 'DESC',
        'meta_key'    => 'res_datetime',
        'meta_query'  => [
            'relation' => 'OR',
            ['key' => 'res_tel', 'value' => $tel],
            ['key' => 'res_email', 'value' => $email],
        ]
    ]);

    if (!$reservations) {
        echo '予約履歴はありません。';
        return;
    }

    // 来店回数
    $visit_total = intval(get_post_meta($post->ID, 'visit_count', true));
    $current_visit = $visit_total;

    // --- CSV 出力ボタン ---
    $csv_url = admin_url('admin-ajax.php?action=customer_reservation_csv&customer_id=' . $post->ID);
    echo '<p style="text-align:right;">
            <a href="'.esc_url($csv_url).'" class="button button-primary">CSVダウンロード</a>
          </p>';

    echo '<table class="widefat"><thead><tr>
            <th>来店回数</th>
            <th>来店日時</th>
            <th>時間</th>
            <th>メニュー</th>
            <th>担当者</th>
            <th>合計金額</th>
          </tr></thead><tbody>';

    foreach ($reservations as $r) {

        $date_raw  = get_post_meta($r->ID, 'res_date', true);
        $time      = get_post_meta($r->ID, 'res_time', true);
        $menu      = get_post_meta($r->ID, 'res_menu', true);
        $staff_id  = intval(get_post_meta($r->ID, 'res_staff', true));
        $auto      = intval(get_post_meta($r->ID, 'res_auto_assigned', true));
        $total_raw = get_post_meta($r->ID, 'res_total_price', true);

        // 日付
        $date_jp = date('Y年n月j日', strtotime($date_raw));

        // 担当者名
        if ($staff_id > 0) {
            $user = get_userdata($staff_id);
            $staff_name = $user ? $user->display_name : '不明';
            if ($auto === 1) {
                $staff_name .= '（指名なし）';
            }
        } else {
            $staff_name = '指名なし';
        }

        // 金額
        $total = ($total_raw === '' || $total_raw === null)
            ? '-'
            : number_format(intval($total_raw)) . '円';

        // 来店回数
        $visit_label = $current_visit . ' 回目';

        echo "<tr>
                <td>{$visit_label}</td>
                <td>{$date_jp}</td>
                <td>{$time}</td>
                <td>{$menu}</td>
                <td>{$staff_name}</td>
                <td>{$total}</td>
              </tr>";

        $current_visit--;
    }

    echo '</tbody></table>';
}



/**
 * ▼ 顧客詳細画面に LTV（生涯売上）表示
 */
add_action('add_meta_boxes_customer', function () {

    add_meta_box(
        'customer_ltv_box',
        '生涯売上（LTV）',
        'salon_customer_ltv_box',
        'customer',
        'side',
        'high'
    );

});

function salon_customer_ltv_box($post) {

    $tel   = get_post_meta($post->ID, 'tel', true);
    $email = get_post_meta($post->ID, 'email', true);

    $reservations = get_posts([
        'post_type'   => 'reservation',
        'numberposts' => -1,
        'meta_query'  => [
            'relation' => 'OR',
            ['key' => 'res_tel', 'value' => $tel],
            ['key' => 'res_email', 'value' => $email]
        ]
    ]);

    $total_sales = 0;

    foreach ($reservations as $r) {
        $price = intval(get_post_meta($r->ID, 'res_total_price', true));
        $total_sales += $price;
    }

    echo "<p><strong>" . number_format($total_sales) . " 円</strong></p>";
}


/**
 * ▼ 顧客ランク（VIP / ゴールド / 一般）自動判定
 */
add_action('add_meta_boxes_customer', function () {

    add_meta_box(
        'customer_rank_box',
        '顧客ランク',
        'salon_customer_rank_box',
        'customer',
        'side',
        'high'
    );

});

function salon_customer_rank_box($post) {

    $visit = intval(get_post_meta($post->ID, 'visit_count', true));

    // LTV計算
    $tel   = get_post_meta($post->ID, 'tel', true);
    $email = get_post_meta($post->ID, 'email', true);
    $reservations = get_posts([
        'post_type'   => 'reservation',
        'numberposts' => -1,
        'meta_query'  => [
            'relation' => 'OR',
            ['key' => 'res_tel', 'value' => $tel],
            ['key' => 'res_email', 'value' => $email]
        ]
    ]);

    $total_sales = 0;
    foreach ($reservations as $r) {
        $total_sales += intval(get_post_meta($r->ID, 'res_total_price', true));
    }

    // ランク判定
    if ($visit >= 10 && $total_sales >= 100000) {
        $rank = 'VIP';
    } elseif ($visit >= 5 && $total_sales >= 50000) {
        $rank = 'ゴールド';
    } else {
        $rank = '一般';
    }

    echo "<p><strong>{$rank}</strong></p>";
}


/**
 * ▼ 顧客情報編集フォーム保存処理
 */
add_action('save_post_customer', function($post_id) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (!isset($_POST['salon_customer_edit_nonce_field']) ||
        !wp_verify_nonce($_POST['salon_customer_edit_nonce_field'], 'salon_customer_edit_nonce')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['customer_name'])) {
        update_post_meta($post_id, 'name', sanitize_text_field($_POST['customer_name']));
    }

    if (isset($_POST['customer_tel'])) {
        update_post_meta($post_id, 'tel', sanitize_text_field($_POST['customer_tel']));
    }

    if (isset($_POST['customer_email'])) {
        update_post_meta($post_id, 'email', sanitize_email($_POST['customer_email']));
    }
});


/**
 * ▼ 顧客管理：タイトル入力欄を非表示にする
 */
add_action('admin_head', function () {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'customer') {
        echo '<style>
            #titlediv, 
            #titlewrap, 
            #edit-slug-box {
                display: none !important;
            }
        </style>';
    }
});

/**
 * ▼ 顧客管理：タイトルを自動生成（必須フィールド対策）
 */
add_filter('wp_insert_post_data', function ($data, $postarr) {
    if ($data['post_type'] === 'customer') {

        // 顧客名をタイトルに自動同期
        $name = $_POST['customer_name'] ?? '';
        if ($name) {
            $data['post_title'] = sanitize_text_field($name);
        }
    }
    return $data;
}, 10, 2);


/**
 * ▼ 予約編集時に顧客の最終担当者も更新する
 */
add_action('save_post_reservation', function ($post_id, $post, $update) {

    if (!$update) return;

    $tel   = get_post_meta($post_id, 'res_tel', true);
    $email = get_post_meta($post_id, 'res_email', true);

   if (!$tel && !$email) return;

    $customers = get_posts([
        'post_type'   => 'customer',
        'numberposts' => 1,
        'meta_query'  => [
            'relation' => 'OR',
            ['key' => 'tel', 'value' => $tel],
            ['key' => 'email', 'value' => $email]
        ]
    ]);

    if (!$customers) return;
    $cid = $customers[0]->ID;

    $staff = intval(get_post_meta($post_id, 'res_staff', true));
    $auto  = intval(get_post_meta($post_id, 'res_auto_assigned', true));
    $date  = get_post_meta($post_id, 'res_date', true);
    $menu  = get_post_meta($post_id, 'res_menu', true);

    update_post_meta($cid, 'last_staff', $staff);
    update_post_meta($cid, 'last_auto_assigned', $auto);
    update_post_meta($cid, 'last_visit_date', $date);
    update_post_meta($cid, 'last_menu', $menu);

}, 10, 3);

/**
 * ▼ CSV出力処理（Shift_JIS で文字化け防止版）
 */
add_action('wp_ajax_customer_reservation_csv', function () {

    if (!current_user_can('edit_posts')) {
        wp_die('権限がありません。');
    }

    $cid = intval($_GET['customer_id'] ?? 0);
    if (!$cid) wp_die('顧客IDが不正です。');

    $tel   = get_post_meta($cid, 'tel', true);
    $email = get_post_meta($cid, 'email', true);

    $reservations = get_posts([
        'post_type'   => 'reservation',
        'numberposts' => -1,
        'orderby'     => 'meta_value',
        'order'       => 'DESC',
        'meta_key'    => 'res_datetime',
        'meta_query'  => [
            'relation' => 'OR',
            ['key' => 'res_tel', 'value' => $tel],
            ['key' => 'res_email', 'value' => $email],
        ]
    ]);

    // ▼ Excel文字化け防止 → Shift_JIS 出力
    header('Content-Type: text/csv; charset=Shift_JIS');
    header('Content-Disposition: attachment; filename=customer_history_' . $cid . '.csv');

    $output = fopen('php://output', 'w');

    // UTF-8 → Shift_JIS に変換する関数
    function sjis_array($arr) {
        return array_map(function($v){
            return mb_convert_encoding($v, 'SJIS-win', 'UTF-8');
        }, $arr);
    }

    // ▼ ヘッダー
    fputcsv($output, sjis_array(['来店回数', '日付', '時間', 'メニュー', '担当者', '合計金額']));

    $visit_total = intval(get_post_meta($cid, 'visit_count', true));
    $current_visit = $visit_total;

    foreach ($reservations as $r) {

        $date  = get_post_meta($r->ID, 'res_date', true);
        $time  = get_post_meta($r->ID, 'res_time', true);
        $menu  = get_post_meta($r->ID, 'res_menu', true);
        $staff_id = intval(get_post_meta($r->ID, 'res_staff', true));
        $auto     = intval(get_post_meta($r->ID, 'res_auto_assigned', true));
        $total    = get_post_meta($r->ID, 'res_total_price', true);

        if ($staff_id > 0) {
            $user = get_userdata($staff_id);
            $staff_name = $user ? $user->display_name : '不明';
            if ($auto === 1) {
                $staff_name .= '（指名なし）';
            }
        } else {
            $staff_name = '指名なし';
        }

        fputcsv($output, sjis_array([
            $current_visit . '回目',
            $date,
            $time,
            $menu,
            $staff_name,
            $total
        ]));

        $current_visit--;
    }

    fclose($output);
    exit;
});

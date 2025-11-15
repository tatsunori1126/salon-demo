<?php
if (!defined('ABSPATH')) exit;

/********************************************************************
 * 📩 サロン予約：来店前リマインド & 来店後フォロー（メール送信）
 ********************************************************************/

/********************************************************************
 * 🔍 デバッグログ関数
 ********************************************************************/
function salon_log_reminder($msg) {
    error_log("🟩 REMINDER: " . $msg);
}


/********************************************************************
 * ① 管理画面に「リマインド設定」追加
 ********************************************************************/
add_action('admin_menu', function () {
    add_menu_page(
        'リマインド設定',
        'リマインド設定',
        'manage_options',
        'salon-reminder-settings',
        'salon_reminder_settings_page',
        'dashicons-email-alt',
        26
    );
});


/********************************************************************
 * ② 設定ページ HTML
 ********************************************************************/
function salon_reminder_settings_page()
{
    if (!empty($_POST['salon_reminder_save'])) {

        update_option('salon_reminder_before_days', intval($_POST['rem_before_days']));
        update_option('salon_reminder_before_time', sanitize_text_field($_POST['rem_before_time']));
        update_option('salon_reminder_before_msg', wp_kses_post($_POST['rem_before_msg']));

        update_option('salon_follow_days', intval($_POST['follow_days']));
        update_option('salon_follow_time', sanitize_text_field($_POST['follow_time']));
        update_option('salon_follow_msg', wp_kses_post($_POST['follow_msg']));

        echo '<div class="updated"><p>保存しました。</p></div>';
    }

    $before_days = get_option('salon_reminder_before_days', 1);
    $before_time = get_option('salon_reminder_before_time', '10:00');
    $before_msg  = get_option('salon_reminder_before_msg', '');

    $follow_days = get_option('salon_follow_days', 3);
    $follow_time = get_option('salon_follow_time', '10:00');
    $follow_msg  = get_option('salon_follow_msg', '');
    ?>

    <div class="wrap">
        <h1>リマインド設定</h1>
        <form method="post">

            <h2>📩 来店前リマインド</h2>
            <table class="form-table">
                <tr>
                    <th>送信タイミング</th>
                    <td>
                        来店 <input type="number" name="rem_before_days"
                                     value="<?php echo esc_attr($before_days); ?>"
                                     min="0" style="width:70px;"> 日前
                        の <input type="time" name="rem_before_time"
                                  value="<?php echo esc_attr($before_time); ?>">
                    </td>
                </tr>
                <tr>
                    <th>追加メッセージ</th>
                    <td>
                        <textarea name="rem_before_msg" rows="4" class="large-text"><?php
                            echo esc_textarea($before_msg); ?></textarea>
                        <p>※ 氏名・日時などの基本文は自動挿入されます</p>
                    </td>
                </tr>
            </table>

            <h2>📩 来店後フォローメール</h2>
            <table class="form-table">
                <tr>
                    <th>送信タイミング</th>
                    <td>
                        来店 <input type="number" name="follow_days"
                                     value="<?php echo esc_attr($follow_days); ?>"
                                     min="0" style="width:70px;"> 日後
                        の <input type="time" name="follow_time"
                                  value="<?php echo esc_attr($follow_time); ?>">
                    </td>
                </tr>
                <tr>
                    <th>追加メッセージ</th>
                    <td>
                        <textarea name="follow_msg" rows="4" class="large-text"><?php
                            echo esc_textarea($follow_msg); ?></textarea>
                        <p>※ 基本文は自動挿入されます</p>
                    </td>
                </tr>
            </table>

            <p><input type="submit" name="salon_reminder_save"
                      class="button button-primary" value="保存する"></p>
        </form>
    </div>

<?php
}


/********************************************************************
 * ③ WP-Cron（毎分） — 正しい登録
 ********************************************************************/
add_filter('cron_schedules', function ($schedules) {
    $schedules['minute'] = [
        'interval' => 60,
        'display'  => '毎分'
    ];
    return $schedules;
});

add_action('plugins_loaded', function () {

    $next = wp_next_scheduled('salon_reminder_cron');

    if (!$next) {
        wp_schedule_event(time(), 'minute', 'salon_reminder_cron');
        error_log("🟩 Cron 初回登録: salon_reminder_cron");
    } else {
        error_log("🔧 Cron 既存: " . date('Y-m-d H:i:s', $next));
    }
});


/********************************************************************
 * 🆕 即時送信：設定時刻を過ぎて予約が作られた場合
 * （meta 更新時に確実に発火する）
 ********************************************************************/
add_action('updated_post_meta', function($meta_id, $post_id, $meta_key, $meta_value){

    if (get_post_type($post_id) !== 'reservation') return;

    if ($meta_key !== 'res_date' && $meta_key !== 'res_time') return;

    // すでに送信済み？
    if (get_post_meta($post_id, 'reminder_before_sent', true)) return;

    $date = get_post_meta($post_id, 'res_date', true);
    $time = get_post_meta($post_id, 'res_time', true);

    if (!$date || !$time) return;

    $before_days = get_option('salon_reminder_before_days', 1);
    $before_time = get_option('salon_reminder_before_time', '10:00');
    $before_msg  = get_option('salon_reminder_before_msg', '');

    // 送信予定時刻
    $raw_ts  = strtotime("$date $before_time");
    $send_ts = $raw_ts - ($before_days * DAY_IN_SECONDS);
    $now_ts  = current_time('timestamp');

    salon_log_reminder("⏱ 即時送信チェック(meta): ID={$post_id} send=" . date('Y-m-d H:i', $send_ts) . " now=" . date('Y-m-d H:i', $now_ts));

    if ($now_ts >= $send_ts) {
        salon_log_reminder("🚀 即時送信(meta) → ID={$post_id}");
        salon_send_reminder_mail($post_id, $before_msg, 'before');
        update_post_meta($post_id, 'reminder_before_sent', 1);
    }

}, 10, 4);



/********************************************************************
 * ④ Cron 処理（完全安定版）
 ********************************************************************/
add_action('salon_reminder_cron', function () {

    error_log("🔔 CRON 発火: " . current_time('mysql'));

    if (get_transient('salon_cron_lock')) {
        error_log("🛑 Cron locked → 二重起動防止");
        return;
    }
    set_transient('salon_cron_lock', 1, 30);

    global $wpdb;

    $now_ts = current_time('timestamp');
    $now = date('Y-m-d H:i', $now_ts);
    salon_log_reminder("Cron 処理開始 now={$now}");

    // 設定
    $before_days = get_option('salon_reminder_before_days', 1);
    $before_time = get_option('salon_reminder_before_time', '10:00');
    $before_msg  = get_option('salon_reminder_before_msg', '');

    $follow_days = get_option('salon_follow_days', 3);
    $follow_time = get_option('salon_follow_time', '10:00');
    $follow_msg  = get_option('salon_follow_msg', '');


    /****************************************
     * ▼ 来店前リマインド
     ****************************************/
    $before_ids = $wpdb->get_col("
        SELECT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} d
            ON p.ID = d.post_id AND d.meta_key='res_date'
        WHERE p.post_type='reservation'
          AND p.post_status='publish'
    ");

    foreach ($before_ids as $res_id) {

        if (get_post_meta($res_id, 'reminder_before_sent', true))
            continue;

        $date = get_post_meta($res_id, 'res_date', true);

        $raw_ts  = strtotime("$date $before_time");
        $send_ts = $raw_ts - ($before_days * DAY_IN_SECONDS);

        salon_log_reminder("CHECK before: ID={$res_id} send=" . date('Y-m-d H:i', $send_ts));

        if ($now_ts >= $send_ts) {
            salon_log_reminder("🔥 来店前メール送信 → ID={$res_id}");
            salon_send_reminder_mail($res_id, $before_msg, 'before');
            update_post_meta($res_id, 'reminder_before_sent', 1);
        }
    }


    /****************************************
     * ▼ 来店後フォロー
     ****************************************/
    $after_ids = $wpdb->get_col("
        SELECT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} d
            ON p.ID = d.post_id AND d.meta_key='res_date'
        WHERE p.post_type='reservation'
          AND p.post_status='publish'
    ");

    foreach ($after_ids as $res_id) {

        if (get_post_meta($res_id, 'reminder_after_sent', true))
            continue;

        $date = get_post_meta($res_id, 'res_date', true);

        $raw_ts  = strtotime("$date $follow_time");
        $send_ts = $raw_ts + ($follow_days * DAY_IN_SECONDS);

        salon_log_reminder("CHECK after: ID={$res_id} send=" . date('Y-m-d H:i', $send_ts));

        if ($now_ts >= $send_ts) {
            salon_log_reminder("🔥 来店後メール送信 → ID={$res_id}");
            salon_send_reminder_mail($res_id, $follow_msg, 'after');
            update_post_meta($res_id, 'reminder_after_sent', 1);
        }
    }

    delete_transient('salon_cron_lock');
});


/********************************************************************
 * ⑤ メール送信本体（日本語日付 + 店舗情報つき）
 ********************************************************************/
function salon_send_reminder_mail($res_id, $extra_msg, $type = 'before')
{
    // 店舗設定から取得（要：store_name / store_address / store_tel）
    $store = salon_get_store_settings();
    $store_name    = $store['store_name']    ?? get_bloginfo('name');
    $store_address = $store['address'] ?? '';
    $store_tel     = $store['tel']     ?? '';

    // 予約情報
    $name  = get_post_meta($res_id, 'res_name', true);
    $email = get_post_meta($res_id, 'res_email', true);
    $date  = get_post_meta($res_id, 'res_date', true);
    $time  = get_post_meta($res_id, 'res_time', true);
    $menu  = get_post_meta($res_id, 'res_menu', true);

    if (!$email) {
        salon_log_reminder("⚠ メールなし → ID={$res_id}");
        return;
    }

    /****************************************************
     * ▼ 日付を日本語表記に変換
     *   2025-11-16 → 2025年11月16日（日）
     ****************************************************/
    $timestamp = strtotime($date);
    $w = ['日','月','火','水','木','金','土'];
    $weekday = $w[ date('w', $timestamp) ];

    $jp_date = date('Y年n月j日', $timestamp) . "（{$weekday}）";


    /****************************************************
     * ▼ メールヘッダー
     ****************************************************/
    $site = $store_name;
    $from = get_option('admin_email');
    $headers = ["From: {$site} <{$from}>"];


    /****************************************************
     * ▼ メール本文（来店前 / 来店後で分岐）
     ****************************************************/
    if ($type === 'before') {
        $subject = "【ご予約の確認】{$site}";
        $body = "{$name} 様\n\n"
            . "ご予約日時が近づいてまいりましたのでご連絡いたします。\n\n"
            . "【ご予約内容】\n"
            . "日時：{$jp_date} {$time}\n"
            . "メニュー：{$menu}\n\n";
    } else {
        $subject = "【ご来店ありがとうございました】{$site}";
        $body = "{$name} 様\n\n"
            . "先日はご来店いただきありがとうございました。\n"
            . "その後の髪の調子はいかがでしょうか？\n\n"
            . "【今回のメニュー】\n"
            . "{$menu}\n\n";
    }

    /****************************************************
     * ▼ 管理画面で設定した追加メッセージ
     ****************************************************/
    if ($extra_msg) {
        $body .= "{$extra_msg}\n\n";
    }

    /****************************************************
     * ▼ メール末尾：店舗情報を追加
     ****************************************************/
    $body .= "────────────────────\n";
    $body .= "{$store_name}\n";
    if ($store_address) $body .= "住所：{$store_address}\n";
    if ($store_tel)     $body .= "TEL：{$store_tel}\n";
    $body .= "────────────────────\n";


    /****************************************************
     * ▼ 送信
     ****************************************************/
    salon_log_reminder("📨 メール送信 → {$email} / {$subject}");
    wp_mail($email, $subject, $body, $headers);
}

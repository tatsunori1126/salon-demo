<?php
if (!defined('ABSPATH')) exit;

/***********************************************************
 * メール送信関連関数
 * salon_send_reservation_mail()
 ***********************************************************/

/**
 * ▼ 予約完了時の通知メール送信
 * - 管理者宛：新規予約通知
 * - ユーザー宛：予約確認メール
 */
if (!function_exists('salon_send_reservation_mail')) {
  function salon_send_reservation_mail($post_id) {
    if (!$post_id) return;

    $admin = get_option('admin_email');
    $site  = get_bloginfo('name');
    $to_user = get_post_meta($post_id, 'res_email', true);
    $name = get_post_meta($post_id, 'res_name', true);
    $date = get_post_meta($post_id, 'res_date', true);
    $time = get_post_meta($post_id, 'res_time', true);
    $menu = get_post_meta($post_id, 'res_menu', true);
    $staff_id = get_post_meta($post_id, 'res_staff', true);
    $staff_name = ($staff_id > 0 && $u = get_userdata($staff_id)) ? $u->display_name : '指名なし';
    $tel = get_post_meta($post_id, 'res_tel', true);

    // ===== 件名 =====
    $subject_admin = "【{$site}】新規予約が入りました";
    $subject_user  = "【{$site}】ご予約ありがとうございます";

    // ===== 管理者向け本文 =====
    $body_admin = <<<EOM
    以下の内容で新規予約が入りました。

    ■ お名前：{$name}
    ■ 日時：{$date} {$time}
    ■ メニュー：{$menu}
    ■ 担当：{$staff_name}
    ■ 電話番号：{$tel}
    ■ メール：{$to_user}
    EOM;

    // ===== ユーザー向け本文 =====
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

    // ===== メール送信 =====
    wp_mail($admin, $subject_admin, $body_admin);

    if ($to_user) {
      wp_mail($to_user, $subject_user, $body_user);
    }
  }
}

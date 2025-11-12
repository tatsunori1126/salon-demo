<?php
if (!defined('ABSPATH')) exit;

/***********************************************************
 * メール送信関連関数（拡張版）
 * salon_send_reservation_mail()
 * - 差出人名をサイト名に自動設定
 * - 日時を日本語フォーマットで表示
 * - 店舗住所・電話番号をフッターに自動挿入
 ***********************************************************/

/**
 * ✉️ 差出人名を「サイト名（WordPress設定）」から自動取得
 */
add_filter('wp_mail_from_name', function($name) {
  return get_bloginfo('name'); // 管理画面の「設定 → 一般 → サイトのタイトル」
});


/**
 * ▼ 予約完了時の通知メール送信
 * - 管理者宛：新規予約通知
 * - ユーザー宛：予約確認メール
 */
if (!function_exists('salon_send_reservation_mail')) {
  function salon_send_reservation_mail($post_id) {
    if (!$post_id) return;

    // 店舗情報を取得（住所・電話も含む）
    $store = function_exists('salon_get_store_settings') ? salon_get_store_settings() : [];
    $address = $store['address'] ?? '';
    $tel_store = $store['tel'] ?? '';

    // 管理者・ユーザー情報
    $admin = get_option('admin_email');
    $site  = get_bloginfo('name');
    $to_user = get_post_meta($post_id, 'res_email', true);
    $name = get_post_meta($post_id, 'res_name', true);
    $date = get_post_meta($post_id, 'res_date', true);
    $time = get_post_meta($post_id, 'res_time', true);
    $menu = get_post_meta($post_id, 'res_menu', true);
    $staff_id = get_post_meta($post_id, 'res_staff', true);
    $staff_name = ($staff_id > 0 && $u = get_userdata($staff_id)) ? $u->display_name : '指名なし';
    $tel_user = get_post_meta($post_id, 'res_tel', true);

    // ===== 日付フォーマット変換 =====
    $timestamp = strtotime("{$date} {$time}");
    $formatted_date = date_i18n('Y年n月j日 H:i', $timestamp);

    // ===== 件名 =====
    $subject_admin = "新規予約が入りました";
    $subject_user  = "ご予約ありがとうございます";

    // ===== 管理者向け本文 =====
    $body_admin = <<<EOM
以下の内容で新規予約が入りました。

■ お名前：{$name}
■ 日時：{$formatted_date}
■ メニュー：{$menu}
■ 担当：{$staff_name}
■ 電話番号：{$tel_user}
■ メール：{$to_user}

────────────────────
{$site}
EOM;

    // 店舗情報があれば追記
    if ($address) $body_admin .= "\n住所：{$address}";
    if ($tel_store) $body_admin .= "\nTEL：{$tel_store}";

    // ===== ユーザー向け本文 =====
    $body_user = <<<EOM
{$name} 様

このたびはご予約いただきありがとうございます。
以下の内容で承りました。

■ 日時：{$formatted_date}
■ メニュー：{$menu}
■ 担当：{$staff_name}

当日はお気をつけてお越しくださいませ。
キャンセルや変更がある場合はご連絡ください。

────────────────────
{$site}
EOM;

    if ($address) $body_user .= "\n住所：{$address}";
    if ($tel_store) $body_user .= "\nTEL：{$tel_store}";

    // ===== メール送信 =====
    wp_mail($admin, $subject_admin, $body_admin);
    if ($to_user) {
      wp_mail($to_user, $subject_user, $body_user);
    }
  }
}

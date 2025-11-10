<?php
/**
 * Template Name: 予約完了ページ
 */
get_header();
?>

<main class="p-reservation__thanks l-inner">
  <section class="p-reservation__thanks-section">
    <div class="p-reservation__thanks-inner">
      <h1 class="p-reservation__thanks-title">ご予約が完了いたしました</h1>

      <p class="p-reservation__thanks-text">
        ご予約内容の確認メールをお送りいたしました。<br>
        当日お会いできるのをスタッフ一同、心よりお待ちしております。
      </p>

      <div class="p-reservation__thanks-btn-wrapper">
        <a href="<?php echo esc_url( home_url('/reservation') ); ?>" class="p-reservation__thanks-btn">
          カレンダーに戻る
        </a>
      </div>
    </div>
  </section>
</main>

<?php get_footer(); ?>

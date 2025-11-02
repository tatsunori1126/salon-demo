<?php get_header(); ?>
<main class="l-main p-main">
    <div class="error-404-container">
        <div class="error-404-box">
            <?php
            if(!is_front_page()):
            ?>
                <div class="breadcrumbs" typeof="BreadcrumbList" vocab="https://schema.org/">
                    <?php if(function_exists('bcn_display'))
                    {
                        bcn_display();
                    }?>
                </div>
            <?php
            endif;
            ?>
            <h1 class="error-404-title">404 NOT FOUND.</h1>
            <p class="error-404-text">お探しのページが見つかりませんでした。</p>
        </div>
        <a href="<?php echo esc_url( home_url('/')); ?>" class="c-btn">
            <span class="c-btn__text">Home</span>
            <span class="c-btn__bg"></span>
            <span class="c-btn__circle">
                <span class="c-btn__arrow">→</span>
            </span>
        </a>
    </div>
</main>
<?php get_footer(); ?>
<?php
/** Shared layout for the pages mirrored from the church's previous site. */
$path = trim((string) get_page_uri(get_queried_object_id()), '/');
if ($path === 'pages') {
    wp_safe_redirect(home_url('/pages/watch-live/'), 301);
    exit;
}
if ($path === 'livestreams') {
    wp_safe_redirect(home_url('/live/'), 301);
    exit;
}
$pages = require get_template_directory() . '/pages/data.php';
$page = $pages[$path] ?? null;
$theme = get_template_directory_uri();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#14213D">
    <link rel="icon" href="<?php echo esc_url($theme . '/assets/arrow-logo.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo esc_url($theme . '/styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($theme . '/pages.css'); ?>">
    <?php wp_head(); ?>
</head>
<body <?php body_class('arrow-subpage'); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Skip to content</a>
<header class="header" id="top">
    <div class="container header-inner">
        <a class="logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="The Arrow Church home"><img src="<?php echo esc_url($theme . '/assets/arrow-logo.png'); ?>" alt="The Arrow Church"></a>
        <button class="menu-toggle" type="button" aria-controls="main-nav" aria-expanded="false" aria-label="Open menu"><span></span><span></span></button>
        <nav id="main-nav" aria-label="Main navigation">
            <a href="<?php echo esc_url(home_url('/about/')); ?>">ABOUT</a>
            <a href="<?php echo esc_url(home_url('/ministries/')); ?>">MINISTRIES</a>
            <a href="<?php echo esc_url(home_url('/events/')); ?>">EVENTS</a>
            <a href="<?php echo esc_url(home_url('/live/')); ?>">SERMONS</a>
            <a href="<?php echo esc_url(home_url('/pages/watch-live/')); ?>">WATCH LIVE</a>
            <a href="<?php echo esc_url(home_url('/contact/')); ?>">CONTACT</a>
            <a href="<?php echo esc_url(home_url('/give/')); ?>">GIVE</a>
            <a class="nav-button" href="<?php echo esc_url(home_url('/#visit')); ?>">PLAN A VISIT <span>↗</span></a>
        </nav>
    </div>
</header>
<main id="main">
    <?php if ($page) : ?>
        <section class="subhero" style="--subhero-image:url('<?php echo esc_url($theme . '/assets/' . $page['image']); ?>')">
            <div class="container subhero-inner">
                <p class="overline light"><span class="overline-mark">✳</span> <?php echo esc_html($page['label']); ?></p>
                <h1><?php echo esc_html($page['title']); ?></h1>
                <p><?php echo esc_html($page['intro']); ?></p>
            </div>
        </section>
        <?php require get_template_directory() . '/pages/content.php'; ?>
    <?php else : ?>
        <section class="subhero" style="--subhero-image:url('<?php echo esc_url($theme . '/assets/arrow-home.jpg'); ?>')"><div class="container subhero-inner"><p class="overline light"><span class="overline-mark">✳</span> THE ARROW CHURCH</p><h1><?php the_title(); ?></h1></div></section>
        <section class="section"><div class="container article-body"><?php while (have_posts()) : the_post(); the_content(); endwhile; ?></div></section>
    <?php endif; ?>
</main>
<footer class="footer"><div class="container"><div class="footer-main">
    <img src="<?php echo esc_url($theme . '/assets/arrow-logo.png'); ?>" alt="The Arrow Church">
    <div><p>FOLLOW ALONG</p><a href="https://www.instagram.com/the.arrow.church/">Instagram ↗</a><a href="https://www.youtube.com/@thearrowchurch">YouTube ↗</a><a href="https://www.facebook.com/profile.php?id=100081125124651">Facebook ↗</a></div>
    <div><p>EXPLORE</p><a href="<?php echo esc_url(home_url('/about/')); ?>">About ↗</a><a href="<?php echo esc_url(home_url('/about/our-team/')); ?>">Our Team ↗</a><a href="<?php echo esc_url(home_url('/ministries/')); ?>">Ministries ↗</a><a href="<?php echo esc_url(home_url('/events/')); ?>">Events ↗</a><a href="<?php echo esc_url(home_url('/live/')); ?>">Sermons ↗</a><a href="<?php echo esc_url(home_url('/pages/watch-live/')); ?>">Watch Live ↗</a><a href="<?php echo esc_url(home_url('/contact/')); ?>">Contact ↗</a><a href="<?php echo esc_url(home_url('/give/')); ?>">Give ↗</a></div>
    <div><p>VISIT</p><a href="https://maps.app.goo.gl/62ZYeJGdf3Qw7sJn7">2931 Lucerne Dr, Janesville, WI 53545 ↗</a><a href="tel:16087744837">(608) 774-4837 ↗</a></div>
</div><div class="footer-bottom"><span>© <span id="year"></span> THE ARROW CHURCH BILINGUAL MINISTRY</span><a href="#top">BACK TO TOP ↑</a></div></div></footer>
<script src="<?php echo esc_url($theme . '/script.js'); ?>"></script>
<?php wp_footer(); ?>
</body></html>

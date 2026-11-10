<?php
/** Shared layout for the pages mirrored from the church's previous site. */
$path = trim((string) get_page_uri(get_queried_object_id()), '/');
$stream_id = (string) get_query_var('arrow_stream_id');
if ($stream_id === '1' || $stream_id === '2') {
    $path = 'livestreams/' . $stream_id;
}
if ($path === 'pages') {
    wp_safe_redirect(home_url('/pages/watch-live/'), 301);
    exit;
}
if ($path === 'livestreams') {
    wp_safe_redirect(home_url('/live/'), 301);
    exit;
}
$arrow_pages = require get_template_directory() . '/pages/data.php';
$arrow_page = $arrow_pages[$path] ?? null;
$theme = get_template_directory_uri();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#14213D">
    <?php if ($arrow_page) : ?><meta name="description" content="<?php echo esc_attr($arrow_page['intro']); ?>"><?php endif; ?>
    <link rel="icon" href="<?php echo esc_url(arrow_photo_url('arrow-logo.png')); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo esc_url($theme . '/styles.css?ver=' . filemtime(get_template_directory() . '/styles.css')); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($theme . '/pages.css?ver=' . filemtime(get_template_directory() . '/pages.css')); ?>">
    <?php if ($path === 'about/our-team') : ?><link rel="stylesheet" href="<?php echo esc_url($theme . '/staff.css?ver=' . filemtime(get_template_directory() . '/staff.css')); ?>"><?php endif; ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class('arrow-subpage'); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Skip to content</a>
<header class="header" id="top">
    <div class="container header-inner">
        <a class="logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="The Arrow Church home"><img src="<?php echo esc_url(arrow_photo_url('arrow-logo.png')); ?>" alt="The Arrow Church"></a>
        <button class="menu-toggle" type="button" aria-controls="main-nav" aria-expanded="false" aria-label="Open menu"><span></span><span></span></button>
        <nav id="main-nav" aria-label="Main navigation">
            <a href="<?php echo esc_url(home_url('/about/')); ?>">ABOUT</a>
            <a href="<?php echo esc_url(home_url('/ministries/')); ?>">MINISTRIES</a>
            <a href="<?php echo esc_url(home_url('/events/')); ?>">EVENTS</a>
            <a href="<?php echo esc_url(home_url('/about/our-team/')); ?>">STAFF</a>
            <a href="<?php echo esc_url(home_url('/pages/watch-live/')); ?>">WATCH LIVE</a>
            <a href="<?php echo esc_url(home_url('/contact/')); ?>">CONTACT</a>
            <a href="<?php echo esc_url(home_url('/give/')); ?>">GIVE</a>
            <a class="nav-button" href="<?php echo esc_url(home_url('/#visit')); ?>">PLAN A VISIT <span class="arrow-icon arrow-up-right" aria-hidden="true"></span></a>
        </nav>
    </div>
</header>
<main id="main">
    <?php if ($path === 'about/our-team') : ?>
        <?php require get_template_directory() . '/pages/content.php'; ?>
    <?php elseif ($arrow_page) : ?>
        <?php $position = arrow_photo_display($arrow_page['image'], 'hero:' . $path); ?>
        <section class="subhero" style="--subhero-image:url('<?php echo esc_url(arrow_photo_url($arrow_page['image'], 'hero:' . $path)); ?>');--subhero-fit:<?php echo esc_attr($position['fit']); ?>;--subhero-position:<?php echo esc_attr($position['x'] . '% ' . $position['y'] . '%'); ?>">
            <div class="container subhero-inner">
                <p class="overline light"><span class="overline-mark" aria-hidden="true"></span> <?php echo esc_html($arrow_page['label']); ?></p>
                <h1><?php echo esc_html($arrow_page['title']); ?></h1>
                <p><?php echo esc_html($arrow_page['intro']); ?></p>
            </div>
        </section>
        <?php require get_template_directory() . '/pages/content.php'; ?>
    <?php else : ?>
        <?php $position = arrow_photo_display('arrow-home.jpg'); ?>
        <section class="subhero" style="--subhero-image:url('<?php echo esc_url(arrow_photo_url('arrow-home.jpg')); ?>');--subhero-fit:<?php echo esc_attr($position['fit']); ?>;--subhero-position:<?php echo esc_attr($position['x'] . '% ' . $position['y'] . '%'); ?>"><div class="container subhero-inner"><p class="overline light"><span class="overline-mark" aria-hidden="true"></span> THE ARROW CHURCH</p><h1><?php the_title(); ?></h1></div></section>
        <section class="section"><div class="container article-body"><?php while (have_posts()) : the_post(); the_content(); endwhile; ?></div></section>
    <?php endif; ?>
</main>
<footer class="footer"><div class="container"><div class="footer-main">
    <img src="<?php echo esc_url(arrow_photo_url('arrow-logo.png')); ?>" alt="The Arrow Church">
    <div><p>FOLLOW ALONG</p><a href="https://www.instagram.com/the.arrow.church/">Instagram <span class="arrow-icon arrow-up-right" aria-hidden="true"></span></a><a href="https://www.youtube.com/@thearrowchurch">YouTube <span class="arrow-icon arrow-up-right" aria-hidden="true"></span></a><a href="https://www.facebook.com/profile.php?id=100081125124651">Facebook <span class="arrow-icon arrow-up-right" aria-hidden="true"></span></a></div>
    <div><p>EXPLORE</p><a href="<?php echo esc_url(home_url('/about/')); ?>">About <span class="arrow-icon arrow-up-right" aria-hidden="true"></span></a><a href="<?php echo esc_url(home_url('/about/our-team/')); ?>">Staff <span class="arrow-icon arrow-up-right" aria-hidden="true"></span></a><a href="<?php echo esc_url(home_url('/ministries/')); ?>">Ministries <span class="arrow-icon arrow-up-right" aria-hidden="true"></span></a><a href="<?php echo esc_url(home_url('/events/')); ?>">Events <span class="arrow-icon arrow-up-right" aria-hidden="true"></span></a><a href="<?php echo esc_url(home_url('/pages/watch-live/')); ?>">Watch Live <span class="arrow-icon arrow-up-right" aria-hidden="true"></span></a><a href="<?php echo esc_url(home_url('/contact/')); ?>">Contact <span class="arrow-icon arrow-up-right" aria-hidden="true"></span></a><a href="<?php echo esc_url(home_url('/give/')); ?>">Give <span class="arrow-icon arrow-up-right" aria-hidden="true"></span></a></div>
    <div><p>VISIT</p><a href="https://maps.app.goo.gl/62ZYeJGdf3Qw7sJn7">2931 Lucerne Dr, Janesville, WI 53545 <span class="arrow-icon arrow-up-right" aria-hidden="true"></span></a><a href="tel:16087744837">(608) 774-4837 <span class="arrow-icon arrow-up-right" aria-hidden="true"></span></a></div>
</div><div class="footer-bottom"><span>© <span id="year"></span> THE ARROW CHURCH BILINGUAL MINISTRY</span><a href="#top">BACK TO TOP <span class="arrow-icon arrow-up" aria-hidden="true"></span></a></div></div></footer>
<script src="<?php echo esc_url($theme . '/script.js'); ?>"></script>
<?php wp_footer(); ?>
</body></html>

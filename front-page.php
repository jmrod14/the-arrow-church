<?php
/** Homepage template. The same markup is previewable as index.html. */
$theme_url = esc_url(get_template_directory_uri());
$markup = file_get_contents(get_template_directory() . '/index.html');
if ($markup === false) {
    status_header(500);
    exit('The homepage template is unavailable.');
}
$markup = str_replace('assets/', $theme_url . '/assets/', $markup);
foreach (['arrow-home.jpg', 'arrow-worship.jpg', 'arrow-kids.jpg', 'arrow-youth.jpg', 'arrow-young-adults.jpg', 'arrow-prayer.jpg', 'arrow-pastors.jpg', 'arrow-team-rossy.jpg', 'arrow-logo.png'] as $file) {
    $old_url = $theme_url . '/assets/' . $file;
    $new_url = esc_url(arrow_photo_url($file));
    $markup = str_replace('src="' . $old_url . '"', 'src="' . $new_url . '" style="' . esc_attr(arrow_photo_style($file)) . '"', $markup);
    $markup = str_replace('href="' . $old_url . '"', 'href="' . $new_url . '"', $markup);
}
$hero_url = esc_url(arrow_photo_url('arrow-home.jpg', 'hero:home'));
$markup = str_replace('class="hero-photo"', 'class="hero-photo" style="background-image:url(&quot;' . $hero_url . '&quot;);' . esc_attr(arrow_photo_style('arrow-home.jpg', 'hero:home', true)) . '"', $markup);
$live_url = esc_url(arrow_photo_url('arrow-worship.jpg'));
$live_display = arrow_photo_display('arrow-worship.jpg');
$markup = str_replace('class="live-poster"', 'class="live-poster" style="background-image:linear-gradient(90deg,rgba(0,0,0,.78),rgba(0,0,0,.22)),url(&quot;' . $live_url . '&quot;);background-size:100% 100%,' . esc_attr($live_display['fit']) . ';background-position:center,' . esc_attr($live_display['x'] . '% ' . $live_display['y'] . '%') . '"', $markup);
$markup = str_replace('href="styles.css"', 'href="' . $theme_url . '/styles.css"', $markup);
$markup = str_replace('src="script.js"', 'src="' . $theme_url . '/script.js"', $markup);
$markup = str_replace('action="/wp-admin/admin-post.php"', 'action="' . esc_url(admin_url('admin-post.php')) . '"', $markup);
$markup = str_replace('__ARROW_NONCE__', esc_attr(wp_create_nonce('arrow_contact')), $markup);
$message = '';
if (isset($_GET['contact'])) {
    $status = sanitize_key(wp_unslash($_GET['contact']));
    if ($status === 'sent') {
        $message = 'Thanks for reaching out. Your message has been received.';
    } elseif ($status === 'error') {
        $message = 'Your message could not be sent. Please call us at (608) 774-4837.';
    }
}
$markup = str_replace('__ARROW_CONTACT_STATUS__', esc_html($message), $markup);
ob_start();
wp_head();
$head = ob_get_clean();
ob_start();
wp_footer();
$footer = ob_get_clean();
$markup = str_replace('</head>', $head . '</head>', $markup);
$markup = str_replace('</body>', $footer . '</body>', $markup);
echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped substitutions into trusted theme markup.

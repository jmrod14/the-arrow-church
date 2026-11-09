<?php
/** Homepage template. The same markup is previewable as index.html. */
$theme_url = esc_url(get_template_directory_uri());
$markup = file_get_contents(get_template_directory() . '/index.html');
if ($markup === false) {
    status_header(500);
    exit('The homepage template is unavailable.');
}
$markup = str_replace('assets/', $theme_url . '/assets/', $markup);
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

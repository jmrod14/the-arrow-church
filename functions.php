<?php
/** Theme features and contact form handling. */
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
});

add_action('init', function () {
    register_post_type('arrow_message', [
        'labels' => ['name' => 'Contact Messages', 'singular_name' => 'Contact Message'],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'supports' => ['title', 'editor'],
        'capability_type' => 'post',
        'menu_icon' => 'dashicons-email-alt',
    ]);
});

function arrow_handle_contact() {
    $fallback = home_url('/?contact=error#contact');
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        wp_safe_redirect($fallback, 303);
        exit;
    }
    $nonce = isset($_POST['arrow_nonce']) ? sanitize_text_field(wp_unslash($_POST['arrow_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'arrow_contact')) {
        wp_safe_redirect($fallback, 303);
        exit;
    }
    if (!empty($_POST['website'])) {
        wp_safe_redirect(home_url('/?contact=sent#contact'), 303);
        exit;
    }
    $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
    if ($name === '' || !is_email($email) || $message === '' || strlen($name) > 120 || strlen($message) > 5000) {
        wp_safe_redirect($fallback, 303);
        exit;
    }
    $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $limit_key = 'arrow_contact_' . md5($ip);
    if (get_transient($limit_key)) {
        wp_safe_redirect($fallback, 303);
        exit;
    }
    set_transient($limit_key, 1, MINUTE_IN_SECONDS);
    $body = "From: {$name}\nEmail: {$email}\n\n{$message}";
    $post_id = wp_insert_post([
        'post_type' => 'arrow_message',
        'post_status' => 'private',
        'post_title' => 'Message from ' . $name,
        'post_content' => $body,
    ], true);
    if (is_wp_error($post_id)) {
        wp_safe_redirect($fallback, 303);
        exit;
    }
    wp_mail(get_option('admin_email'), 'New Arrow Church contact message', $body, [
        'Reply-To: ' . $name . ' <' . $email . '>',
    ]);
    wp_safe_redirect(home_url('/?contact=sent#contact'), 303);
    exit;
}
add_action('admin_post_arrow_contact', 'arrow_handle_contact');
add_action('admin_post_nopriv_arrow_contact', 'arrow_handle_contact');

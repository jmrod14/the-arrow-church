<?php
/** Theme features and contact form handling. */
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
});

require_once get_template_directory() . '/inc/site-photos.php';

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
    $return_to = isset($_POST['return_to']) && sanitize_key(wp_unslash($_POST['return_to'])) === 'contact' ? '/contact/' : '/';
    $suffix = $return_to === '/' ? '#contact' : '';
    $fallback = home_url($return_to . '?contact=error' . $suffix);
    $success = home_url($return_to . '?contact=sent' . $suffix);
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
        wp_safe_redirect($success, 303);
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
    wp_safe_redirect($success, 303);
    exit;
}
add_action('admin_post_arrow_contact', 'arrow_handle_contact');
add_action('admin_post_nopriv_arrow_contact', 'arrow_handle_contact');

/** Keep dated event links and calendar subscriptions working after migration. */
add_action('init', function () {
    add_rewrite_rule('^events/sunday-worship/([0-9]{4}-[0-9]{2}-[0-9]{2})/?$', 'index.php?pagename=events/sunday-worship&arrow_event_date=$matches[1]', 'top');
    add_rewrite_rule('^events/sunday-worship/([0-9]{4}-[0-9]{2}-[0-9]{2})\.ics$', 'index.php?arrow_ics_date=$matches[1]', 'top');
    add_rewrite_rule('^events\.ics$', 'index.php?arrow_calendar_ics=1', 'top');
    add_rewrite_rule('^livestreams/([12])/?$', 'index.php?pagename=live&arrow_stream_id=$matches[1]', 'top');
    add_rewrite_rule('^collections/ministries/?$', 'index.php?pagename=ministries', 'top');
});
add_filter('query_vars', function ($vars) {
    return array_merge($vars, ['arrow_event_date', 'arrow_ics_date', 'arrow_calendar_ics', 'arrow_stream_id']);
});
add_filter('redirect_canonical', function ($redirect) {
    return get_query_var('arrow_stream_id') ? false : $redirect;
});
add_filter('pre_get_document_title', function ($title) {
    $stream_id = (string) get_query_var('arrow_stream_id');
    if ($stream_id === '1' || $stream_id === '2') {
        return ($stream_id === '1' ? 'The Arrow Church Live Stream' : 'Guest Speaker') . ' – The Arrow Church';
    }
    return $title;
});

function arrow_upcoming_sundays($count) {
    $today = new DateTimeImmutable('today', new DateTimeZone('America/Chicago'));
    $days_until_sunday = (7 - (int) $today->format('w')) % 7;
    $first = $today->modify('+' . $days_until_sunday . ' days');
    $dates = [];
    for ($i = 0; $i < $count; $i++) {
        $dates[] = $first->modify('+' . ($i * 7) . ' days');
    }
    return $dates;
}

function arrow_event_date() {
    $raw = get_query_var('arrow_event_date');
    if (!$raw && isset($_GET['date'])) {
        $raw = sanitize_text_field(wp_unslash($_GET['date']));
    }
    if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new DateTimeZone('America/Chicago'));
        if ($date && $date->format('Y-m-d') === $raw && $date->format('w') === '0') {
            return $date;
        }
    }
    return arrow_upcoming_sundays(1)[0];
}

add_action('template_redirect', function () {
    $event_date = get_query_var('arrow_ics_date');
    $calendar = get_query_var('arrow_calendar_ics');
    if (!$event_date && !$calendar) {
        return;
    }
    $tz = new DateTimeZone('America/Chicago');
    $date = $calendar ? arrow_upcoming_sundays(1)[0] : DateTimeImmutable::createFromFormat('!Y-m-d', $event_date, $tz);
    if (!$date || $date->format('w') !== '0' || (!$calendar && $date->format('Y-m-d') !== $event_date)) {
        status_header(404);
        exit;
    }
    $start = $date->setTime(12, 30);
    $end = $date->setTime(13, 30);
    $lines = [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//The Arrow Church//Events//EN',
        'CALSCALE:GREGORIAN', 'X-WR-CALNAME:The Arrow Church',
        'BEGIN:VTIMEZONE', 'TZID:America/Chicago',
        'BEGIN:DAYLIGHT', 'TZOFFSETFROM:-0600', 'TZOFFSETTO:-0500', 'TZNAME:CDT', 'DTSTART:19700308T020000', 'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=2SU', 'END:DAYLIGHT',
        'BEGIN:STANDARD', 'TZOFFSETFROM:-0500', 'TZOFFSETTO:-0600', 'TZNAME:CST', 'DTSTART:19701101T020000', 'RRULE:FREQ=YEARLY;BYMONTH=11;BYDAY=1SU', 'END:STANDARD',
        'END:VTIMEZONE', 'BEGIN:VEVENT',
        'UID:sunday-worship-' . $date->format('Ymd') . '@thearrowchurch.org',
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART;TZID=America/Chicago:' . $start->format('Ymd\THis'),
        'DTEND;TZID=America/Chicago:' . $end->format('Ymd\THis'),
        'SUMMARY:Sunday Worship - The Arrow Church',
        'DESCRIPTION:Worship with us at The Arrow Church.',
        'LOCATION:2931 Lucerne Dr\, Janesville\, WI 53545',
    ];
    if ($calendar) {
        $lines[] = 'RRULE:FREQ=WEEKLY;BYDAY=SU';
    }
    $lines = array_merge($lines, ['END:VEVENT', 'END:VCALENDAR']);
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="the-arrow-church-events.ics"');
    echo implode("\r\n", $lines) . "\r\n";
    exit;
});

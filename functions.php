<?php
/** Theme features and contact form handling. */
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
});

require_once get_template_directory() . '/inc/site-photos.php';
require_once get_template_directory() . '/inc/staff.php';

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

/** Keep dated worship links and calendar subscriptions working after migration. */
add_action('init', function () {
    add_rewrite_rule('^events/sunday-worship/([0-9]{4}-[0-9]{2}-[0-9]{2})/?$', 'index.php?pagename=events/sunday-worship&arrow_event_date=$matches[1]', 'top');
    add_rewrite_rule('^events/sunday-worship/([0-9]{4}-[0-9]{2}-[0-9]{2})\.ics$', 'index.php?arrow_ics_date=$matches[1]&arrow_ics_service=sunday-worship', 'top');
    add_rewrite_rule('^events/friday-worship/([0-9]{4}-[0-9]{2}-[0-9]{2})/?$', 'index.php?pagename=events/friday-worship&arrow_event_date=$matches[1]', 'top');
    add_rewrite_rule('^events/friday-worship/([0-9]{4}-[0-9]{2}-[0-9]{2})\.ics$', 'index.php?arrow_ics_date=$matches[1]&arrow_ics_service=friday-worship', 'top');
    add_rewrite_rule('^events\.ics$', 'index.php?arrow_calendar_ics=1', 'top');
    add_rewrite_rule('^livestreams/([12])/?$', 'index.php?pagename=live&arrow_stream_id=$matches[1]', 'top');
    add_rewrite_rule('^collections/ministries/?$', 'index.php?pagename=ministries', 'top');
});
add_filter('query_vars', function ($vars) {
    return array_merge($vars, ['arrow_event_date', 'arrow_ics_date', 'arrow_ics_service', 'arrow_calendar_ics', 'arrow_stream_id']);
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

/** Recurring worship gatherings. Keys are the dated URL slugs. */
function arrow_worship_services() {
    return [
        'sunday-worship' => [
            'weekday' => 0,
            'byday' => 'SU',
            'start' => [12, 30],
            'end' => [13, 30],
            'name' => 'Sunday Worship',
            'list_label' => 'SUNDAY / DOMINGO · 12:30–1:30 PM',
            'clock' => '12:30 PM',
            'summary' => 'Sunday Worship - The Arrow Church',
            'description' => 'Worship with us at The Arrow Church.',
        ],
        'friday-worship' => [
            'weekday' => 5,
            'byday' => 'FR',
            'start' => [19, 0],
            'end' => [21, 0],
            'name' => 'Friday Worship',
            'list_label' => 'FRIDAY / VIERNES · 7:00–9:00 PM',
            'clock' => '7:00 PM',
            'summary' => 'Friday Worship - The Arrow Church',
            'description' => 'Worship with us at The Arrow Church.',
        ],
    ];
}

function arrow_upcoming_weekdays($weekday, $count) {
    $today = new DateTimeImmutable('today', new DateTimeZone('America/Chicago'));
    $days_until = ($weekday - (int) $today->format('w') + 7) % 7;
    $first = $today->modify('+' . $days_until . ' days');
    $dates = [];
    for ($i = 0; $i < $count; $i++) {
        $dates[] = $first->modify('+' . ($i * 7) . ' days');
    }
    return $dates;
}

function arrow_upcoming_sundays($count) {
    return arrow_upcoming_weekdays(0, $count);
}

/** Next Sunday and Friday gatherings, earliest date first. */
function arrow_upcoming_gatherings($count) {
    $gatherings = [];
    foreach (arrow_worship_services() as $slug => $service) {
        foreach (arrow_upcoming_weekdays($service['weekday'], $count) as $date) {
            $gatherings[] = ['slug' => $slug, 'service' => $service, 'date' => $date];
        }
    }
    usort($gatherings, function ($a, $b) {
        return $a['date'] <=> $b['date'];
    });
    return $gatherings;
}

function arrow_gathering_on(DateTimeImmutable $date) {
    $weekday = (int) $date->format('w');
    foreach (arrow_worship_services() as $slug => $service) {
        if ($service['weekday'] === $weekday) {
            return ['slug' => $slug, 'service' => $service, 'date' => $date];
        }
    }
    return null;
}

function arrow_event_date($slug = 'sunday-worship') {
    $services = arrow_worship_services();
    $service = $services[$slug] ?? $services['sunday-worship'];
    $raw = get_query_var('arrow_event_date');
    if (!$raw && isset($_GET['date'])) {
        $raw = sanitize_text_field(wp_unslash($_GET['date']));
    }
    if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new DateTimeZone('America/Chicago'));
        if ($date && $date->format('Y-m-d') === $raw && (int) $date->format('w') === $service['weekday']) {
            return $date;
        }
    }
    return arrow_upcoming_weekdays($service['weekday'], 1)[0];
}

/** Calendar feed or one dated worship event. Null means the date does not match that service. */
function arrow_ics_document($event_date, $service_slug, $calendar) {
    $services = arrow_worship_services();
    $tz = new DateTimeZone('America/Chicago');
    $included = [];
    if ($calendar) {
        foreach ($services as $slug => $service) {
            $included[] = [
                'date' => arrow_upcoming_weekdays($service['weekday'], 1)[0],
                'slug' => $slug,
                'recurring' => true,
            ];
        }
        usort($included, function ($a, $b) {
            return $a['date'] <=> $b['date'];
        });
    } else {
        $slug = isset($services[$service_slug]) ? $service_slug : 'sunday-worship';
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $event_date, $tz);
        $service = $services[$slug];
        if (!$date || $date->format('Y-m-d') !== $event_date || (int) $date->format('w') !== $service['weekday']) {
            return null;
        }
        $included[] = ['date' => $date, 'slug' => $slug, 'recurring' => false];
    }
    $lines = [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//The Arrow Church//Events//EN',
        'CALSCALE:GREGORIAN', 'X-WR-CALNAME:The Arrow Church',
        'BEGIN:VTIMEZONE', 'TZID:America/Chicago',
        'BEGIN:DAYLIGHT', 'TZOFFSETFROM:-0600', 'TZOFFSETTO:-0500', 'TZNAME:CDT', 'DTSTART:19700308T020000', 'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=2SU', 'END:DAYLIGHT',
        'BEGIN:STANDARD', 'TZOFFSETFROM:-0500', 'TZOFFSETTO:-0600', 'TZNAME:CST', 'DTSTART:19701101T020000', 'RRULE:FREQ=YEARLY;BYMONTH=11;BYDAY=1SU', 'END:STANDARD',
        'END:VTIMEZONE',
    ];
    foreach ($included as $item) {
        $service = $services[$item['slug']];
        $date = $item['date'];
        $start = $date->setTime($service['start'][0], $service['start'][1]);
        $end = $date->setTime($service['end'][0], $service['end'][1]);
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . $item['slug'] . '-' . $date->format('Ymd') . '@thearrowchurch.org';
        $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
        $lines[] = 'DTSTART;TZID=America/Chicago:' . $start->format('Ymd\THis');
        $lines[] = 'DTEND;TZID=America/Chicago:' . $end->format('Ymd\THis');
        $lines[] = 'SUMMARY:' . $service['summary'];
        $lines[] = 'DESCRIPTION:' . $service['description'];
        $lines[] = 'LOCATION:2931 Lucerne Dr\, Janesville\, WI 53545';
        if ($item['recurring']) {
            $lines[] = 'RRULE:FREQ=WEEKLY;BYDAY=' . $service['byday'];
        }
        $lines[] = 'END:VEVENT';
    }
    $lines[] = 'END:VCALENDAR';
    return implode("\r\n", $lines) . "\r\n";
}

add_action('template_redirect', function () {
    $event_date = get_query_var('arrow_ics_date');
    $calendar = get_query_var('arrow_calendar_ics');
    if (!$event_date && !$calendar) {
        return;
    }
    $document = arrow_ics_document($event_date, (string) get_query_var('arrow_ics_service'), (bool) $calendar);
    if ($document === null) {
        status_header(404);
        exit;
    }
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="the-arrow-church-events.ics"');
    echo $document;
    exit;
});

/** Share cards. Other public pages use the home image. */
function arrow_share_cards() {
    return [
        'home' => [
            'file' => 'share-home.jpg',
            'alt' => 'The Arrow Church in Janesville. Sundays 12:30 PM and Fridays 7:00 PM.',
        ],
        'about/our-team' => [
            'file' => 'share-staff.jpg',
            'alt' => 'Staff and leaders of The Arrow Church.',
        ],
        'pages/watch-live' => [
            'file' => 'share-watch.jpg',
            'alt' => 'Watch The Arrow Church live.',
        ],
        'contact' => [
            'file' => 'share-contact.jpg',
            'alt' => 'Contact The Arrow Church in Janesville, Wisconsin.',
        ],
    ];
}

function arrow_share_image() {
    $cards = arrow_share_cards();
    $key = 'home';
    if (!is_front_page()) {
        $path = trim((string) get_page_uri(get_queried_object_id()), '/');
        if (isset($cards[$path])) {
            $key = $path;
        }
    }
    $card = $cards[$key];
    return [
        'url' => set_url_scheme(get_template_directory_uri() . '/assets/' . $card['file'], 'https'),
        'width' => '1200',
        'height' => '630',
        'alt' => $card['alt'],
        'type' => 'image/jpeg',
    ];
}

function arrow_share_facebook_tags($tags) {
    if (!is_array($tags)) {
        $tags = [];
    }
    $image = arrow_share_image();
    $tags['og:image'] = $image['url'];
    $tags['og:image:secure_url'] = $image['url'];
    $tags['og:image:width'] = $image['width'];
    $tags['og:image:height'] = $image['height'];
    $tags['og:image:alt'] = $image['alt'];
    $tags['og:image:type'] = $image['type'];
    return $tags;
}
add_filter('aioseo_facebook_tags', 'arrow_share_facebook_tags');

function arrow_share_twitter_tags($tags) {
    if (!is_array($tags)) {
        $tags = [];
    }
    $image = arrow_share_image();
    $tags['twitter:card'] = 'summary_large_image';
    $tags['twitter:image'] = $image['url'];
    return $tags;
}
add_filter('aioseo_twitter_tags', 'arrow_share_twitter_tags');

/** Used only when All in One SEO is not already printing the social tags. */
function arrow_share_meta_tags() {
    if (defined('AIOSEO_VERSION') || function_exists('aioseo')) {
        return;
    }
    $image = arrow_share_image();
    $tags = [
        'og:image' => $image['url'],
        'og:image:secure_url' => $image['url'],
        'og:image:width' => $image['width'],
        'og:image:height' => $image['height'],
        'og:image:alt' => $image['alt'],
        'og:image:type' => $image['type'],
        'twitter:card' => 'summary_large_image',
        'twitter:image' => $image['url'],
    ];
    foreach ($tags as $name => $content) {
        $attribute = str_starts_with($name, 'twitter:') ? 'name' : 'property';
        printf(
            "<meta %s=\"%s\" content=\"%s\" />\n",
            esc_attr($attribute),
            esc_attr($name),
            esc_attr($content)
        );
    }
}
add_action('wp_head', 'arrow_share_meta_tags', 5);

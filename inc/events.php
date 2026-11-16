<?php
/**
 * Google Calendar data for the existing events list and month grid.
 * Cached 15 minutes, with the last good feed kept if Google is unreachable.
 * /events/ is excluded from Cache Enabler, which otherwise holds HTML for about 10 hours.
 */

require_once __DIR__ . '/ics.php';

function arrow_calendar_feed_url(): string {
    return 'https://calendar.google.com/calendar/ical/thearrowchurchcalendar%40gmail.com/public/basic.ics';
}

function arrow_calendar_subscribe_url(): string {
    return 'https://calendar.google.com/calendar/u/0?cid=dGhlYXJyb3djaHVyY2hjYWxlbmRhckBnbWFpbC5jb20';
}

function arrow_events_cache_exclusion(): string {
    return '/^\/events(\/|$)/';
}

function arrow_calendar_ics(): string {
    $cached = get_transient('arrow_gcal_ics');
    if (is_string($cached) && arrow_ics_is_calendar($cached)) {
        return $cached;
    }
    $response = wp_remote_get(arrow_calendar_feed_url(), [
        'timeout' => 10,
        'redirection' => 2,
        'limit_response_size' => 1048576,
        'user-agent' => 'The Arrow Church website',
    ]);
    $body = '';
    if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200) {
        $body = (string) wp_remote_retrieve_body($response);
        if (strncmp($body, "\xEF\xBB\xBF", 3) === 0) {
            $body = substr($body, 3);
        }
    }
    $ttl = 15 * (defined('MINUTE_IN_SECONDS') ? MINUTE_IN_SECONDS : 60);
    if (!arrow_ics_is_calendar($body)) {
        $stale = get_option('arrow_gcal_ics_last_good', '');
        if (is_string($stale) && arrow_ics_is_calendar($stale)) {
            set_transient('arrow_gcal_ics', $stale, $ttl);
            return $stale;
        }
        return '';
    }
    set_transient('arrow_gcal_ics', $body, $ttl);
    update_option('arrow_gcal_ics_last_good', $body, false);
    return $body;
}

function arrow_list_feed_events(?DateTimeImmutable $now = null, ?string $ics = null): array {
    $tz = arrow_ics_display_timezone();
    $now = $now ? $now->setTimezone($tz) : new DateTimeImmutable('now', $tz);
    return arrow_events_feed_between($now, $now->modify('+60 days'), 20, $ics);
}

function arrow_month_feed_events(DateTimeImmutable $month, ?string $ics = null): array {
    $start = $month->setTime(0, 0);
    return arrow_events_feed_between($start, $start->modify('first day of next month')->setTime(0, 0), 80, $ics);
}

function arrow_events_feed_between(DateTimeImmutable $start, DateTimeImmutable $end, int $limit, ?string $ics = null): array {
    if ($ics === null) {
        $ics = arrow_calendar_ics();
    }
    if (!is_string($ics) || !arrow_ics_is_calendar($ics)) {
        return [];
    }
    return arrow_ics_occurrences($ics, $start, $end, $limit);
}

function arrow_feed_events_by_day(array $events): array {
    $by_day = [];
    foreach ($events as $event) {
        $by_day[$event['start']->format('Y-m-d')][] = $event;
    }
    return $by_day;
}

function arrow_feed_event_clock(array $event): string {
    if (!empty($event['all_day'])) {
        return 'All day';
    }
    return $event['start']->format('g:i A');
}

function arrow_feed_event_label(array $event): string {
    $names = [
        ['SUNDAY', 'DOMINGO'],
        ['MONDAY', 'LUNES'],
        ['TUESDAY', 'MARTES'],
        ['WEDNESDAY', 'MIÉRCOLES'],
        ['THURSDAY', 'JUEVES'],
        ['FRIDAY', 'VIERNES'],
        ['SATURDAY', 'SÁBADO'],
    ];
    $name = $names[(int) $event['start']->format('w')];
    if (!empty($event['all_day'])) {
        $when = 'All day';
    } elseif ($event['start']->format('Y-m-d') === $event['end']->format('Y-m-d') && $event['start']->format('A') === $event['end']->format('A')) {
        $when = $event['start']->format('g:i') . '–' . $event['end']->format('g:i A');
    } else {
        $when = $event['start']->format('g:i A') . '–' . $event['end']->format('g:i A');
    }
    return $name[0] . ' / ' . $name[1] . ' · ' . $when;
}

function arrow_feed_event_href(array $event): string {
    if ($event['location'] !== '') {
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($event['location']);
    }
    return home_url('/events/calendar/?month=' . $event['start']->format('Y-m'));
}

function arrow_events_json_ld(array $events): string {
    if (!$events) {
        return '';
    }
    $graph = [];
    foreach ($events as $event) {
        $item = [
            '@type' => 'Event',
            'name' => $event['title'],
            'startDate' => !empty($event['all_day']) ? $event['start']->format('Y-m-d') : $event['start']->format('c'),
            'endDate' => !empty($event['all_day']) ? $event['end']->modify('-1 day')->format('Y-m-d') : $event['end']->format('c'),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'organizer' => [
                '@type' => 'Organization',
                'name' => 'The Arrow Church',
                'url' => home_url('/'),
            ],
        ];
        if ($event['description'] !== '') {
            $item['description'] = $event['description'];
        }
        if ($event['location'] !== '') {
            $item['location'] = [
                '@type' => 'Place',
                'name' => $event['location'],
                'address' => $event['location'],
            ];
        }
        $graph[] = $item;
    }
    $json = wp_json_encode([
        '@context' => 'https://schema.org',
        '@graph' => $graph,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json) || $json === '') {
        return '';
    }
    return '<script type="application/ld+json">' . $json . "</script>\n";
}

function arrow_request_is_events_path(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = is_string($uri) ? (string) parse_url($uri, PHP_URL_PATH) : '';
    return $path !== '' && preg_match(arrow_events_cache_exclusion(), $path) === 1;
}

function arrow_merge_events_cache_exclusion(string $current): string {
    $ours = arrow_events_cache_exclusion();
    if ($current === '') {
        return $ours;
    }
    $covers = true;
    foreach (['/events', '/events/', '/events/calendar/'] as $path) {
        if (@preg_match($current, $path) !== 1) {
            $covers = false;
            break;
        }
    }
    if ($covers) {
        return $current;
    }
    if (preg_match('#\A/(.*)/([a-z]*)\z#s', $current, $match)) {
        $merged = '/(?:' . $match[1] . ')|(?:^\/events(\/|$))/' . $match[2];
        if (@preg_match($merged, '') !== false && @preg_match($merged, '/events/') === 1 && @preg_match($merged, '/give/') !== 1) {
            return $merged;
        }
    }
    return $current;
}

function arrow_delete_cached_events_pages(): void {
    if (!defined('WP_CONTENT_DIR')) {
        return;
    }
    $root = realpath(WP_CONTENT_DIR . '/cache/cache-enabler');
    if ($root === false) {
        return;
    }
    foreach (glob($root . '/*/events', GLOB_ONLYDIR) ?: [] as $dir) {
        $real = realpath($dir);
        if ($real && str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            arrow_delete_directory($real);
        }
    }
}

function arrow_delete_directory(string $dir): void {
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            arrow_delete_directory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

function arrow_exclude_events_from_cache_enabler(): void {
    $option = 'cache_enabler';
    $settings = get_option($option);
    if (!is_array($settings)) {
        $legacy = get_option('cache-enabler');
        if (is_array($legacy)) {
            $option = 'cache-enabler';
            $settings = $legacy;
        }
    }
    if (is_array($settings)) {
        $key = 'excluded_page_paths';
        if (!array_key_exists($key, $settings) && array_key_exists('excl_paths', $settings)) {
            $key = 'excl_paths';
        } elseif (!array_key_exists($key, $settings) && array_key_exists('excl_regexp', $settings)) {
            $key = 'excl_regexp';
        }
        $updated = arrow_merge_events_cache_exclusion((string) ($settings[$key] ?? ''));
        if ($updated !== (string) ($settings[$key] ?? '')) {
            $settings[$key] = $updated;
            update_option($option, $settings);
            if (class_exists('Cache_Enabler_Disk') && method_exists('Cache_Enabler_Disk', 'create_settings_file')) {
                Cache_Enabler_Disk::create_settings_file($settings);
            }
        }
    }
    if (class_exists('Cache_Enabler') && method_exists('Cache_Enabler', 'clear_page_cache_by_url')) {
        foreach (['/events/', '/events/calendar/'] as $path) {
            Cache_Enabler::clear_page_cache_by_url(home_url($path));
        }
    }
    arrow_delete_cached_events_pages();
}

add_filter('cache_enabler_bypass_cache', function ($bypass) {
    return $bypass || arrow_request_is_events_path();
});

add_action('template_redirect', function () {
    if (!arrow_request_is_events_path()) {
        return;
    }
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    if (!headers_sent()) {
        header('Cache-Control: public, max-age=900');
    }
});

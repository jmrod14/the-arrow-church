<?php
/**
 * Parser fixtures for the public calendar. Not deployed (tools/ is excluded).
 */
require dirname(__DIR__) . '/inc/ics.php';

$failures = 0;

function expect($condition, string $message): void {
    global $failures;
    if ($condition) {
        echo "ok: {$message}\n";
        return;
    }
    $failures++;
    fwrite(STDERR, "FAIL: {$message}\n");
}

function expect_same($expected, $actual, string $message): void {
    if ($expected === $actual) {
        expect(true, $message);
        return;
    }
    global $failures;
    $failures++;
    fwrite(STDERR, "FAIL: {$message}\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n");
}

function chicago(string $stamp): DateTimeImmutable {
    return new DateTimeImmutable($stamp, new DateTimeZone('America/Chicago'));
}

function dates_of(array $events): array {
    return array_map(function ($event) {
        return $event['start']->format('Y-m-d H:i');
    }, $events);
}

function titles_on(string $ics, string $from, string $until, int $limit = 50): array {
    $events = arrow_ics_occurrences($ics, chicago($from), chicago($until), $limit);
    $rows = [];
    foreach ($events as $event) {
        $rows[] = $event['start']->format('Y-m-d H:i') . ' ' . $event['title'];
    }
    return $rows;
}

$tz = new DateTimeZone('America/Chicago');
$live = file_get_contents(__DIR__ . '/fixtures/mens-prayer-group.ics');
$rules = file_get_contents(__DIR__ . '/fixtures/rules.ics');
$empty = file_get_contents(__DIR__ . '/fixtures/empty.ics');

expect(str_contains($live, "RRULE:FREQ=YEARLY;BYMONTH=11;BYDAY=1SU"), 'fixture keeps the standard-time timezone rule');
expect(str_contains($live, "RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=2SU"), 'fixture keeps the daylight timezone rule');
expect(str_contains($live, 'DTSTART;TZID=America/Chicago:20261201T190000'), 'fixture keeps the first-Tuesday start');
expect(str_contains($live, 'RRULE:FREQ=MONTHLY;BYDAY=1TU'), 'fixture keeps the monthly ordinal rule');

$raw = arrow_ics_parse_events($live);
expect_same(1, count($raw), 'VTIMEZONE yearly rules are not events');
expect_same('Reunion De Caballeros | Men\'s Prayer Group', $raw[0]['summary'], 'summary keeps the apostrophe');
expect_same(
    'The Arrow Church Bilingual Ministry, 2931 Lucerne Dr, Janesville, WI 53545, USA',
    $raw[0]['location'],
    'folded location unescapes commas'
);
expect_same('2026-12-01 19:00', $raw[0]['start']->setTimezone($tz)->format('Y-m-d H:i'), 'start is 7:00 PM Chicago');
expect_same(false, $raw[0]['all_day'], 'prayer group is not all day');

$wide = arrow_ics_occurrences($live, chicago('2026-01-01 00:00:00'), chicago('2027-04-01 00:00:00'), 20);
expect_same(
    ['2026-12-01 19:00', '2027-01-05 19:00', '2027-02-02 19:00', '2027-03-02 19:00'],
    dates_of($wide),
    'first Tuesday of each month from the series start'
);
$november = arrow_ics_occurrences($live, chicago('2026-11-01 00:00:00'), chicago('2026-12-01 00:00:00'), 20);
expect_same([], dates_of($november), 'timezone first-Sunday rule is not a November event');
$upcoming = arrow_ics_occurrences($live, chicago('2026-11-16 08:00:00'), chicago('2026-11-16 08:00:00')->modify('+60 days'), 20);
expect_same(['2026-12-01 19:00', '2027-01-05 19:00'], dates_of($upcoming), 'list window is 60 days, so February is out');

$raw_rules = arrow_ics_parse_events($rules);
$rule_titles = array_map(function ($event) {
    return $event['summary'];
}, $raw_rules);
expect(!in_array('CDT', $rule_titles, true) && !in_array('CST', $rule_titles, true), 'timezone names are not event titles');
expect_same(11, count($raw_rules), 'rules fixture has one event per VEVENT');

$weekly = array_values(array_filter(
    arrow_ics_occurrences($rules, chicago('2026-11-01 00:00:00'), chicago('2026-12-31 00:00:00'), 80),
    function ($event) {
        return $event['title'] === 'Friday and Sunday';
    }
));
expect_same(['2026-11-20 19:00', '2026-11-22 19:00', '2026-11-29 19:00'], dates_of($weekly), 'weekly BYDAY honors COUNT and drops EXDATE without adding an extra week');
expect_same('2931 Lucerne Dr, Janesville, WI', $weekly[0]['location'] ?? '', 'weekly location unescapes commas');

$daily = array_values(array_filter(
    arrow_ics_occurrences($rules, chicago('2026-11-01 00:00:00'), chicago('2026-12-01 00:00:00'), 80),
    function ($event) {
        return $event['title'] === 'Daily count';
    }
));
expect_same(['2026-11-16 09:00', '2026-11-17 09:00', '2026-11-18 09:00'], dates_of($daily), 'daily COUNT stops at three');
$daily_until = array_values(array_filter(
    arrow_ics_occurrences($rules, chicago('2026-11-01 00:00:00'), chicago('2026-12-01 00:00:00'), 80),
    function ($event) {
        return $event['title'] === 'Daily until';
    }
));
expect_same(['2026-11-18 08:00', '2026-11-19 08:00', '2026-11-20 08:00'], dates_of($daily_until), 'daily UNTIL is inclusive');

$sixteenths = array_values(array_filter(
    arrow_ics_occurrences($rules, chicago('2026-11-01 00:00:00'), chicago('2027-03-01 00:00:00'), 80),
    function ($event) {
        return $event['title'] === 'Monthly the 16th';
    }
));
expect_same(['2026-11-16 12:00', '2026-12-16 12:00', '2027-01-16 12:00'], dates_of($sixteenths), 'monthly UNTIL keeps January and drops February');

$last_sundays = array_values(array_filter(
    arrow_ics_occurrences($rules, chicago('2026-11-01 00:00:00'), chicago('2027-02-01 00:00:00'), 80),
    function ($event) {
        return $event['title'] === 'Last Sunday';
    }
));
expect_same(['2026-11-29 18:00', '2026-12-27 18:00', '2027-01-31 18:00'], dates_of($last_sundays), 'negative ordinal BYDAY=-1SU is the last Sunday');

$second = array_values(array_filter(
    arrow_ics_occurrences($rules, chicago('2026-03-01 00:00:00'), chicago('2026-05-01 00:00:00'), 10),
    function ($event) {
        return $event['title'] === 'Second Sunday';
    }
));
expect_same(['2026-03-08 09:00', '2026-04-12 09:00'], dates_of($second), 'ordinal BYDAY=2SU is the second Sunday');

$mondays = array_values(array_filter(
    arrow_ics_occurrences($rules, chicago('2026-11-16 00:00:00'), chicago('2027-01-15 00:00:00'), 80),
    function ($event) {
        return $event['title'] === 'Every other Monday';
    }
));
expect_same(
    ['2026-11-16 10:00', '2026-11-30 10:00', '2026-12-14 10:00', '2026-12-28 10:00', '2027-01-11 10:00'],
    dates_of($mondays),
    'weekly INTERVAL=2 stays on the matching weeks'
);

$old = array_values(array_filter(
    arrow_ics_occurrences($rules, chicago('2026-11-16 08:00:00'), chicago('2027-01-15 08:00:00'), 80),
    function ($event) {
        return $event['title'] === 'Sunday since 2020';
    }
));
expect_same('2026-11-22 10:00', $old[0]['start']->format('Y-m-d H:i') ?? '', 'a long weekly series jumps to the window');
expect_same('2027-01-10 10:00', $old[count($old) - 1]['start']->format('Y-m-d H:i') ?? '', 'the jump still includes the last Sunday in range');
expect_same(8, count($old), 'eight Sundays from Nov 22 through Jan 10');

$christmas = array_values(array_filter(
    arrow_ics_occurrences($rules, chicago('2026-12-01 00:00:00'), chicago('2027-01-01 00:00:00'), 20),
    function ($event) {
        return $event['title'] === 'Christmas Eve';
    }
));
expect_same(1, count($christmas), 'all-day span is one occurrence');
expect_same(true, $christmas[0]['all_day'] ?? false, 'VALUE=DATE is all day');
expect_same('2026-12-24', $christmas[0]['start']->format('Y-m-d'), 'all-day starts on the 24th');
expect_same('2026-12-26', $christmas[0]['end']->format('Y-m-d'), 'all-day DTEND is exclusive');
expect_same("All are welcome.\nCome early.", $christmas[0]['description'], 'description unescapes newlines');

$utc = array_values(array_filter(
    arrow_ics_occurrences($rules, chicago('2026-12-01 00:00:00'), chicago('2026-12-03 00:00:00'), 10),
    function ($event) {
        return $event['title'] === 'UTC noon';
    }
));
expect_same('2026-12-02 12:00', $utc[0]['start']->format('Y-m-d H:i') ?? '', 'UTC Z time converts to Chicago');

$escaped = array_values(array_filter(
    arrow_ics_occurrences($rules, chicago('2026-12-10 00:00:00'), chicago('2026-12-11 00:00:00'), 5),
    function ($event) {
        return str_starts_with($event['title'], 'Hello');
    }
));
expect_same('Hello, world; path\\here', $escaped[0]['title'] ?? '', 'summary unescapes comma, semicolon, and backslash');
expect_same('Room A, Hall', $escaped[0]['location'] ?? '', 'location unescapes a comma');

$dst = arrow_ics_occurrences(
    "BEGIN:VCALENDAR\nX-WR-TIMEZONE:America/Chicago\nBEGIN:VEVENT\nUID:dst@test\nDTSTART;TZID=America/Chicago:20260302T190000\nDTEND;TZID=America/Chicago:20260302T200000\nRRULE:FREQ=WEEKLY;BYDAY=MO;COUNT=3\nSUMMARY:DST clock\nEND:VEVENT\nEND:VCALENDAR\n",
    chicago('2026-03-01 00:00:00'),
    chicago('2026-04-01 00:00:00'),
    5
);
expect_same(['2026-03-02 19:00', '2026-03-09 19:00', '2026-03-16 19:00'], dates_of($dst), 'weekly clock time stays 7:00 PM across DST');

expect_same([], arrow_ics_occurrences($empty, chicago('2026-11-01 00:00:00'), chicago('2027-01-01 00:00:00'), 20), 'empty feed has no events');
expect_same([], arrow_ics_parse_events($empty), 'empty feed parses as a calendar with zero events');

$folded = arrow_ics_parse_events("BEGIN:VCALENDAR\r\nX-WR-TIMEZONE:America/Chicago\r\nBEGIN:VEVENT\r\nUID:fold@test\r\nDTSTART;TZID=America/Chicago:20261205T100000\r\nDTEND;TZID=America/Chicago:20261205T110000\r\nSUMMARY:Folded\r\n long line\r\nDESCRIPTION:Hello\\nthere\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
expect_same('Foldedlong line', $folded[0]['summary'] ?? '', 'a CRLF fold drops the single leading space');

$capped = arrow_ics_occurrences(
    "BEGIN:VCALENDAR\nBEGIN:VEVENT\nUID:cap@test\nDTSTART;TZID=America/Chicago:20261116T090000\nDTEND;TZID=America/Chicago:20261116T100000\nRRULE:FREQ=DAILY\nSUMMARY:Many\nEND:VEVENT\nEND:VCALENDAR\n",
    chicago('2026-11-16 00:00:00'),
    chicago('2026-11-16 00:00:00')->modify('+60 days'),
    20
);
expect_same(20, count($capped), 'a daily series inside 60 days is capped at 20');
expect_same('2026-12-05 09:00', $capped[19]['start']->format('Y-m-d H:i'), 'the 20th daily event is Dec 5');

$parts = arrow_ics_text_parts("See <script>alert(1)</script> https://example.com/visit.");
expect_same('See <script>alert(1)</script> ', $parts[0]['text'], 'angle brackets stay in the text part');
expect_same('https://example.com/visit', $parts[1]['url'], 'the URL is split out without the trailing period');

function add_action(...$args): void {}
function add_filter(...$args): void {}
function get_transient($key) {
    global $arrow_test_ics;
    return $key === 'arrow_gcal_ics' ? $arrow_test_ics : false;
}
function set_transient(...$args) { return true; }
function get_option($key, $default = false) { return $default; }
function update_option(...$args) { return true; }
function is_wp_error($value): bool { return $value instanceof Exception; }
function wp_remote_get(...$args) { return new Exception('offline'); }
function wp_remote_retrieve_response_code($response): int { return 0; }
function wp_remote_retrieve_body($response): string { return ''; }
function esc_html($text): string { return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_attr($text): string { return esc_html($text); }
function esc_url($url, $protocols = null): string {
    $url = trim((string) $url);
    if (!preg_match('#^(https?:|/|\#)#i', $url)) {
        return '';
    }
    return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function wp_json_encode($data, $options = 0, $depth = 512) {
    return json_encode($data, $options | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT, $depth);
}
function home_url($path = '/'): string { return 'https://thearrowchurch.org' . $path; }
function add_query_arg($args, $url = ''): string {
    if (!is_array($args)) {
        $args = [$args => $url];
        $url = func_get_arg(2);
    }
    $sep = str_contains((string) $url, '?') ? '&' : '?';
    return $url . $sep . http_build_query($args);
}
function wp_unslash($value) { return $value; }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function sanitize_key($value): string { return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }

require dirname(__DIR__) . '/inc/events.php';

$prayer = arrow_ics_occurrences($live, chicago('2026-12-01 00:00:00'), chicago('2026-12-02 00:00:00'), 5)[0];
expect_same('TUESDAY / MARTES · 7:00–8:00 PM', arrow_feed_event_label($prayer), 'list label matches the existing bilingual time style');
expect_same('7:00 PM', arrow_feed_event_clock($prayer), 'calendar chip uses the start time');
expect(str_contains(arrow_feed_event_href($prayer), 'maps/search') && str_contains(arrow_feed_event_href($prayer), 'Lucerne'), 'location row links to Google Maps');
$listed = arrow_list_feed_events(chicago('2026-11-16 08:00:00'), $live);
expect_same(['2026-12-01 19:00', '2027-01-05 19:00'], dates_of($listed), 'the events list is the next 60 days');
expect_same([], arrow_list_feed_events(chicago('2026-11-16 08:00:00'), $empty), 'an empty feed lists nothing');
expect(preg_match(arrow_events_cache_exclusion(), '/events/') === 1, 'cache exclusion matches /events/');
expect(preg_match(arrow_events_cache_exclusion(), '/events/calendar/') === 1, 'cache exclusion matches the calendar tab');
expect(preg_match(arrow_events_cache_exclusion(), '/give/') !== 1, 'cache exclusion leaves other pages alone');
$json = arrow_events_json_ld([$prayer]);
expect(str_contains($json, 'application/ld+json') && str_contains($json, 'Reunion De Caballeros') && !str_contains($json, '<script>'), 'JSON-LD names the event and escapes tags');

function render_events(string $path, array $query, string $ics): string {
    global $arrow_test_ics;
    $arrow_test_ics = $ics;
    $_GET = $query;
    ob_start();
    include dirname(__DIR__) . '/pages/content.php';
    return ob_get_clean();
}

$arrow_test_ics = $live;
$december = render_events('events', ['month' => '2026-12'], $live);
expect(str_contains($december, 'pill-link active') && str_contains($december, '>CALENDAR</a>'), 'calendar tab is selected by default');
expect(str_contains($december, 'calendar-grid') && str_contains($december, 'calendar-day'), 'default view uses the existing calendar grid');
expect(str_contains($december, 'Reunion De Caballeros | Men&#039;s Prayer Group') || str_contains($december, 'Reunion De Caballeros | Men&#039;s Prayer Group'), 'December 1 renders the prayer group title');
expect(str_contains($december, '7:00 PM'), 'December 1 renders 7:00 PM');
expect(str_contains($december, 'calendar.google.com/calendar/u/0?cid=dGhlYXJyb3djaHVyY2hjYWxlbmRhckBnbWFpbC5jb20'), 'subscribe points at Google Calendar');
expect(!str_contains($december, 'Sunday Worship') && !str_contains($december, 'Friday Worship'), 'worship services are not hardcoded');
expect(!str_contains($december, '1970') && !str_contains($december, 'BYDAY=1SU'), 'timezone rules are not rendered');
expect(str_contains($december, 'The Arrow Church Bilingual Ministry, 2931 Lucerne Dr, Janesville'), 'the folded location is visible from the calendar link or JSON-LD');

$list = render_events('events', ['view' => 'list'], $live);
expect(str_contains($list, 'class="event-list"') && str_contains($list, 'class="event-row"') && str_contains($list, 'class="event-date"') && str_contains($list, 'class="event-arrow'), 'list view keeps the existing row markup');
expect(str_contains($list, 'COMING UP') && str_contains($list, 'TUESDAY / MARTES · 7:00–8:00 PM'), 'list keeps the coming-up heading and bilingual time');
expect(str_contains($list, 'pill-link active') && preg_match('/pill-link active"[^>]*>LIST</', $list) === 1, 'list tab is selected on the list view');

$blank = render_events('events', ['view' => 'list'], $empty);
expect(str_contains($blank, 'No upcoming events right now. Check back soon.') && str_contains($blank, 'No hay eventos próximos por ahora. Vuelve pronto.'), 'empty feed uses both languages');

$unsafe = arrow_ics_occurrences("BEGIN:VCALENDAR\nBEGIN:VEVENT\nUID:xss@test\nDTSTART;TZID=America/Chicago:20261210T150000\nDTEND;TZID=America/Chicago:20261210T160000\nSUMMARY:<script>alert(1)</script>\nDESCRIPTION:See https://example.com/safe.\nLOCATION:<img src=x>\nEND:VEVENT\nEND:VCALENDAR\n", chicago('2026-12-10 00:00:00'), chicago('2026-12-11 00:00:00'), 2);
$unsafe_html = render_events('events', ['view' => 'list'], "BEGIN:VCALENDAR\nBEGIN:VEVENT\nUID:xss@test\nDTSTART;TZID=America/Chicago:20261210T150000\nDTEND;TZID=America/Chicago:20261210T160000\nSUMMARY:<script>alert(1)</script>\nDESCRIPTION:See https://example.com/safe.\nLOCATION:Hall <b>A</b>\nEND:VEVENT\nEND:VCALENDAR\n");
expect(!str_contains($unsafe_html, '<script>alert') && str_contains($unsafe_html, '&lt;script&gt;'), 'event titles are escaped');
$jsonld_xss = file_get_contents(__DIR__ . '/fixtures/jsonld-xss.ics');
$jsonld_html = render_events('events', ['view' => 'list'], $jsonld_xss);
$jsonld_open = '<script type="application/ld+json">';
$jsonld_start = strpos($jsonld_html, $jsonld_open);
$jsonld_rest = $jsonld_start === false ? '' : substr($jsonld_html, $jsonld_start + strlen($jsonld_open));
$jsonld_close = strpos($jsonld_rest, '</script>');
$jsonld_payload = $jsonld_close === false ? '' : substr($jsonld_rest, 0, $jsonld_close);
$jsonld_after = $jsonld_close === false ? $jsonld_rest : substr($jsonld_rest, $jsonld_close + strlen('</script>'));
expect($jsonld_start !== false && $jsonld_close !== false && !str_contains($jsonld_payload, '</script>') && !str_contains($jsonld_after, '</script>'), 'JSON-LD contains no literal </script> before its closing tag');
expect(!str_contains($unsafe_html, '<b>A</b>'), 'locations are escaped');
expect(count($unsafe) === 1, 'unsafe fixture still parses as one event');

if ($failures > 0) {
    fwrite(STDERR, "{$failures} failed\n");
    exit(1);
}
echo "All calendar checks passed\n";

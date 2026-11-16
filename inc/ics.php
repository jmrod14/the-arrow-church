<?php
/**
 * Self-contained iCalendar parser for the public church calendar.
 * No WordPress dependencies. VTIMEZONE blocks are read only so their RRULEs are not events.
 */

function arrow_ics_display_timezone(): DateTimeZone {
    return new DateTimeZone('America/Chicago');
}

function arrow_ics_unfold(string $ics): string {
    $ics = str_replace(["\r\n", "\r"], "\n", $ics);
    $unfolded = preg_replace("/\n[ \t]/", '', $ics);
    return is_string($unfolded) ? $unfolded : $ics;
}

function arrow_ics_unescape(string $text): string {
    $out = '';
    $length = strlen($text);
    for ($i = 0; $i < $length; $i++) {
        if ($text[$i] === '\\' && $i + 1 < $length) {
            $next = $text[++$i];
            if ($next === 'n' || $next === 'N') {
                $out .= "\n";
            } else {
                $out .= $next;
            }
            continue;
        }
        $out .= $text[$i];
    }
    return $out;
}

/** Split on a delimiter, keeping escaped delimiters inside the value. */
function arrow_ics_split(string $value, string $delimiter): array {
    $parts = [];
    $current = '';
    $length = strlen($value);
    for ($i = 0; $i < $length; $i++) {
        if ($value[$i] === '\\' && $i + 1 < $length) {
            $current .= $value[$i] . $value[++$i];
            continue;
        }
        if ($value[$i] === $delimiter) {
            $parts[] = $current;
            $current = '';
            continue;
        }
        $current .= $value[$i];
    }
    $parts[] = $current;
    return $parts;
}

function arrow_ics_timezone(?string $tzid, DateTimeZone $fallback): DateTimeZone {
    $tzid = trim((string) $tzid, " \t\"");
    if ($tzid === '') {
        return $fallback;
    }
    try {
        return new DateTimeZone($tzid);
    } catch (Exception $e) {
        return $fallback;
    }
}

/**
 * @return array{name:string,params:array<string,string>,value:string}|null
 */
function arrow_ics_content_line(string $line): ?array {
    $line = rtrim($line, "\r\n");
    if ($line === '') {
        return null;
    }
    $length = strlen($line);
    $quoted = false;
    $colon = null;
    for ($i = 0; $i < $length; $i++) {
        $char = $line[$i];
        if ($char === '"') {
            $quoted = !$quoted;
        } elseif ($char === ':' && !$quoted) {
            $colon = $i;
            break;
        }
    }
    if ($colon === null) {
        return null;
    }
    $left = substr($line, 0, $colon);
    $value = substr($line, $colon + 1);
    $pieces = arrow_ics_split($left, ';');
    $name = strtoupper(trim($pieces[0]));
    if ($name === '') {
        return null;
    }
    $params = [];
    $count = count($pieces);
    for ($p = 1; $p < $count; $p++) {
        $piece = $pieces[$p];
        $eq = strpos($piece, '=');
        if ($eq === false) {
            continue;
        }
        $key = strtoupper(trim(substr($piece, 0, $eq)));
        $param = trim(substr($piece, $eq + 1));
        if (strlen($param) >= 2 && $param[0] === '"' && substr($param, -1) === '"') {
            $param = substr($param, 1, -1);
        }
        $params[$key] = $param;
    }
    return ['name' => $name, 'params' => $params, 'value' => $value];
}

/**
 * @param array<string,string> $params
 * @return array{dt:DateTimeImmutable,all_day:bool}|null
 */
function arrow_ics_parse_datetime(string $value, array $params, DateTimeZone $fallback): ?array {
    $value = trim($value);
    $tz = arrow_ics_timezone($params['TZID'] ?? '', $fallback);
    $date_only = (isset($params['VALUE']) && strtoupper($params['VALUE']) === 'DATE') || preg_match('/^\d{8}$/', $value);
    if ($date_only && preg_match('/^\d{8}$/', $value)) {
        $dt = DateTimeImmutable::createFromFormat('!Ymd', $value, $tz);
        if (!$dt || $dt->format('Ymd') !== $value) {
            return null;
        }
        return ['dt' => $dt, 'all_day' => true];
    }
    if (preg_match('/^(\d{8}T\d{6})Z$/', $value, $match)) {
        $dt = DateTimeImmutable::createFromFormat('!Ymd\THis', $match[1], new DateTimeZone('UTC'));
        if (!$dt || $dt->format('Ymd\THis') !== $match[1]) {
            return null;
        }
        return ['dt' => $dt, 'all_day' => false];
    }
    if (preg_match('/^\d{8}T\d{6}$/', $value)) {
        $dt = DateTimeImmutable::createFromFormat('!Ymd\THis', $value, $tz);
        if (!$dt || $dt->format('Ymd\THis') !== $value) {
            return null;
        }
        return ['dt' => $dt, 'all_day' => false];
    }
    return null;
}

/**
 * @return list<array{ord:?int,w:int}>
 */
function arrow_ics_parse_byday(string $value): array {
    $days = [];
    foreach (explode(',', strtoupper($value)) as $part) {
        $part = trim($part);
        if (!preg_match('/^([+-]?\d{1,2})?(SU|MO|TU|WE|TH|FR|SA)$/', $part, $match)) {
            continue;
        }
        $index = ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6][$match[2]];
        $days[] = [
            'ord' => ($match[1] ?? '') === '' ? null : (int) $match[1],
            'w' => $index,
        ];
    }
    return $days;
}

/**
 * @return array{freq:string,interval:int,count:?int,until:?DateTimeImmutable,byday:list<array{ord:?int,w:int}>,bymonthday:list<int>,bymonth:list<int>,wkst:int}|null
 */
function arrow_ics_parse_rrule(string $value, DateTimeZone $fallback): ?array {
    $freq = '';
    $interval = 1;
    $count = null;
    $until = null;
    $byday = [];
    $bymonthday = [];
    $bymonth = [];
    $wkst = 1;
    foreach (explode(';', $value) as $part) {
        $eq = strpos($part, '=');
        if ($eq === false) {
            continue;
        }
        $key = strtoupper(trim(substr($part, 0, $eq)));
        $part_value = trim(substr($part, $eq + 1));
        if ($key === 'FREQ') {
            $freq = strtoupper($part_value);
        } elseif ($key === 'INTERVAL') {
            $interval = max(1, (int) $part_value);
        } elseif ($key === 'COUNT') {
            $count = max(1, (int) $part_value);
        } elseif ($key === 'UNTIL') {
            $parsed = arrow_ics_parse_datetime($part_value, [], $fallback);
            $until = $parsed['dt'] ?? null;
        } elseif ($key === 'BYDAY') {
            $byday = arrow_ics_parse_byday($part_value);
        } elseif ($key === 'BYMONTHDAY') {
            foreach (explode(',', $part_value) as $day) {
                if (preg_match('/^[+-]?\d{1,2}$/', trim($day))) {
                    $bymonthday[] = (int) $day;
                }
            }
        } elseif ($key === 'BYMONTH') {
            foreach (explode(',', $part_value) as $month) {
                $month = (int) $month;
                if ($month >= 1 && $month <= 12) {
                    $bymonth[] = $month;
                }
            }
        } elseif ($key === 'WKST') {
            $parsed = arrow_ics_parse_byday($part_value);
            if ($parsed) {
                $wkst = $parsed[0]['w'];
            }
        }
    }
    if (!in_array($freq, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)) {
        return null;
    }
    return [
        'freq' => $freq,
        'interval' => $interval,
        'count' => $count,
        'until' => $until,
        'byday' => $byday,
        'bymonthday' => $bymonthday,
        'bymonth' => $bymonth,
        'wkst' => $wkst,
    ];
}

function arrow_ics_is_calendar(string $body): bool {
    if ($body === '' || strlen($body) > 1048576) {
        return false;
    }
    if (stripos($body, '<html') !== false) {
        return false;
    }
    return stripos($body, 'BEGIN:VCALENDAR') !== false && stripos($body, 'END:VCALENDAR') !== false;
}

/**
 * VEVENTs only. YEARLY rules inside VTIMEZONE are ignored.
 *
 * @return list<array<string,mixed>>
 */
function arrow_ics_parse_events(string $ics): array {
    $ics = arrow_ics_unfold($ics);
    if (strncmp($ics, "\xEF\xBB\xBF", 3) === 0) {
        $ics = substr($ics, 3);
    }
    $calendar_tz = arrow_ics_display_timezone();
    $stack = [];
    $events = [];
    $current = null;
    $vevent_depth = null;
    foreach (explode("\n", $ics) as $line) {
        $prop = arrow_ics_content_line($line);
        if (!$prop) {
            continue;
        }
        if ($prop['name'] === 'BEGIN') {
            $stack[] = strtoupper(trim($prop['value']));
            if (strtoupper(trim($prop['value'])) === 'VEVENT' && $vevent_depth === null) {
                $vevent_depth = count($stack);
                $current = ['props' => []];
            }
            continue;
        }
        if ($prop['name'] === 'END') {
            $ended = array_pop($stack);
            if ($ended === 'VEVENT' && $vevent_depth !== null && count($stack) === $vevent_depth - 1 && $current) {
                $event = arrow_ics_normalize_event($current['props'], $calendar_tz);
                if ($event) {
                    $events[] = $event;
                }
                $current = null;
                $vevent_depth = null;
            }
            if (count($events) >= 500) {
                break;
            }
            continue;
        }
        if ($vevent_depth !== null && count($stack) === $vevent_depth && $current && count($current['props']) < 300) {
            $current['props'][] = $prop;
            continue;
        }
        if ($stack === ['VCALENDAR'] && $prop['name'] === 'X-WR-TIMEZONE') {
            $calendar_tz = arrow_ics_timezone($prop['value'], $calendar_tz);
        }
    }
    return $events;
}

/**
 * @param list<array{name:string,params:array<string,string>,value:string}> $props
 * @return array<string,mixed>|null
 */
function arrow_ics_normalize_event(array $props, DateTimeZone $calendar_tz): ?array {
    $summary = '';
    $description = '';
    $location = '';
    $status = 'CONFIRMED';
    $uid = '';
    $url = '';
    $start = null;
    $end = null;
    $all_day = false;
    $rrule = null;
    $exdates = [];
    $recurrence_id = null;
    $duration = '';
    foreach ($props as $prop) {
        $name = $prop['name'];
        if ($name === 'SUMMARY') {
            $summary = arrow_ics_unescape($prop['value']);
        } elseif ($name === 'DESCRIPTION') {
            $description = arrow_ics_unescape($prop['value']);
        } elseif ($name === 'LOCATION') {
            $location = arrow_ics_unescape($prop['value']);
        } elseif ($name === 'STATUS') {
            $status = strtoupper(trim($prop['value']));
        } elseif ($name === 'UID') {
            $uid = trim($prop['value']);
        } elseif ($name === 'URL') {
            $url = trim($prop['value']);
        } elseif ($name === 'DTSTART') {
            $parsed = arrow_ics_parse_datetime($prop['value'], $prop['params'], $calendar_tz);
            if ($parsed) {
                $start = $parsed['dt'];
                $all_day = $parsed['all_day'];
            }
        } elseif ($name === 'DTEND') {
            $parsed = arrow_ics_parse_datetime($prop['value'], $prop['params'], $calendar_tz);
            if ($parsed) {
                $end = $parsed['dt'];
            }
        } elseif ($name === 'DURATION') {
            $duration = strtoupper(trim($prop['value']));
        } elseif ($name === 'RRULE') {
            $rrule = arrow_ics_parse_rrule($prop['value'], $calendar_tz);
        } elseif ($name === 'EXDATE') {
            foreach (arrow_ics_split($prop['value'], ',') as $piece) {
                $parsed = arrow_ics_parse_datetime($piece, $prop['params'], $calendar_tz);
                if ($parsed) {
                    $exdates[] = $parsed;
                }
            }
        } elseif ($name === 'RECURRENCE-ID') {
            $recurrence_id = arrow_ics_parse_datetime($prop['value'], $prop['params'], $calendar_tz);
        }
    }
    if (!$start) {
        return null;
    }
    if (!$end && $duration !== '') {
        try {
            $end = $start->add(new DateInterval($duration));
        } catch (Exception $e) {
            $end = null;
        }
    }
    if (!$end) {
        $end = $all_day ? $start->modify('+1 day') : $start->modify('+1 hour');
    }
    return [
        'uid' => $uid,
        'summary' => arrow_ics_clip($summary, 500),
        'description' => arrow_ics_clip($description, 4000),
        'location' => arrow_ics_clip($location, 500),
        'status' => $status,
        'url' => $url,
        'start' => $start,
        'end' => $end,
        'all_day' => $all_day,
        'rrule' => $rrule,
        'exdates' => $exdates,
        'recurrence_id' => $recurrence_id,
    ];
}

function arrow_ics_clip(string $text, int $limit): string {
    if (strlen($text) <= $limit) {
        return $text;
    }
    return substr($text, 0, $limit);
}

function arrow_ics_point_key(DateTimeImmutable $dt, bool $all_day): string {
    if ($all_day) {
        return 'd:' . $dt->format('Y-m-d');
    }
    return 't:' . $dt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
}

/**
 * Plain-text pieces with http(s) URLs split out for later escaping.
 *
 * @return list<array{text:string,url:?string}>
 */
function arrow_ics_text_parts(string $text): array {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    if (strlen($text) > 4000) {
        $text = substr($text, 0, 4000);
    }
    $parts = [];
    $offset = 0;
    $length = strlen($text);
    while ($offset < $length && preg_match('#https?://[^\s<]+#i', $text, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $pos = $match[0][1];
        $raw = $match[0][0];
        if ($pos > $offset) {
            $parts[] = ['text' => substr($text, $offset, $pos - $offset), 'url' => null];
        }
        $url = $raw;
        $suffix = '';
        while ($url !== '' && preg_match('/[.,;:!?)\]]$/', $url)) {
            $suffix = substr($url, -1) . $suffix;
            $url = substr($url, 0, -1);
        }
        if ($url !== '') {
            $parts[] = ['text' => $url, 'url' => $url];
        }
        if ($suffix !== '') {
            $parts[] = ['text' => $suffix, 'url' => null];
        }
        $offset = $pos + strlen($raw);
    }
    if ($offset < $length) {
        $parts[] = ['text' => substr($text, $offset), 'url' => null];
    }
    if (!$parts) {
        $parts[] = ['text' => '', 'url' => null];
    }
    return $parts;
}

/**
 * Occurrences that overlap [$range_start, $range_end). Times are America/Chicago.
 *
 * @return list<array<string,mixed>>
 */
function arrow_ics_occurrences(string $ics, DateTimeImmutable $range_start, DateTimeImmutable $range_end, int $limit = 20): array {
    $limit = max(1, $limit);
    $raw = arrow_ics_parse_events($ics);
    $masters = [];
    $overrides = [];
    foreach ($raw as $index => $event) {
        $uid = $event['uid'] !== '' ? $event['uid'] : 'anon-' . $index;
        if ($event['recurrence_id']) {
            $overrides[$uid][] = $event;
        } else {
            $masters[$uid] = $event;
        }
    }
    $occurrences = [];
    foreach ($masters as $uid => $master) {
        if ($master['status'] === 'CANCELLED') {
            continue;
        }
        $replace = [];
        foreach ($overrides[$uid] ?? [] as $override) {
            $id = $override['recurrence_id'];
            $replace[arrow_ics_point_key($id['dt'], $id['all_day'])] = $override;
        }
        foreach (arrow_ics_expand($master, $range_start, $range_end) as $instance) {
            $key = arrow_ics_point_key($instance['start_local'], $instance['all_day']);
            if (isset($replace[$key])) {
                continue;
            }
            $occurrences[] = $instance['occurrence'];
        }
        foreach ($overrides[$uid] ?? [] as $override) {
            if ($override['status'] === 'CANCELLED') {
                continue;
            }
            $single = arrow_ics_single_occurrence($override, $range_start, $range_end);
            if ($single) {
                $occurrences[] = $single;
            }
        }
    }
    usort($occurrences, function ($a, $b) {
        $cmp = $a['start'] <=> $b['start'];
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp($a['title'], $b['title']);
    });
    if (count($occurrences) > $limit) {
        $occurrences = array_slice($occurrences, 0, $limit);
    }
    return $occurrences;
}

/**
 * @param array<string,mixed> $event
 * @return list<array{start_local:DateTimeImmutable,all_day:bool,occurrence:array<string,mixed>}>
 */
function arrow_ics_expand(array $event, DateTimeImmutable $range_start, DateTimeImmutable $range_end): array {
    $exdate_keys = [];
    foreach ($event['exdates'] as $exdate) {
        $exdate_keys[arrow_ics_point_key($exdate['dt'], $exdate['all_day'])] = true;
    }
    $out = [];
    foreach (arrow_ics_series_starts($event, $range_start, $range_end) as $start) {
        if (!empty($exdate_keys[arrow_ics_point_key($start, $event['all_day'])])) {
            continue;
        }
        $occurrence = arrow_ics_occurrence_from($event, $start, $range_start, $range_end);
        if ($occurrence) {
            $out[] = [
                'start_local' => $start,
                'all_day' => $event['all_day'],
                'occurrence' => $occurrence,
            ];
        }
    }
    return $out;
}

/**
 * @param array<string,mixed> $event
 * @return list<DateTimeImmutable>
 */
function arrow_ics_series_starts(array $event, DateTimeImmutable $range_start, DateTimeImmutable $range_end): array {
    $rule = $event['rrule'];
    if (!$rule) {
        return [$event['start']];
    }
    if ($rule['freq'] === 'DAILY') {
        return arrow_ics_daily_starts($event, $range_start, $range_end);
    }
    if ($rule['freq'] === 'WEEKLY') {
        return arrow_ics_weekly_starts($event, $range_start, $range_end);
    }
    if ($rule['freq'] === 'MONTHLY' || $rule['freq'] === 'YEARLY') {
        return arrow_ics_monthly_starts($event, $range_start, $range_end);
    }
    return [$event['start']];
}

/**
 * @param array<string,mixed> $event
 * @return list<DateTimeImmutable>
 */
function arrow_ics_daily_starts(array $event, DateTimeImmutable $range_start, DateTimeImmutable $range_end): array {
    $rule = $event['rrule'];
    $interval = $rule['interval'];
    $cursor = $event['start'];
    if ($rule['count'] === null && $range_start > $cursor) {
        $days = (int) $cursor->diff($range_start)->days;
        $steps = intdiv($days, $interval);
        if ($steps > 1) {
            $cursor = $cursor->modify('+' . (($steps - 1) * $interval) . ' days');
        }
    }
    $starts = [];
    $produced = 0;
    for ($guard = 0; $guard < 5000; $guard++) {
        $decision = arrow_ics_consider($cursor, $event, $range_start, $range_end, $produced, $starts);
        if ($decision === 'stop') {
            break;
        }
        $cursor = $cursor->modify('+' . $interval . ' days');
    }
    return $starts;
}

/**
 * @param array<string,mixed> $event
 * @return list<DateTimeImmutable>
 */
function arrow_ics_weekly_starts(array $event, DateTimeImmutable $range_start, DateTimeImmutable $range_end): array {
    $rule = $event['rrule'];
    $interval = $rule['interval'];
    $wkst = $rule['wkst'];
    $days = [];
    foreach ($rule['byday'] as $byday) {
        $days[$byday['w']] = $byday['w'];
    }
    if (!$days) {
        $days[(int) $event['start']->format('w')] = (int) $event['start']->format('w');
    }
    $days = array_values($days);
    usort($days, function ($a, $b) use ($wkst) {
        return (($a - $wkst + 7) % 7) <=> (($b - $wkst + 7) % 7);
    });
    $cursor = arrow_ics_week_start($event['start'], $wkst);
    if ($rule['count'] === null && $range_start > $event['start']) {
        $target = arrow_ics_week_start($range_start, $wkst);
        $weeks = intdiv((int) $cursor->diff($target)->days, 7);
        $aligned = $weeks - ($weeks % $interval);
        if ($aligned >= $interval) {
            $aligned -= $interval;
        }
        if ($aligned > 0) {
            $cursor = $cursor->modify('+' . ($aligned * 7) . ' days');
        }
    }
    $starts = [];
    $produced = 0;
    $hour = (int) $event['start']->format('H');
    $minute = (int) $event['start']->format('i');
    $second = (int) $event['start']->format('s');
    for ($guard = 0; $guard < 5000; $guard++) {
        if ($cursor > $range_end->modify('+14 days')) {
            break;
        }
        $stop = false;
        foreach ($days as $weekday) {
            $delta = ($weekday - $wkst + 7) % 7;
            $when = $cursor->modify('+' . $delta . ' days')->setTime($hour, $minute, $second);
            $decision = arrow_ics_consider($when, $event, $range_start, $range_end, $produced, $starts);
            if ($decision === 'stop') {
                $stop = true;
                break;
            }
        }
        if ($stop) {
            break;
        }
        $cursor = $cursor->modify('+' . (7 * $interval) . ' days');
    }
    return $starts;
}

/**
 * @param array<string,mixed> $event
 * @return list<DateTimeImmutable>
 */
function arrow_ics_monthly_starts(array $event, DateTimeImmutable $range_start, DateTimeImmutable $range_end): array {
    $rule = $event['rrule'];
    $interval = $rule['freq'] === 'YEARLY' ? $rule['interval'] * 12 : $rule['interval'];
    $year = (int) $event['start']->format('Y');
    $month = (int) $event['start']->format('n');
    if ($rule['count'] === null && $range_start > $event['start']) {
        $diff = ((int) $range_start->format('Y') - $year) * 12 + ((int) $range_start->format('n') - $month);
        $steps = intdiv($diff, $interval);
        if ($steps > 1) {
            $jump = ($steps - 1) * $interval;
            $month += $jump;
            $year += intdiv($month - 1, 12);
            $month = (($month - 1) % 12) + 1;
        }
    }
    $starts = [];
    $produced = 0;
    $end_stamp = ((int) $range_end->format('Y')) * 12 + (int) $range_end->format('n');
    for ($guard = 0; $guard < 5000; $guard++) {
        $stamp = $year * 12 + $month;
        if ($stamp > $end_stamp + $interval) {
            break;
        }
        if ($rule['bymonth'] && !in_array($month, $rule['bymonth'], true) && $rule['freq'] !== 'MONTHLY') {
            $month += $interval;
            $year += intdiv($month - 1, 12);
            $month = (($month - 1) % 12) + 1;
            continue;
        }
        $stop = false;
        foreach (arrow_ics_month_candidates($event, $year, $month) as $when) {
            $decision = arrow_ics_consider($when, $event, $range_start, $range_end, $produced, $starts);
            if ($decision === 'stop') {
                $stop = true;
                break;
            }
        }
        if ($stop) {
            break;
        }
        $month += $interval;
        $year += intdiv($month - 1, 12);
        $month = (($month - 1) % 12) + 1;
    }
    return $starts;
}

/**
 * @param array<string,mixed> $event
 * @param list<DateTimeImmutable> $starts
 */
function arrow_ics_consider(DateTimeImmutable $when, array $event, DateTimeImmutable $range_start, DateTimeImmutable $range_end, int &$produced, array &$starts): string {
    $dtstart = $event['start'];
    $until = $event['rrule']['until'] ?? null;
    $count = $event['rrule']['count'] ?? null;
    if ($event['all_day']) {
        if ($when->format('Y-m-d') < $dtstart->format('Y-m-d')) {
            return 'skip';
        }
        if ($until && $when->format('Y-m-d') > $until->setTimezone($when->getTimezone())->format('Y-m-d')) {
            return 'stop';
        }
    } else {
        if ($when < $dtstart) {
            return 'skip';
        }
        if ($until && $when > $until) {
            return 'stop';
        }
    }
    $produced++;
    if ($count !== null && $produced > $count) {
        return 'stop';
    }
    if ($when >= $range_end) {
        return 'stop';
    }
    $end = arrow_ics_instance_end($event, $when);
    if ($end > $range_start) {
        $starts[] = $when;
    }
    return 'keep';
}

function arrow_ics_instance_end(array $event, DateTimeImmutable $start): DateTimeImmutable {
    if ($event['all_day']) {
        $days = arrow_ics_span_days($event['start'], $event['end']);
        return $start->modify('+' . $days . ' days');
    }
    $seconds = $event['end']->getTimestamp() - $event['start']->getTimestamp();
    if ($seconds <= 0) {
        $seconds = 3600;
    }
    return $start->setTimestamp($start->getTimestamp() + $seconds);
}

function arrow_ics_span_days(DateTimeImmutable $start, DateTimeImmutable $end): int {
    $from = DateTimeImmutable::createFromFormat('!Y-m-d', $start->format('Y-m-d'));
    $to = DateTimeImmutable::createFromFormat('!Y-m-d', $end->format('Y-m-d'));
    $days = (int) $from->diff($to)->days;
    return max(1, $days);
}

function arrow_ics_week_start(DateTimeImmutable $dt, int $wkst): DateTimeImmutable {
    $delta = ((int) $dt->format('w') - $wkst + 7) % 7;
    return $dt->setTime(0, 0)->modify('-' . $delta . ' days');
}

/**
 * @param array<string,mixed> $event
 * @return list<DateTimeImmutable>
 */
function arrow_ics_month_candidates(array $event, int $year, int $month): array {
    $rule = $event['rrule'];
    $like = $event['start'];
    $dates = [];
    if ($rule['byday']) {
        foreach ($rule['byday'] as $byday) {
            if ($byday['ord'] === null) {
                $dim = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                for ($day = 1; $day <= $dim; $day++) {
                    $when = arrow_ics_at($year, $month, $day, $like);
                    if ($when && (int) $when->format('w') === $byday['w']) {
                        $dates[] = $when;
                    }
                }
            } else {
                $when = arrow_ics_nth_weekday($year, $month, $byday['ord'], $byday['w'], $like);
                if ($when) {
                    $dates[] = $when;
                }
            }
        }
    } elseif ($rule['bymonthday']) {
        $dim = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        foreach ($rule['bymonthday'] as $day) {
            if ($day < 0) {
                $day = $dim + $day + 1;
            }
            $when = arrow_ics_at($year, $month, $day, $like);
            if ($when) {
                $dates[] = $when;
            }
        }
    } else {
        $when = arrow_ics_at($year, $month, (int) $like->format('j'), $like);
        if ($when) {
            $dates[] = $when;
        }
    }
    usort($dates, function ($a, $b) {
        return $a <=> $b;
    });
    $unique = [];
    $seen = [];
    foreach ($dates as $date) {
        $key = $date->format('Y-m-d H:i:s');
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $unique[] = $date;
    }
    return $unique;
}

function arrow_ics_nth_weekday(int $year, int $month, int $ord, int $weekday, DateTimeImmutable $like): ?DateTimeImmutable {
    $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $like->getTimezone());
    $dim = (int) $first->format('t');
    if ($ord > 0) {
        $delta = ($weekday - (int) $first->format('w') + 7) % 7;
        $day = 1 + $delta + ($ord - 1) * 7;
        if ($day > $dim) {
            return null;
        }
        return arrow_ics_at($year, $month, $day, $like);
    }
    if ($ord < 0) {
        $last = $first->setDate($year, $month, $dim);
        $delta = ((int) $last->format('w') - $weekday + 7) % 7;
        $day = $dim - $delta + ($ord + 1) * 7;
        if ($day < 1) {
            return null;
        }
        return arrow_ics_at($year, $month, $day, $like);
    }
    return null;
}

function arrow_ics_at(int $year, int $month, int $day, DateTimeImmutable $like): ?DateTimeImmutable {
    if ($month < 1 || $month > 12 || $day < 1) {
        return null;
    }
    $dim = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    if ($day > $dim) {
        return null;
    }
    $stamp = sprintf(
        '%04d-%02d-%02d %02d:%02d:%02d',
        $year,
        $month,
        $day,
        (int) $like->format('H'),
        (int) $like->format('i'),
        (int) $like->format('s')
    );
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $stamp, $like->getTimezone());
    if (!$dt || $dt->format('Y-m-d H:i:s') !== $stamp) {
        return null;
    }
    return $dt;
}

/**
 * @param array<string,mixed> $event
 * @return array<string,mixed>|null
 */
function arrow_ics_occurrence_from(array $event, DateTimeImmutable $start, DateTimeImmutable $range_start, DateTimeImmutable $range_end): ?array {
    $end = arrow_ics_instance_end($event, $start);
    if ($end <= $range_start || $start >= $range_end) {
        return null;
    }
    $display = arrow_ics_display_timezone();
    if ($event['all_day']) {
        $shown_start = DateTimeImmutable::createFromFormat('!Y-m-d', $start->format('Y-m-d'), $display);
        $shown_end = $shown_start->modify('+' . arrow_ics_span_days($event['start'], $event['end']) . ' days');
    } else {
        $shown_start = $start->setTimezone($display);
        $shown_end = $end->setTimezone($display);
    }
    return [
        'uid' => $event['uid'],
        'title' => $event['summary'] !== '' ? $event['summary'] : 'The Arrow Church',
        'description' => $event['description'],
        'location' => $event['location'],
        'start' => $shown_start,
        'end' => $shown_end,
        'all_day' => $event['all_day'],
        'url' => $event['url'],
    ];
}

/**
 * @param array<string,mixed> $event
 * @return array<string,mixed>|null
 */
function arrow_ics_single_occurrence(array $event, DateTimeImmutable $range_start, DateTimeImmutable $range_end): ?array {
    return arrow_ics_occurrence_from($event, $event['start'], $range_start, $range_end);
}

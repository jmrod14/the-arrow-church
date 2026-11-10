<?php
/** Staff names and roles shown on the team page. Saved WordPress values override these defaults. */

function arrow_additional_staff() {
    return [
        ['name' => 'Luis & Kellie Morales', 'title' => 'Ministry Leaders', 'image' => 'staff-09.jpg'],
        ['name' => 'German & Yudith', 'title' => 'Ministry Leaders', 'image' => 'staff-10.jpg'],
    ];
}

function arrow_staff_defaults() {
    $profiles = [
        1 => ['image' => 'staff-01.jpg', 'name' => 'Itzel and Samuel Torres', 'title' => 'Lead Pastors', 'confirmed' => true],
        2 => ['image' => 'staff-02.jpg', 'name' => 'Jordan and Casey Miller', 'title' => 'Community Coordinators', 'confirmed' => false],
        3 => ['image' => 'staff-03.jpg', 'name' => 'Alex Rivera', 'title' => 'Youth Ministry', 'confirmed' => false],
        4 => ['image' => 'staff-04.jpg', 'name' => 'Maria Santos', 'title' => 'Prayer Ministry', 'confirmed' => false],
        5 => ['image' => 'staff-05.jpg', 'name' => 'Daniel Ortiz', 'title' => 'Connections', 'confirmed' => false],
        6 => ['image' => 'staff-06.jpg', 'name' => 'Elena Morales', 'title' => 'Care Ministry', 'confirmed' => false],
        7 => ['image' => 'staff-07.jpg', 'name' => 'Taylor and Morgan Brooks', 'title' => 'Worship Team', 'confirmed' => false],
        8 => ['image' => 'staff-08.jpg', 'name' => 'Sofia Ramirez', 'title' => 'Kids Ministry', 'confirmed' => false],
    ];
    $number = 9;
    foreach (arrow_additional_staff() as $profile) {
        $profiles[$number] = [
            'image' => $profile['image'],
            'name' => $profile['name'],
            'title' => $profile['title'],
            'confirmed' => true,
        ];
        $number++;
    }
    return $profiles;
}

function arrow_staff_name_key($name) {
    $name = html_entity_decode(strip_tags((string) $name), ENT_QUOTES, 'UTF-8');
    $name = strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
    $name = str_replace('&amp;', '&', $name);
    $name = preg_replace('/\s+and\s+/u', ' & ', $name);
    $name = preg_replace('/\s*&\s*/u', ' & ', $name);
    return trim(preg_replace('/\s+/u', ' ', $name));
}

function arrow_staff_image_file($image, $fallback) {
    $image = basename((string) $image);
    if (preg_match('/^staff-\d{2}\.jpg$/', $image)) {
        return $image;
    }
    return $fallback;
}

function arrow_staff_profiles() {
    $saved = get_option('arrow_staff_profiles', []);
    if (!is_array($saved)) {
        $saved = [];
    }
    $profiles = [];
    $seen = [];
    foreach (arrow_staff_defaults() as $number => $default) {
        $override = isset($saved[$number]) && is_array($saved[$number]) ? $saved[$number] : [];
        $name = (string) ($override['name'] ?? $default['name']);
        $name_key = arrow_staff_name_key($name);
        if ($name_key !== '' && isset($seen[$name_key])) {
            continue;
        }
        $profiles[$number] = [
            'image' => arrow_staff_image_file($override['image'] ?? '', $default['image']),
            'name' => $name,
            'title' => (string) ($override['title'] ?? $default['title']),
            'confirmed' => isset($override['confirmed']) ? (bool) $override['confirmed'] : $default['confirmed'],
        ];
        $seen[$name_key] = true;
    }
    $extras = $saved;
    uksort($extras, function ($a, $b) {
        return (int) $a <=> (int) $b;
    });
    foreach ($extras as $key => $override) {
        if (!is_array($override) || isset($profiles[$key])) {
            continue;
        }
        $name = trim((string) ($override['name'] ?? ''));
        $name_key = arrow_staff_name_key($name);
        $image = arrow_staff_image_file($override['image'] ?? '', '');
        if ($name === '' || $image === '' || isset($seen[$name_key])) {
            continue;
        }
        $profiles[$key] = [
            'image' => $image,
            'name' => $name,
            'title' => (string) ($override['title'] ?? ''),
            'confirmed' => !array_key_exists('confirmed', $override) || (bool) $override['confirmed'],
        ];
        $seen[$name_key] = true;
    }
    return $profiles;
}

/** Append new staff by name. Existing option keys and their order stay as they are. */
function arrow_sync_staff_profiles() {
    $saved = get_option('arrow_staff_profiles', []);
    if (!is_array($saved)) {
        $saved = [];
    }
    $known = [];
    $images = [];
    foreach ($saved as $profile) {
        if (!is_array($profile)) {
            continue;
        }
        if (isset($profile['name'])) {
            $known[arrow_staff_name_key($profile['name'])] = true;
        }
        $image = arrow_staff_image_file($profile['image'] ?? '', '');
        if ($image !== '') {
            $images[$image] = true;
        }
    }
    $next = 9;
    foreach (array_keys($saved) as $key) {
        if (is_numeric($key)) {
            $next = max($next, (int) $key + 1);
        }
    }
    $changed = false;
    $slot = 9;
    foreach (arrow_additional_staff() as $profile) {
        $name_key = arrow_staff_name_key($profile['name']);
        $image = arrow_staff_image_file($profile['image'] ?? '', '');
        $slot_saved = array_key_exists($slot, $saved);
        if ($name_key === '' || isset($known[$name_key]) || $slot_saved || ($image !== '' && isset($images[$image]))) {
            $slot++;
            continue;
        }
        while (isset($saved[$next])) {
            $next++;
        }
        $saved[$next] = [
            'name' => $profile['name'],
            'title' => $profile['title'],
            'image' => $profile['image'],
            'confirmed' => true,
        ];
        $known[$name_key] = true;
        if ($image !== '') {
            $images[$image] = true;
        }
        $next++;
        $slot++;
        $changed = true;
    }
    if ($changed) {
        update_option('arrow_staff_profiles', $saved, false);
    }
    return $saved;
}

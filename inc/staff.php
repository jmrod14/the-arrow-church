<?php
/** Staff details shown on the team page. Unconfirmed details are visibly marked as samples. */

function arrow_staff_defaults() {
    return [
        1 => ['image' => 'staff-01.jpg', 'name' => 'Itzel and Samuel Torres', 'title' => 'Lead Pastors', 'confirmed' => true],
        2 => ['image' => 'staff-02.jpg', 'name' => 'Jordan and Casey Miller', 'title' => 'Community Coordinators', 'confirmed' => false],
        3 => ['image' => 'staff-03.jpg', 'name' => 'Alex Rivera', 'title' => 'Youth Ministry', 'confirmed' => false],
        4 => ['image' => 'staff-04.jpg', 'name' => 'Maria Santos', 'title' => 'Prayer Ministry', 'confirmed' => false],
        5 => ['image' => 'staff-05.jpg', 'name' => 'Daniel Ortiz', 'title' => 'Connections', 'confirmed' => false],
        6 => ['image' => 'staff-06.jpg', 'name' => 'Elena Morales', 'title' => 'Care Ministry', 'confirmed' => false],
        7 => ['image' => 'staff-07.jpg', 'name' => 'Taylor and Morgan Brooks', 'title' => 'Worship Team', 'confirmed' => false],
        8 => ['image' => 'staff-08.jpg', 'name' => 'Sofia Ramirez', 'title' => 'Kids Ministry', 'confirmed' => false],
    ];
}

function arrow_staff_profiles() {
    $saved = get_option('arrow_staff_profiles', []);
    if (!is_array($saved)) {
        $saved = [];
    }
    $profiles = [];
    foreach (arrow_staff_defaults() as $number => $default) {
        $override = isset($saved[$number]) && is_array($saved[$number]) ? $saved[$number] : [];
        $profiles[$number] = [
            'image' => $default['image'],
            'name' => (string) ($override['name'] ?? $default['name']),
            'title' => (string) ($override['title'] ?? $default['title']),
            'confirmed' => isset($override['confirmed']) ? (bool) $override['confirmed'] : $default['confirmed'],
        ];
    }
    return $profiles;
}

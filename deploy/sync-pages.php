<?php
/** Provision route records; visible page content remains in this Git theme. */
$routes = [
    'about' => 'About',
    'about/our-team' => 'Our Team',
    'ministries' => 'Ministries',
    'ministries/childrens-ministry' => 'Children’s Ministry',
    'ministries/youth-ministry' => 'Youth Ministry',
    'ministries/young-adults' => 'Young Adults',
    'ministries/prayer-oracion' => 'Prayer / Oración',
    'events' => 'Events',
    'events/calendar' => 'Calendar',
    'events/sunday-worship' => 'Sunday Worship',
    'live' => 'Sermons',
    'livestreams' => 'Past Live Streams',
    'pages' => 'Pages',
    'pages/watch-live' => 'Watch Live',
    'contact' => 'Contact',
    'give' => 'Give',
];

$ids = [];
foreach ($routes as $path => $title) {
    $existing = get_page_by_path($path, OBJECT, 'page');
    if ($existing) {
        $ids[$path] = $existing->ID;
        continue;
    }
    $pieces = explode('/', $path);
    $slug = array_pop($pieces);
    $parent_path = implode('/', $pieces);
    $id = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_name' => $slug,
        'post_parent' => $parent_path ? ($ids[$parent_path] ?? 0) : 0,
        'post_content' => 'Content is maintained in the website theme repository.',
    ], true);
    if (is_wp_error($id)) {
        throw new RuntimeException('Could not create page ' . $path . ': ' . $id->get_error_message());
    }
    $ids[$path] = $id;
    echo 'Created ' . $path . PHP_EOL;
}

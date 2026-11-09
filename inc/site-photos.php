<?php
/** Choose the theme's photos from the normal WordPress Media Library. */

function arrow_photo_fields() {
    $fields = [
        'hero:home' => ['Homepage', 'Main hero photo', 'arrow-home.jpg'],
        'asset:arrow-worship.jpg' => ['Shared photos', 'Worship and live video', 'arrow-worship.jpg'],
        'asset:arrow-kids.jpg' => ['Shared photos', 'Kids ministry', 'arrow-kids.jpg'],
        'asset:arrow-youth.jpg' => ['Shared photos', 'Youth ministry', 'arrow-youth.jpg'],
        'asset:arrow-young-adults.jpg' => ['Shared photos', 'Young adults ministry', 'arrow-young-adults.jpg'],
        'asset:arrow-prayer.jpg' => ['Shared photos', 'Prayer ministry', 'arrow-prayer.jpg'],
        'asset:arrow-pastors.jpg' => ['Shared photos', 'Pastors Samuel and Itzel', 'arrow-pastors.jpg'],
        'asset:arrow-team-rossy.jpg' => ['Shared photos', 'Rossy Marte', 'arrow-team-rossy.jpg'],
        'asset:arrow-home.jpg' => ['Shared photos', 'Church gathering', 'arrow-home.jpg'],
        'asset:arrow-logo.png' => ['Brand', 'Church logo', 'arrow-logo.png'],
    ];
    $pages = require get_template_directory() . '/pages/data.php';
    foreach ($pages as $path => $page) {
        $fields['hero:' . $path] = ['Page banners', $page['title'] . ' (' . $path . ')', $page['image']];
    }
    return $fields;
}

function arrow_photo_url($file, $slot = '') {
    $photos = get_option('arrow_site_photos', []);
    if (!is_array($photos)) {
        $photos = [];
    }
    foreach (array_filter([$slot, 'asset:' . $file]) as $key) {
        $id = absint($photos[$key] ?? 0);
        if ($id && wp_attachment_is_image($id)) {
            $url = wp_get_attachment_image_url($id, 'full');
            if ($url) {
                return $url;
            }
        }
    }
    return get_template_directory_uri() . '/assets/' . $file;
}

function arrow_photo_display($file, $slot = '') {
    $settings = get_option('arrow_photo_display', []);
    if (!is_array($settings)) {
        $settings = [];
    }
    $fallback_y = $slot === 'hero:home' ? 45 : (strpos($slot, 'hero:') === 0 ? 42 : 50);
    $value = $settings[$slot] ?? $settings['asset:' . $file] ?? [];
    if (!is_array($value)) {
        $value = [];
    }
    return [
        'fit' => ($value['fit'] ?? ($file === 'arrow-logo.png' ? 'contain' : 'cover')) === 'contain' ? 'contain' : 'cover',
        'x' => max(0, min(100, (int) ($value['x'] ?? 50))),
        'y' => max(0, min(100, (int) ($value['y'] ?? $fallback_y))),
    ];
}

function arrow_photo_style($file, $slot = '', $background = false) {
    $display = arrow_photo_display($file, $slot);
    $fit = $background ? 'background-size' : 'object-fit';
    $position = $background ? 'background-position' : 'object-position';
    return $fit . ':' . $display['fit'] . ';' . $position . ':' . $display['x'] . '% ' . $display['y'] . '%;';
}

add_action('admin_menu', function () {
    add_menu_page('Site Photos', 'Site Photos', 'manage_options', 'arrow-site-photos', 'arrow_render_photo_admin', 'dashicons-format-gallery', 25);
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_arrow-site-photos') {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_script('arrow-site-photos', get_template_directory_uri() . '/inc/site-photos.js', [], '1.1', true);
    wp_enqueue_style('arrow-site-photos', get_template_directory_uri() . '/inc/site-photos.css', [], '1.1');
});

function arrow_render_photo_admin() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $saved = get_option('arrow_site_photos', []);
    if (!is_array($saved)) {
        $saved = [];
    }
    $group = '';
    ?>
    <div class="wrap arrow-photo-admin">
        <h1>Site Photos</h1>
        <p>Choose a photo, adjust how it fits, then save. <strong>Show whole image</strong> keeps every edge visible. <strong>Fill frame</strong> looks fuller; use the position controls to choose which part stays in view.</p>
        <?php if (isset($_GET['updated']) && $_GET['updated'] === '1') : ?><div class="notice notice-success is-dismissible"><p>Site photos saved.</p></div><?php endif; ?>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
            <input type="hidden" name="action" value="arrow_save_photos">
            <?php wp_nonce_field('arrow_save_photos'); ?>
            <?php foreach (arrow_photo_fields() as $key => $field) :
                if ($group !== $field[0]) {
                    if ($group !== '') {
                        echo '</div>';
                    }
                    $group = $field[0];
                    echo '<h2>' . esc_html($group) . '</h2><div class="arrow-photo-grid">';
                }
                $id = absint($saved[$key] ?? 0);
                $photo = arrow_photo_url($field[2], $key);
                $display = arrow_photo_display($field[2], $key);
                $default = strpos($key, 'asset:') === 0 ? get_template_directory_uri() . '/assets/' . $field[2] : arrow_photo_url($field[2]);
                ?>
                <div class="arrow-photo-card" data-default="<?php echo esc_url($default); ?>">
                    <h3><?php echo esc_html($field[1]); ?></h3>
                    <img src="<?php echo esc_url($photo); ?>" alt="Preview of <?php echo esc_attr($field[1]); ?>" style="<?php echo esc_attr(arrow_photo_style($field[2], $key)); ?>">
                    <input type="hidden" name="photos[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($id); ?>">
                    <div class="arrow-photo-actions"><button type="button" class="button arrow-choose-photo">Choose photo</button><button type="button" class="button-link arrow-reset-photo" <?php disabled(!$id); ?>>Use original</button></div>
                    <label class="arrow-fit-label">Display
                        <select name="display[<?php echo esc_attr($key); ?>][fit]" class="arrow-fit">
                            <option value="cover" <?php selected($display['fit'], 'cover'); ?>>Fill frame</option>
                            <option value="contain" <?php selected($display['fit'], 'contain'); ?>>Show whole image</option>
                        </select>
                    </label>
                    <label class="arrow-position-label">Move left / right <input type="range" name="display[<?php echo esc_attr($key); ?>][x]" min="0" max="100" value="<?php echo esc_attr($display['x']); ?>" class="arrow-position-x"></label>
                    <label class="arrow-position-label">Move up / down <input type="range" name="display[<?php echo esc_attr($key); ?>][y]" min="0" max="100" value="<?php echo esc_attr($display['y']); ?>" class="arrow-position-y"></label>
                </div>
            <?php endforeach; ?>
            </div>
            <?php submit_button('Save site photos'); ?>
        </form>
    </div>
    <?php
}

add_action('admin_post_arrow_save_photos', function () {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to change site photos.');
    }
    check_admin_referer('arrow_save_photos');
    $submitted = isset($_POST['photos']) && is_array($_POST['photos']) ? wp_unslash($_POST['photos']) : [];
    $saved = [];
    foreach (arrow_photo_fields() as $key => $field) {
        $id = absint($submitted[$key] ?? 0);
        if ($id && wp_attachment_is_image($id)) {
            $saved[$key] = $id;
        }
    }
    update_option('arrow_site_photos', $saved, false);
    $submitted_display = isset($_POST['display']) && is_array($_POST['display']) ? wp_unslash($_POST['display']) : [];
    $display = [];
    foreach (arrow_photo_fields() as $key => $field) {
        $value = isset($submitted_display[$key]) && is_array($submitted_display[$key]) ? $submitted_display[$key] : [];
        $display[$key] = [
            'fit' => ($value['fit'] ?? '') === 'contain' ? 'contain' : 'cover',
            'x' => max(0, min(100, (int) ($value['x'] ?? 50))),
            'y' => max(0, min(100, (int) ($value['y'] ?? 50))),
        ];
    }
    update_option('arrow_photo_display', $display, false);
    wp_safe_redirect(admin_url('admin.php?page=arrow-site-photos&updated=1'));
    exit;
});

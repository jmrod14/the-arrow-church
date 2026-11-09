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

add_action('admin_menu', function () {
    add_menu_page('Site Photos', 'Site Photos', 'manage_options', 'arrow-site-photos', 'arrow_render_photo_admin', 'dashicons-format-gallery', 25);
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_arrow-site-photos') {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_script('arrow-site-photos', get_template_directory_uri() . '/inc/site-photos.js', [], '1.0', true);
    wp_enqueue_style('arrow-site-photos', get_template_directory_uri() . '/inc/site-photos.css', [], '1.0');
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
        <p>Choose photos from the Media Library, then save. Photos already on the site stay in place until you replace them. Page banners can be changed separately.</p>
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
                ?>
                <div class="arrow-photo-card" data-default="<?php echo esc_url(get_template_directory_uri() . '/assets/' . $field[2]); ?>">
                    <h3><?php echo esc_html($field[1]); ?></h3>
                    <img src="<?php echo esc_url($photo); ?>" alt="Preview of <?php echo esc_attr($field[1]); ?>">
                    <input type="hidden" name="photos[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($id); ?>">
                    <div class="arrow-photo-actions"><button type="button" class="button arrow-choose-photo">Choose photo</button><button type="button" class="button-link arrow-reset-photo" <?php disabled(!$id); ?>>Use original</button></div>
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
    wp_safe_redirect(admin_url('admin.php?page=arrow-site-photos&updated=1'));
    exit;
});

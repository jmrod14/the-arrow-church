# The Arrow Church website

A custom WordPress theme for The Arrow Church, with a previewable HTML homepage and WordPress subpages. The theme uses the church's own photos and content, includes live and archived video, an events calendar, giving through the church's existing Tithely account, and a WordPress contact form.

## Change photos in WordPress

Sign in at `/wp-admin/`, open **Site Photos** in the left menu, choose replacements from the Media Library, adjust how each image fits, and click **Save site photos**. **Show whole image** keeps the entire photo visible; **Fill frame** fills the shape and lets you move the visible portion with the horizontal and vertical sliders. The homepage hero, shared photos, logo, and individual page banners can be changed without editing code. **Use original** restores a photo shipped with the theme. Uploads and display settings remain in WordPress when the theme is deployed again from Git. Elementor is not required for photo changes; the page layouts are still maintained in the theme.

## Live preview

The Lightsail preview is at https://arrow.184.198.12.185.sslip.io/. The production domain still serves the existing church website. The instance has a static IP and an automatically renewing HTTPS certificate for the preview hostname. Change the WordPress URLs and issue a certificate for `thearrowchurch.org` when DNS is ready to move.

## Preview locally

Open `index.html` locally to preview the design. The contact form works after the theme is deployed to WordPress.

## Before launch

- Confirm the published service schedule. The location, times, phone, livestream, and social links came from the church's existing site.
- Review the church photos and logo copied from the existing site in `assets/`.
- The contact form saves a private Contact Message in WordPress; this path was tested on Lightsail. The instance has no outgoing mail transport, so configure SMTP or another mail service for notifications. Until then, check Contact Messages in the WordPress dashboard.
- The events list, calendar, recurring Sunday worship page, and calendar subscriptions are hosted here. Giving opens the church's existing secure Tithely giving page; verify that account remains active before retiring the old website.
- Point DNS to the Lightsail static IP and configure HTTPS for the production domain before switching it.

## Git deployment

`deploy/post-receive` validates PHP, synchronizes the theme to `/var/www/html/wp-content/themes/the-arrow-church`, provisions the page routes, and refreshes WordPress permalinks. The page copy lives in `pages/data.php` and `pages/content.php`; the WordPress page records supply the URLs. The private SSH keys stay outside Git. The theme is active on Lightsail. On this Windows workspace, use `deploy/publish.ps1 -KeyPath <private-key-path> -KnownHostsPath <known-hosts-path>` to send the current `main` commit as a Git bundle and deploy it. The instance uses static IP `184.198.12.185`. SSH access is restricted to the approved client IP in the Lightsail firewall.

# The Arrow Church website

A custom WordPress theme for The Arrow Church, with a previewable HTML homepage. The theme uses the church's own photos and content, embeds its YouTube livestream, and includes a WordPress contact form.

## Live preview

The Lightsail preview is at https://arrow.184.198.12.185.sslip.io/. The production domain still serves the existing church website. The instance has a static IP and an automatically renewing HTTPS certificate for the preview hostname. Change the WordPress URLs and issue a certificate for `thearrowchurch.org` when DNS is ready to move.

## Preview locally

Open `index.html` locally to preview the design. The contact form works after the theme is deployed to WordPress.

## Before launch

- Confirm the published service schedule. The location, times, phone, livestream, and social links came from the church's existing site.
- Review the church photos and logo copied from the existing site in `assets/`.
- The contact form saves a private Contact Message in WordPress; this path was tested on Lightsail. The instance has no outgoing mail transport, so configure SMTP or another mail service for notifications. Until then, check Contact Messages in the WordPress dashboard.
- Events and giving still link to the existing church site. Move those records or services before retiring it.
- Point DNS to the Lightsail static IP and configure HTTPS for the production domain before switching it.

## Git deployment

`deploy/post-receive` validates PHP and synchronizes the theme to `/var/www/html/wp-content/themes/the-arrow-church`. The private SSH key stays outside Git. The theme is already active on the Lightsail WordPress instance. On this Windows workspace, use `deploy/publish.ps1 -KeyPath <private-key-path> -KnownHostsPath <known-hosts-path>` to send the current `main` commit as a Git bundle and deploy it. The instance uses static IP `184.198.12.185`. SSH access is restricted to the approved client IP in the Lightsail firewall.

# The Arrow Church website

A custom WordPress theme for The Arrow Church, with a previewable HTML homepage. The theme uses the church's own photos and content, embeds its YouTube livestream, and includes a WordPress contact form.

## Preview locally

Open `index.html` locally to preview the design. The contact form works after the theme is deployed to WordPress.

## Before launch

- Confirm the published service schedule. The location, times, phone, livestream, and social links came from the church's existing site.
- Review the church photos and logo copied from the existing site in `assets/`.
- The contact form saves a private Contact Message in WordPress and attempts email to WordPress's admin email. Verify mail delivery and configure SMTP before directing visitors to it.
- Events and giving still link to the existing church site. Move those records or services before retiring it.
- Point DNS to a Lightsail static IP and configure HTTPS before switching the public domain.

## Git deployment

The repository can push directly to the Lightsail bare Git repository. `deploy/post-receive` validates PHP and synchronizes the theme to `/var/www/html/wp-content/themes/the-arrow-church`. The private SSH key stays outside Git. Activate the theme using WordPress CLI after the first push.

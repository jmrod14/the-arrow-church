# The Arrow Church website

A responsive, static website for The Arrow Church. It uses plain HTML, CSS, and JavaScript, so it can be served by Nginx or Apache on AWS Lightsail without a build step.

## Preview locally

From this directory, run `python -m http.server 8000`, then open `http://localhost:8000`.

## Before launch

- Confirm the published service schedule before launch. The location, service times, phone number, livestream, and social links were taken from the church's existing homepage.
- Replace the editorial stock photography with approved church photos.
- Add confirmed links for giving and events as those become available.
- Point DNS to the Lightsail instance and configure HTTPS when deploying. Serve this directory as the web root.

Contact buttons use the phone number shown on the existing site. The image in `assets/community-hero.png` was generated for this redesign and should be replaced with approved church photography when available.

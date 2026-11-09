# The Arrow Church website

A responsive, static website for The Arrow Church. It uses plain HTML, CSS, and JavaScript, so it can be served by Nginx or Apache on AWS Lightsail without a build step.

## Preview locally

From this directory, run `python -m http.server 8000`, then open `http://localhost:8000`.

## Before launch

- Confirm the church's location, service schedule, and preferred contact address. The current domain was unreachable during development, so those details were not guessed.
- Replace the editorial stock photography with approved church photos.
- Add confirmed links for sermons, giving, events, and social profiles as those become available.
- Point DNS to the Lightsail instance and configure HTTPS when deploying. Serve this directory as the web root.

The contact buttons currently open `hello@thearrowchurch.org`; verify that mailbox before publishing.

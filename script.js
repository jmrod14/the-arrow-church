const toggle = document.querySelector('.menu-toggle');
const nav = document.querySelector('#main-nav');
const header = document.querySelector('.header');
const updateHeader = () => header?.classList.toggle('scrolled', window.scrollY > 24);
updateHeader();
window.addEventListener('scroll', updateHeader, { passive: true });
toggle.addEventListener('click', () => {
  const open = toggle.getAttribute('aria-expanded') !== 'true';
  toggle.setAttribute('aria-expanded', String(open));
  toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  nav.classList.toggle('open', open);
});
nav.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
  nav.classList.remove('open');
  toggle.setAttribute('aria-expanded', 'false');
  toggle.setAttribute('aria-label', 'Open menu');
}));
document.querySelector('#year').textContent = new Date().getFullYear();
document.querySelector('.live-play')?.addEventListener('click', () => {
  const frame = document.querySelector('.live-frame iframe');
  frame.src = frame.dataset.src;
  frame.hidden = false;
  document.querySelector('.live-poster').hidden = true;
});
const contactStatus = document.querySelector('.contact-status');
if (contactStatus?.textContent === '__ARROW_CONTACT_STATUS__') contactStatus.textContent = 'The form will be active on the WordPress site.';

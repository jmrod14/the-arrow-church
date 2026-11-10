const toggle = document.querySelector('.menu-toggle');
const nav = document.querySelector('#main-nav');
const header = document.querySelector('.header');
const updateHeader = () => header?.classList.toggle('scrolled', window.scrollY > 24);
updateHeader();
window.addEventListener('scroll', updateHeader, { passive: true });
const setMenuOpen = (open) => {
  toggle.setAttribute('aria-expanded', String(open));
  toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  nav.classList.toggle('open', open);
};
toggle.addEventListener('click', () => setMenuOpen(toggle.getAttribute('aria-expanded') !== 'true'));
nav.querySelector('.menu-close')?.addEventListener('click', () => {
  setMenuOpen(false);
  toggle.focus();
});
nav.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
  setMenuOpen(false);
}));
document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && nav.classList.contains('open')) {
    setMenuOpen(false);
    toggle.focus();
  }
});
document.addEventListener('pointerdown', event => {
  if (nav.classList.contains('open') && !header.contains(event.target)) setMenuOpen(false);
});
document.querySelector('#year').textContent = new Date().getFullYear();
document.querySelector('.live-play')?.addEventListener('click', () => {
  const frame = document.querySelector('.live-frame iframe');
  frame.src = frame.dataset.src;
  frame.hidden = false;
  document.querySelector('.live-poster').hidden = true;
});
const contactStatus = document.querySelector('.contact-status');
if (contactStatus?.textContent === '__ARROW_CONTACT_STATUS__') contactStatus.textContent = 'The form will be active on the WordPress site.';

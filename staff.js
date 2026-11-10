(() => {
  const track = document.querySelector('.staff-track');
  if (!track) return;
  const cards = [...track.querySelectorAll('.staff-card')];
  const previous = document.querySelector('.staff-prev');
  const next = document.querySelector('.staff-next');
  const counter = document.querySelector('.staff-counter');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  const cardStep = () => cards[0].getBoundingClientRect().width + parseFloat(getComputedStyle(track).gap || 0);
  const visibleCount = () => Math.max(1, Math.round(track.clientWidth / cardStep()));
  const currentIndex = () => Math.max(0, Math.min(cards.length - 1, Math.round(track.scrollLeft / cardStep())));
  const update = () => {
    const first = currentIndex();
    const last = Math.min(cards.length, first + visibleCount());
    previous.disabled = first === 0;
    next.disabled = last >= cards.length;
    counter.textContent = `${String(first + 1).padStart(2, '0')}–${String(last).padStart(2, '0')} / ${String(cards.length).padStart(2, '0')}`;
  };
  const move = (direction) => track.scrollBy({ left: direction * cardStep(), behavior: reducedMotion.matches ? 'auto' : 'smooth' });
  previous.addEventListener('click', () => move(-1));
  next.addEventListener('click', () => move(1));
  let frame;
  track.addEventListener('scroll', () => {
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(update);
  }, { passive: true });
  window.addEventListener('resize', update);
  update();
})();

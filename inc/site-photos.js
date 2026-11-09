document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.arrow-photo-card').forEach((card) => {
    const input = card.querySelector('input');
    const preview = card.querySelector('img');
    const reset = card.querySelector('.arrow-reset-photo');
    const fit = card.querySelector('.arrow-fit');
    const x = card.querySelector('.arrow-position-x');
    const y = card.querySelector('.arrow-position-y');
    const updatePreview = () => {
      preview.style.objectFit = fit.value;
      preview.style.objectPosition = `${x.value}% ${y.value}%`;
    };
    [fit, x, y].forEach((control) => control.addEventListener('input', updatePreview));
    card.querySelector('.arrow-choose-photo').addEventListener('click', () => {
      const picker = wp.media({ title: 'Choose a site photo', button: { text: 'Use this photo' }, library: { type: 'image' }, multiple: false });
      picker.on('select', () => {
        const image = picker.state().get('selection').first().toJSON();
        input.value = image.id;
        preview.src = image.url;
        reset.disabled = false;
      });
      picker.open();
    });
    reset.addEventListener('click', () => {
      input.value = '';
      preview.src = card.dataset.default;
      reset.disabled = true;
    });
  });
});

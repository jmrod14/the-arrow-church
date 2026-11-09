document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.arrow-photo-card').forEach((card) => {
    const input = card.querySelector('input');
    const preview = card.querySelector('img');
    const reset = card.querySelector('.arrow-reset-photo');
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

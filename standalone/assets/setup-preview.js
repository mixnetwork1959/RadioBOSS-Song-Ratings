(() => {
  'use strict';

  const preview = document.querySelector('#setup-preview');
  if (!preview) return;

  const accent = document.querySelector('#setup-accent');
  const background = document.querySelector('#setup-background');
  const text = document.querySelector('#setup-text');
  const radius = document.querySelector('#preview-radius');
  const size = document.querySelector('#preview-size');

  const update = () => {
    preview.style.setProperty('--preview-accent', accent?.value || '#2563eb');
    preview.style.setProperty('--preview-bg', background?.value || '#111827');
    preview.style.setProperty('--preview-text', text?.value || '#eef4ff');
    preview.classList.toggle('square', radius?.value === 'square');
    preview.classList.toggle('compact', size?.value === 'compact');
  };

  [accent, background, text, radius, size].forEach((field) => field?.addEventListener('input', update));
  update();
})();


(() => {
  'use strict';

  const script = document.currentScript;
  if (!script) return;
  const base = new URL('.', script.src).href.replace(/\/$/, '');
  const origin = new URL(base).origin;
  const frames = [];

  const mount = () => {
    document.querySelectorAll('.rbsr-embed:not([data-rbsr-mounted])').forEach((container) => {
      const station = container.dataset.station || '';
      const mode = container.dataset.mode === 'player' ? 'player' : 'ratings';
      if (!station) return;
      const frame = document.createElement('iframe');
      frame.src = `${base}/widget.php?station=${encodeURIComponent(station)}&mode=${mode}`;
      frame.title = container.dataset.title || 'Song rating';
      frame.loading = 'lazy';
      frame.allow = 'autoplay';
      frame.style.cssText = `display:block;width:100%;max-width:680px;height:${mode === 'player' ? 390 : 330}px;border:0;overflow:hidden;background:transparent`;
      container.dataset.rbsrMounted = '1';
      container.appendChild(frame);
      frames.push({ frame, station });
    });
  };

  window.addEventListener('message', (event) => {
    if (event.origin !== origin || !event.data || event.data.type !== 'rbsr:resize') return;
    const item = frames.find(({ frame }) => frame.contentWindow === event.source);
    if (!item) return;
    const height = Math.max(220, Math.min(900, Number(event.data.height) || 330));
    item.frame.style.height = `${height}px`;
  });

  const existing = window.RBSR || {};
  window.RBSR = Object.assign(existing, {
    setTrack(track = {}) {
      frames.forEach(({ frame, station }) => {
        if (!track.station || track.station === station) {
          frame.contentWindow?.postMessage({ type: 'rbsr:set-track', track }, origin);
        }
      });
    },
    mount
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount, { once: true });
  } else {
    mount();
  }
})();


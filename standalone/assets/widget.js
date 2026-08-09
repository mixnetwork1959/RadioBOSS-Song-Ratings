(() => {
  'use strict';

  const config = window.RBSR_STANDALONE_CONFIG || {};
  const endpoints = config.endpoints || {};
  const labels = config.labels || {};
  const root = document.querySelector('.rbsr-widget');
  if (!root) return;

  const visitorKey = 'rbsr_standalone_visitor_id';
  const storage = {
    get(key) { try { return localStorage.getItem(key) || ''; } catch (error) { return ''; } },
    set(key, value) { try { localStorage.setItem(key, value); } catch (error) {} }
  };
  let visitor = storage.get(visitorKey);
  if (!visitor) {
    visitor = (window.crypto && crypto.randomUUID)
      ? crypto.randomUUID()
      : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    storage.set(visitorKey, visitor);
  }

  const station = String(root.dataset.station || config.station || '');
  const title = root.querySelector('.rbsr-title');
  const artist = root.querySelector('.rbsr-artist');
  const cover = root.querySelector('.rbsr-cover');
  const coverPlaceholder = root.querySelector('.rbsr-cover-placeholder');
  const defaultLogo = String(root.dataset.logo || '');
  const message = root.querySelector('.rbsr-message');
  const buttons = [...root.querySelectorAll('[data-rating]')];
  const audio = root.querySelector('.rbsr-audio');
  const play = root.querySelector('.rbsr-play');
  const mute = root.querySelector('.rbsr-mute');
  const volume = root.querySelector('.rbsr-volume');
  let current = null;
  let pollTimer = null;
  let reconnectTimer = null;
  let playbackRequested = false;
  let previousVolume = volume ? Number(volume.value) || 0.8 : 0.8;

  const setButtonsEnabled = (enabled) => buttons.forEach((button) => { button.disabled = !enabled; });

  const updateCounts = (counts = {}) => {
    buttons.forEach((button) => {
      const count = button.querySelector('span');
      if (count) count.textContent = String(counts[button.dataset.rating] || 0);
    });
  };

  const restoreSelection = () => {
    const key = current?.songKey ? `rbsr_vote_${current.station}_${current.songKey}` : '';
    const selected = key ? storage.get(key) : '';
    buttons.forEach((button) => {
      const active = button.dataset.rating === selected;
      button.classList.toggle('is-selected', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  };

  const sameStream = (left, right) => {
    try { return new URL(left, window.location.href).href === new URL(right, window.location.href).href; }
    catch (error) { return left === right; }
  };

  const updateMediaSession = (track) => {
    if (!('mediaSession' in navigator) || !('MediaMetadata' in window)) return;
    navigator.mediaSession.metadata = new MediaMetadata({
      title: track.title || labels.unknown,
      artist: track.artist || track.stationName || '',
      album: track.stationName || '',
      artwork: track.art ? [{ src: track.art }] : []
    });
  };

  const applyTrack = (track, fetchCounts = false) => {
    if (!track || !track.artist || !track.title) return;
    const trackStation = String(track.station || station);
    if (trackStation !== station) return;
    const changed = !current || current.artist !== track.artist || current.title !== track.title;
    current = {
      station,
      stationName: String(track.stationName || ''),
      artist: String(track.artist),
      title: String(track.title),
      art: String(track.art || ''),
      stream: String(track.stream || ''),
      songKey: String(track.songKey || '')
    };
    if (title) title.textContent = current.title;
    if (artist) artist.textContent = current.artist;
    if (cover) {
      const image = current.art || defaultLogo;
      if (image) {
        cover.src = image;
        cover.hidden = false;
        if (coverPlaceholder) coverPlaceholder.hidden = true;
      } else {
        cover.removeAttribute('src');
        cover.hidden = true;
        if (coverPlaceholder) coverPlaceholder.hidden = false;
      }
    }
    if (changed) {
      message.textContent = '';
      updateMediaSession(current);
    }
    if (track.counts) updateCounts(track.counts);
    setButtonsEnabled(true);
    if (audio && current.stream && !sameStream(audio.src, current.stream)) {
      const resume = playbackRequested;
      audio.src = current.stream;
      if (resume) audio.play().catch(() => {});
    }
    if (current.songKey) restoreSelection();
    else if (fetchCounts) loadCounts();
  };

  const loadCounts = async () => {
    if (!current) return;
    const query = new URLSearchParams({ station, artist: current.artist, title: current.title });
    try {
      const response = await fetch(`${endpoints.ratings}?${query}`, { cache: 'no-store' });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || 'ratings');
      current.songKey = result.songKey || '';
      updateCounts(result.counts);
      restoreSelection();
    } catch (error) {
      updateCounts({});
    }
  };

  const loadNowPlaying = async () => {
    try {
      const response = await fetch(`${endpoints.nowPlaying}?station=${encodeURIComponent(station)}`, { cache: 'no-store' });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || 'now-playing');
      applyTrack(result, false);
    } catch (error) {
      if (!current) {
        if (title) title.textContent = labels.offline || 'Track information is currently unavailable.';
        if (artist) artist.textContent = '';
        setButtonsEnabled(false);
      }
    }
    clearTimeout(pollTimer);
    pollTimer = setTimeout(loadNowPlaying, Number(config.pollInterval) || 15000);
  };

  buttons.forEach((button) => {
    button.addEventListener('click', async () => {
      if (!current) return;
      setButtonsEnabled(false);
      try {
        const response = await fetch(endpoints.vote, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            station,
            artist: current.artist,
            title: current.title,
            rating: button.dataset.rating,
            visitor
          })
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || labels.voteError);
        current.songKey = result.songKey || current.songKey;
        updateCounts(result.counts);
        storage.set(`rbsr_vote_${station}_${current.songKey}`, result.rating);
        restoreSelection();
        message.textContent = labels.thanks || 'Thank you!';
      } catch (error) {
        message.textContent = error.message || labels.voteError;
      } finally {
        setButtonsEnabled(true);
      }
    });
  });

  if (audio && play && mute && volume) {
    const reconnect = () => {
      if (!playbackRequested || !audio.src) return;
      clearTimeout(reconnectTimer);
      reconnectTimer = setTimeout(() => audio.play().catch(reconnect), 2000);
    };
    const updateVolumeIcon = () => {
      const silent = audio.muted || audio.volume === 0;
      mute.textContent = silent ? '🔇' : (audio.volume < 0.5 ? '🔉' : '🔊');
      mute.setAttribute('aria-label', silent ? labels.unmute : labels.mute);
      mute.title = silent ? labels.unmute : labels.mute;
    };
    play.addEventListener('click', async () => {
      if (audio.paused) {
        playbackRequested = true;
        try { await audio.play(); }
        catch (error) { message.textContent = labels.offline; reconnect(); }
      } else {
        playbackRequested = false;
        clearTimeout(reconnectTimer);
        audio.pause();
      }
    });
    audio.addEventListener('play', () => {
      play.textContent = '❚❚';
      play.setAttribute('aria-label', labels.pause);
      root.classList.add('is-playing');
    });
    audio.addEventListener('pause', () => {
      play.textContent = '▶';
      play.setAttribute('aria-label', labels.play);
      root.classList.remove('is-playing');
    });
    ['stalled', 'error', 'ended'].forEach((name) => audio.addEventListener(name, reconnect));
    volume.addEventListener('input', () => {
      audio.volume = Number(volume.value);
      audio.muted = false;
      if (audio.volume > 0) previousVolume = audio.volume;
      updateVolumeIcon();
    });
    mute.addEventListener('click', () => {
      if (audio.muted || audio.volume === 0) {
        audio.muted = false;
        audio.volume = previousVolume || 0.8;
        volume.value = String(audio.volume);
      } else {
        previousVolume = audio.volume;
        audio.muted = true;
      }
      updateVolumeIcon();
    });
    audio.volume = Number(volume.value);
    updateVolumeIcon();
  }

  const receiveTrack = (track) => applyTrack(track, true);
  window.RBSR = Object.assign(window.RBSR || {}, { setTrack: receiveTrack });
  window.addEventListener('message', (event) => {
    if (event.source !== window.parent || !event.data || event.data.type !== 'rbsr:set-track') return;
    receiveTrack(event.data.track || {});
  });

  if (window.parent !== window) {
    const reportHeight = () => window.parent.postMessage({ type: 'rbsr:resize', height: document.documentElement.scrollHeight + 4 }, '*');
    if ('ResizeObserver' in window) new ResizeObserver(reportHeight).observe(document.body);
    window.addEventListener('load', reportHeight);
  }

  setButtonsEnabled(false);
  loadNowPlaying();
})();


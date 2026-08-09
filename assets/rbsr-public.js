(() => {
  'use strict';

  const config = window.RBSR_CONFIG || {};
  const labels = config.labels || {};
  const visitorKey = 'rbsr_visitor_id';
  const activeStationKey = 'rbsr_active_station_v1';
  const storage = {
    get(key) {
      try { return localStorage.getItem(key) || ''; } catch (error) { return ''; }
    },
    set(key, value) {
      try { localStorage.setItem(key, value); } catch (error) {
        // Ratings still work if browser storage is blocked.
      }
    }
  };

  let visitor = storage.get(visitorKey);
  if (!visitor) {
    visitor = (window.crypto && crypto.randomUUID)
      ? crypto.randomUUID()
      : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    storage.set(visitorKey, visitor);
  }

  const emitTrack = (track) => {
    window.dispatchEvent(new CustomEvent('rbsr:track', { detail: track || {} }));
  };

  window.RBSR = Object.assign(window.RBSR || {}, {
    setTrack(track) {
      emitTrack(track);
    }
  });

  document.querySelectorAll('.rbsr-widget').forEach((root) => {
    const stationSelect = root.querySelector('.rbsr-station');
    const title = root.querySelector('.rbsr-title');
    const artist = root.querySelector('.rbsr-artist');
    const cover = root.querySelector('.rbsr-cover');
    const coverPlaceholder = root.querySelector('.rbsr-cover-placeholder');
    const message = root.querySelector('.rbsr-message');
    const buttons = [...root.querySelectorAll('[data-rating]')];
    const audio = root.querySelector('.rbsr-audio');
    const play = root.querySelector('.rbsr-play');
    const mute = root.querySelector('.rbsr-mute');
    const volume = root.querySelector('.rbsr-volume');
    const source = root.dataset.source || 'api';
    let current = null;
    let pollTimer = null;
    let reconnectTimer = null;
    let playbackRequested = false;
    let previousVolume = volume ? Number(volume.value) || 0.8 : 0.8;

    const stationSlug = () => String(stationSelect ? stationSelect.value : root.dataset.station || '');

    const setButtonsEnabled = (enabled) => {
      buttons.forEach((button) => { button.disabled = !enabled; });
    };

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
      try {
        return new URL(left, window.location.href).href === new URL(right, window.location.href).href;
      } catch (error) {
        return left === right;
      }
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
      const station = String(track.station || stationSlug());
      if (station !== stationSlug()) return;

      const songKey = track.songKey || '';
      const changed = !current
        || current.station !== station
        || current.artist !== track.artist
        || current.title !== track.title;

      current = {
        station,
        stationName: track.stationName || '',
        artist: String(track.artist),
        title: String(track.title),
        art: String(track.art || ''),
        stream: String(track.stream || ''),
        songKey
      };

      if (title) title.textContent = current.title;
      if (artist) artist.textContent = current.artist;
      if (cover) {
        if (current.art) {
          cover.src = current.art;
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

      if (songKey) {
        restoreSelection();
      } else if (fetchCounts) {
        loadCounts();
      }
    };

    const loadCounts = async () => {
      if (!current) return;
      const query = new URLSearchParams({ artist: current.artist, title: current.title });
      try {
        const response = await fetch(
          `${config.restUrl}ratings/${encodeURIComponent(current.station)}?${query}`,
          { cache: 'no-store' }
        );
        if (!response.ok) throw new Error('ratings');
        const result = await response.json();
        if (current && result.songKey) {
          current.songKey = result.songKey;
          updateCounts(result.counts);
          restoreSelection();
        }
      } catch (error) {
        updateCounts({});
      }
    };

    const loadNowPlaying = async () => {
      const station = stationSlug();
      try {
        const response = await fetch(
          `${config.restUrl}now-playing/${encodeURIComponent(station)}`,
          { cache: 'no-store' }
        );
        if (!response.ok) throw new Error('now-playing');
        const track = await response.json();
        applyTrack(track, false);
      } catch (error) {
        if (!current) {
          if (title) title.textContent = labels.offline || 'Track information is currently unavailable.';
          if (artist) artist.textContent = '';
          setButtonsEnabled(false);
        }
      }
      clearTimeout(pollTimer);
      pollTimer = setTimeout(loadNowPlaying, 15000);
    };

    window.addEventListener('rbsr:track', (event) => {
      applyTrack(event.detail || {}, true);
    });

    if (stationSelect && stationSelect.tagName === 'SELECT') {
      stationSelect.addEventListener('change', () => {
        root.dataset.station = stationSlug();
        current = null;
        updateCounts({});
        setButtonsEnabled(false);
        message.textContent = '';
        const option = stationSelect.selectedOptions[0];
        if (option?.dataset.color) root.style.setProperty('--rbsr-accent', option.dataset.color);
        if (title) title.textContent = source === 'external' ? labels.waiting : labels.loading;
        if (artist) artist.textContent = '';
        if (audio) {
          playbackRequested = false;
          audio.pause();
          audio.removeAttribute('src');
          audio.load();
        }
        clearTimeout(pollTimer);
        if (source === 'api') loadNowPlaying();
      });
    }

    buttons.forEach((button) => {
      button.addEventListener('click', async () => {
        if (!current || !current.artist || !current.title) return;
        setButtonsEnabled(false);
        try {
          const response = await fetch(`${config.restUrl}vote`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              station: current.station,
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
          storage.set(`rbsr_vote_${current.station}_${current.songKey}`, result.rating);
          restoreSelection();
          message.textContent = labels.thanks;
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
          try {
            await audio.play();
          } catch (error) {
            message.textContent = labels.offline;
            reconnect();
          }
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
        storage.set(activeStationKey, JSON.stringify({
          station: stationSlug(),
          stationName: current?.stationName || '',
          playing: true,
          updatedAt: Date.now(),
          source: 'rbsr-demo-player'
        }));
      });
      audio.addEventListener('pause', () => {
        play.textContent = '▶';
        play.setAttribute('aria-label', labels.play);
        root.classList.remove('is-playing');
      });
      audio.addEventListener('stalled', reconnect);
      audio.addEventListener('error', reconnect);
      audio.addEventListener('ended', reconnect);
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

    if (source === 'api') {
      loadNowPlaying();
    } else {
      setButtonsEnabled(false);
    }
  });
})();

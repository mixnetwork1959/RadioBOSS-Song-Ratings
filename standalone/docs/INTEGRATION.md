# Standalone integration

## Variant 1: widget below an existing player

This is the recommended placement. Add the generated ratings embed immediately below the existing player. The widget polls the configured Now Playing API every 15 seconds.

## Variant 2: separate rating page

Link a button such as **Rate the current song** to:

~~~text
https://your-site.example/song-ratings/?station=main-station
~~~

The audio may continue playing in the original tab while the rating page remains open in a second tab.

## Variant 3: included neutral player

Use the generated embed with `data-mode="player"`. The stream starts only after a listener action, respecting browser autoplay restrictions.

## JavaScript loader

~~~html
<div class="rbsr-embed" data-station="main-station" data-mode="ratings"></div>
<script src="https://your-site.example/song-ratings/embed.js" defer></script>
~~~

For multiple widgets, add several `rbsr-embed` containers and include `embed.js` only once.

## Optional existing-player metadata bridge

The Now Playing API is the normal source. A custom player may additionally push an immediate update after its metadata changes:

~~~js
window.RBSR.setTrack({
  station: 'main-station',
  artist: currentArtist,
  title: currentTitle,
  art: currentCoverUrl
});
~~~

The station ID must match an enabled station in the standalone administration.


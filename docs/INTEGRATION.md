# Existing player and REST integration

## Recommended existing-player integration

Place the external-source widget on the page:

~~~text
[radioboss_song_ratings station="main-station" source="external"]
~~~

Call window.RBSR.setTrack() each time the existing player receives new metadata:

~~~js
function onPlayerMetadata(metadata) {
  window.RBSR.setTrack({
    station: 'main-station',
    artist: metadata.artist,
    title: metadata.title,
    art: metadata.cover || ''
  });
}
~~~

The widget then:

1. calculates the song identity through the server,
2. loads the current counts,
3. enables the rating buttons,
4. stores the listener's selected rating.

The station ID must match a station enabled in **Song Ratings > Settings**.

## If scripts load in a different order

Call the integration after the WordPress page is ready:

~~~js
window.addEventListener('load', () => {
  if (!window.RBSR) return;

  window.RBSR.setTrack({
    station: 'main-station',
    artist: currentArtist,
    title: currentTitle,
    art: currentCoverUrl
  });
});
~~~

Continue calling setTrack() whenever the title changes.

## REST endpoints

Base URL:

~~~text
/wp-json/radioboss-song-ratings/v1/
~~~

### Read configured Now Playing data

~~~http
GET /now-playing/main-station
~~~

### Read rating counts for a known song

~~~http
GET /ratings/main-station?artist=Example%20Artist&title=Example%20Song
~~~

Response:

~~~json
{
  "station": "main-station",
  "songKey": "sha256-value",
  "counts": {
    "dislike": 0,
    "ok": 1,
    "love": 4
  }
}
~~~

### Submit or update a vote

~~~http
POST /vote
Content-Type: application/json
~~~

~~~json
{
  "station": "main-station",
  "artist": "Example Artist",
  "title": "Example Song",
  "rating": "love",
  "visitor": "persistent-random-browser-id"
}
~~~

The visitor value must contain 16 to 128 letters, numbers, underscores, or hyphens. Do not use an email address or another personal identifier.

## Direct custom button integration

A custom player may use the REST API without rendering the provided widget. The integration must still:

- use an enabled station ID,
- send the exact current artist and title,
- keep a random visitor ID in the browser,
- use only dislike, ok, or love,
- handle HTTP 400 and 429 responses,
- update displayed counts from the returned response.

Using the shortcode widget is recommended because it already implements these rules, accessibility states, rate-limit messages, and local selection state.


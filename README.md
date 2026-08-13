# RadioBOSS Song Ratings

RadioBOSS Song Ratings is an unofficial community WordPress plugin for collecting listener feedback on the song that is currently playing.

The plugin is player-independent. A station can keep its existing web player and add only the rating widget. A neutral demo player is included for testing or for stations that do not already have a player.

## What it does

- Displays Dislike, It's okay, and Love it buttons.
- Stores one current rating per browser visitor and song.
- Lets a listener change an earlier rating.
- Shows live vote totals beside the buttons.
- Provides a protected WordPress dashboard with filters, scores, recommendations, and song details.
- Supports up to four independently configured stations.
- Uses a separate SongSync `songs.json` catalog for every station.
- Supports RadioBOSS installations using either MySQL or SQLite through SongSync.
- Reads AzuraCast Now Playing JSON and simple artist/title JSON.
- Accepts metadata directly from an existing JavaScript player.
- Includes a setup wizard with catalog validation.
- Keeps all station shortcodes visible on the Settings page.
- Can supply the rating table to Radio Music Analytics.

## v1.1.1 architecture

RadioBOSS Song Ratings no longer needs direct access to the RadioBOSS music database.

The data flow is:

```text
RadioBOSS MySQL or SQLite
        ↓
     SongSync
        ↓
station-specific songs.json
        ↓
RadioBOSS Song Ratings
```

Each station must use its own catalog URL. For example:

```text
Main: https://example.com/radioboss-data/main/public/songs.json
Rock: https://example.com/radioboss-data/rock/public/songs.json
```

This keeps stations separated and makes the ratings workflow identical whether RadioBOSS uses MySQL or SQLite locally.

The ratings themselves are stored in the normal WordPress database. Replacing or refreshing `songs.json` does not overwrite existing votes.

## What it does not require

- Listeners do not need an account.
- A station does not need to replace its current web player.
- The demo player is optional.
- Radio Music Analytics and SongSync are optional integrations, not runtime requirements.
- No RadioBOSS MySQL host, username, password, or database credentials are required by this plugin.

## Requirements

- WordPress 6.0 or newer
- PHP 8.0 or newer
- MySQL or MariaDB for WordPress itself
- SongSync with a public `songs.json` URL for every configured station
- A current-song source: either an existing player that knows artist/title or a public JSON Now Playing endpoint

Browsers generally cannot read ICY metadata reliably from a raw audio stream. Use the metadata already available in your player, an AzuraCast endpoint, or another JSON Now Playing endpoint.

## Installation

1. Download the release ZIP.
2. In WordPress, open **Plugins > Add New > Upload Plugin**.
3. Upload the ZIP, install it, and activate **RadioBOSS Song Ratings**.
4. Click **Start Setup Wizard**.
5. Configure the station ID, Now Playing source and the station's SongSync `songs.json` URL.
6. The wizard validates that the catalog URL returns valid JSON containing artist/title entries.
7. Add the shortcode shown on the final wizard page to a WordPress page.

The plugin creates its own WordPress table. RadioBOSS database credentials do not need to be entered.

## Integration modes

### 1. Keep an existing player

Add the rating-only widget:

```text
[radioboss_song_ratings station="main-station" source="external"]
```

When the player's metadata changes, pass it to the widget:

```js
window.RBSR.setTrack({
  station: 'main-station',
  artist: currentArtist,
  title: currentTitle,
  art: currentCoverUrl
});
```

The station value must match the Station ID configured in the wizard.

### 2. Let the widget read a metadata API

Configure the station's Now Playing API and add:

```text
[radioboss_song_ratings station="main-station"]
```

The widget refreshes the current track every 15 seconds and displays the rating buttons. It does not play audio.
### 3. Use the optional demo player

Configure the Now Playing API and stream URL, then add:

```text
[radioboss_rating_player station="main-station"]
```

## Song matching

Before a vote is stored, artist and title are resolved against the `songs.json` catalog for the selected station. Main and Rock catalogs are never mixed.

The ratings identity is station-specific, so the same song can be rated independently on two stations. Multiple physical copies of the same song can share the same rating identity when their normalized artist/title metadata matches.

## Supported metadata JSON

AzuraCast's public Now Playing response works directly. Simple JSON formats are also accepted:

```json
{
  "artist": "Example Artist",
  "title": "Example Song",
  "art": "https://radio.example/covers/example.jpg",
  "stream": "https://radio.example/listen/station/radio.mp3"
}
```

Nested `current`, `current_song`, or `song` objects are supported. Optional next-song data can use `next`, `next_song`, or the AzuraCast `playing_next.song` structure.

## Rating logic

Allowed values are `dislike`, `ok`, and `love`.

A random browser visitor ID is generated locally. Only a salted hash of that ID is stored. The unique database key is station + song + visitor, so another click updates the existing vote instead of creating a duplicate.

## Radio Music Analytics integration

The plugin stores votes in:

```text
<wordpress-prefix>rbsr_song_votes
```

For a standard WordPress prefix this is `wp_rbsr_song_votes`. Set `DB_TABLE` in Radio Music Analytics to the actual table name.

The schema contains:

- station
- song_key
- artist
- title
- visitor_hash
- rating
- created_at
- updated_at

SongSync provides the current music catalog for chart generation, cleanup previews, and rotation analysis.

## Privacy and abuse protection

- No listener account, email address, or name is collected.
- Raw visitor IDs are never stored in the database.
- IP addresses are not stored in the rating table.
- Public vote requests are rate-limited.
- WordPress salts are used for visitor hashes.
- The admin dashboard requires the `manage_options` capability.
- Uninstalling or deactivating the plugin does not silently delete ratings.

## More documentation

- [German installation guide](docs/INSTALLATION-DE.md)
- [Existing player and REST integration](docs/INTEGRATION.md)
- [Migration from the legacy player plugin](docs/MIGRATION.md)

## License and project status

GPL-2.0-or-later. This is an unofficial community project and is not affiliated with or endorsed by DJSoft.

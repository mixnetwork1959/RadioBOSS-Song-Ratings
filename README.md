# RadioBOSS Song Ratings

RadioBOSS Song Ratings is an unofficial community project for collecting listener feedback on the song that is currently playing.

The project is player-independent. A station can keep its existing web player and add only the rating widget. A configurable neutral player is included for stations that do not already have a player.

## Two editions

| Edition | Best for | Installation |
| --- | --- | --- |
| WordPress plugin | WordPress websites | Upload the plugin ZIP and use a shortcode |
| [Standalone Edition](standalone/README.md) | HTML, PHP, custom, or non-WordPress websites | Upload the folder, open `/setup/`, and copy the generated embed code |

The Standalone Edition can share an existing MySQL or MariaDB database. Its wizard detects compatible rating tables already used by WordPress or Analytics and lets the user reuse one or create a new table. A separate database is not required.

## Downloads

- [WordPress plugin v1.0.1](downloads/RadioBOSS-Song-Ratings-v1.0.1.zip)
- [Standalone Edition v1.0.1](downloads/RadioBOSS-Song-Ratings-Standalone-v1.0.1.zip)

Use the WordPress ZIP through **Plugins > Add New > Upload Plugin**. For a website without WordPress, unpack and upload the Standalone ZIP, then open its `/setup/` directory in a browser.

## Recommended placement

Place the rating widget **directly below the existing web player**. The player does not need to be replaced or changed. Alternatively, use a separate rating page in a second tab, or use the included neutral player with ratings.

## What it does

- Displays Dislike, It's okay, and Love it buttons.
- Stores one current rating per browser visitor and song.
- Lets a listener change an earlier rating.
- Shows live vote totals beside the buttons.
- Provides a protected WordPress dashboard with filters, scores, recommendations, and song details.
- Supports up to four independently configured stations.
- Reads AzuraCast Now Playing JSON and simple artist/title JSON.
- Accepts metadata directly from an existing JavaScript player.
- Includes a four-step setup wizard.
- Keeps all station shortcodes visible on the Settings page.
- Can supply the rating table to Radio Music Analytics.

## What it does not require

- Listeners do not need an account.
- A station does not need to replace its current web player.
- The included neutral player is optional.
- Radio Music Analytics and SongSync are optional integrations, not runtime requirements.

## Requirements

- WordPress 6.0 or newer
- PHP 8.0 or newer
- MySQL or MariaDB
- A current-song source: either an existing player that knows artist/title or a public JSON Now Playing endpoint

Browsers generally cannot read ICY metadata reliably from a raw audio stream. Use the metadata already available in your player, an AzuraCast endpoint, or another JSON Now Playing endpoint.

## Installation

1. Download the release ZIP.
2. In WordPress, open **Plugins > Add New > Upload Plugin**.
3. Upload the ZIP, install it, and activate **RadioBOSS Song Ratings**.
4. Click **Start Setup Wizard**.
5. Choose one of the three integration modes.
6. Add the shortcode shown on the final wizard page to a WordPress page.

The plugin creates its own WordPress table. Database credentials do not need to be entered.

## The three setup modes

### 1. Keep an existing player

Add the rating-only widget:

~~~text
[radioboss_song_ratings station="main-station" source="external"]
~~~

When the player's metadata changes, pass it to the widget:

~~~js
window.RBSR.setTrack({
  station: 'main-station',
  artist: currentArtist,
  title: currentTitle,
  art: currentCoverUrl
});
~~~

The station value must match the Station ID configured in the wizard.

### 2. Let the widget read a metadata API

Configure the station's Now Playing API and add:

~~~text
[radioboss_song_ratings station="main-station"]
~~~

The widget refreshes the current track every 15 seconds and displays the rating buttons. It does not play audio.

### 3. Use the included neutral player

Configure the Now Playing API and stream URL, then add:

~~~text
[radioboss_rating_player station="main-station"]
~~~

The included player is intentionally neutral and can be styled with the configured accent color.

## Supported metadata JSON

AzuraCast's public Now Playing response works directly. Simple JSON formats are also accepted:

~~~json
{
  "artist": "Example Artist",
  "title": "Example Song",
  "art": "https://radio.example/covers/example.jpg",
  "stream": "https://radio.example/listen/station/radio.mp3"
}
~~~

Nested current, current_song, or song objects are supported. Optional next-song data can use next, next_song, or the AzuraCast playing_next.song structure.

## Shortcode options

| Shortcode | Purpose |
| --- | --- |
| [radioboss_song_ratings] | Rating-only widget |
| [radioboss_rating_player] | Optional player plus ratings |
| station="station-id" | Selects a configured station |
| source="api" | Reads the configured Now Playing URL |
| source="external" | Waits for metadata from an existing player |
| show_track="no" | Hides the title/artist display and shows only the voting area |

The legacy alias [radio_song_rating] is retained for easier migration.

## Rating logic

Allowed values are:

- dislike
- ok
- love

A random browser visitor ID is generated locally. Only a salted hash of that ID is stored. The unique database key is station + song + visitor, so another click updates the existing vote instead of creating a duplicate.

The dashboard uses these default recommendations:

- Popular: at least five votes, at least 70% Love it, score at least +40
- Review: at least five votes, at least 60% Dislike, score at most -30
- Observe: enough votes, but neither Popular nor Review
- Not enough votes: fewer than five votes

## Radio Music Analytics integration

The plugin stores votes in:

~~~text
<wordpress-prefix>rbsr_song_votes
~~~

For a standard WordPress prefix this is wp_rbsr_song_votes. Set DB_TABLE in Radio Music Analytics to the actual table name.

The schema remains compatible with the rating queries used by Radio Music Analytics:

- station
- song_key
- artist
- title
- visitor_hash
- rating
- created_at
- updated_at

SongSync can provide the current music catalog to Radio Music Analytics for chart generation, cleanup previews, and rotation analysis.

## Privacy and abuse protection

- No listener account, email address, or name is collected.
- Raw visitor IDs are never stored in the database.
- IP addresses are not stored in the rating table.
- Public vote requests are rate-limited.
- WordPress salts are used for visitor hashes.
- The admin dashboard requires the manage_options capability.
- Uninstalling or deactivating the plugin does not silently delete ratings.

## More documentation

- [German installation guide](docs/INSTALLATION-DE.md)
- [Existing player and REST integration](docs/INTEGRATION.md)
- [Migration from the legacy player plugin](docs/MIGRATION.md)

## License and project status

GPL-2.0-or-later. This is an unofficial community project and is not affiliated with or endorsed by DJSoft.

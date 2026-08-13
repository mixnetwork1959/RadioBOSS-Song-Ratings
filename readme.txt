=== RadioBOSS Song Ratings ===
Contributors: mixnetwork1959
Tags: radio, radioboss, ratings, player, azuracast, songsync
Requires at least: 6.0
Requires PHP: 8.0
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add listener song ratings to an existing player, a metadata widget, or the included neutral demo player.

== Description ==

RadioBOSS Song Ratings stores Dislike, It's okay, and Love it feedback for the current song.

Each station uses its own SongSync songs.json catalog. This means the ratings plugin works the same way whether RadioBOSS uses MySQL or SQLite locally, and no RadioBOSS database login details are required by the plugin.

An existing web player can remain in place and pass its current artist/title to the rating widget. The plugin can alternatively read an AzuraCast or compatible JSON endpoint. A neutral demo player is included but is not required.

Features:

* Setup wizard with SongSync catalog validation
* Separate songs.json catalog per station
* RadioBOSS MySQL and SQLite support through SongSync
* Rating-only shortcode
* Existing player JavaScript bridge
* Optional neutral player
* Up to four stations
* Protected WordPress dashboard
* Rating counts, filters, scores, recommendations, and song details
* REST endpoints
* Rate limiting and privacy-friendly visitor hashing

This is an unofficial community project and is not affiliated with or endorsed by DJSoft.

== Installation ==

1. Upload the plugin ZIP in WordPress.
2. Activate RadioBOSS Song Ratings.
3. Start the setup wizard.
4. Select an existing player, a metadata API, or the demo player.
5. Enter the SongSync songs.json URL for the station.
6. The wizard validates the catalog and, where required, the Now Playing source.
7. Add the shortcode shown by the wizard to a page.

== Shortcodes ==

Rating widget:

[radioboss_song_ratings station="main-station"]

Existing player:

[radioboss_song_ratings station="main-station" source="external"]

Optional demo player:

[radioboss_rating_player station="main-station"]

== Privacy ==

The plugin stores a salted hash of a random browser visitor ID. It does not require listener accounts and does not store raw visitor IDs in the ratings table.

== Changelog ==

= 1.1.1 =
* Added SongSync songs.json URL and validation to the setup wizard.
* RadioBOSS MySQL and SQLite are supported through SongSync without RadioBOSS database credentials.

= 1.1.0 =
* Added separate SongSync songs.json catalogs per station.
* Canonicalized track metadata against the selected station catalog.
* Prevented Main/Rock catalog mixing.

= 1.0.1 =
* Added a permanent shortcode overview for every enabled station.

= 1.0.0 =
* Initial public release.

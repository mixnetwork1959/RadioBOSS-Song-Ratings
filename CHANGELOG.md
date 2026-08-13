## 1.1.0
- Use a separate SongSync `songs.json` catalog URL per station as the authoritative music catalog.
- RadioBOSS MySQL/SQLite is now transparent to the ratings plugin; SongSync handles the local database.
- Canonicalize Now Playing and vote metadata against the selected station catalog before creating `song_key`.
- Prevent Main/Rock catalog mixing by resolving strictly within the selected station.
- No RadioBOSS database credentials are required by this plugin.

# Changelog

## 1.0.1

- Added a permanent shortcode overview to the Settings page.
- Lists API widget, existing-player widget, and demo-player shortcodes for every enabled station.
- Shortcode fields can be selected with one click for easier copying.

## 1.0.0

- Initial public release.
- Independent rating-only widget.
- Optional neutral demo player.
- Existing-player JavaScript integration.
- AzuraCast and generic JSON metadata support.
- Three rating values: Dislike, It's okay, and Love it.
- One changeable rating per visitor and song.
- WordPress ratings dashboard with filters, recommendations, and details.
- Four-step setup wizard with metadata connection test.
- Support for up to four stations.
- REST API for current metadata, counts, and votes.
- Rate limiting and salted visitor hashing.
- Automatic non-destructive copy from the compatible legacy vote table.
- Radio Music Analytics integration documentation.

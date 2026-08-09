# Changelog

## Standalone 1.0.1

- Added automatic discovery of existing compatible Song Ratings tables.
- Added an explicit choice between reusing an existing table and creating a new standalone table.
- Added vote and rated-song counts to the table selector.
- Added the active database table name to Standalone Settings.
- Kept backward compatibility with v1.0.0 table-prefix configurations.

## Standalone 1.0.0

- Added a non-WordPress PHP edition with a five-step setup wizard.
- Uses an existing MySQL or MariaDB database and creates its prefixed ratings table automatically.
- Added three integration variants: widget below an existing player, separate rating page, and included neutral player.
- Added configurable colors, logo, language, size, corners, cover visibility, and custom CSS.
- Added responsive JavaScript and plain iFrame embedding.
- Added a protected ratings dashboard, support for four stations, rate limiting, and automatic setup locking.

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

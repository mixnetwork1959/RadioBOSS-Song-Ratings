# RadioBOSS Song Ratings Standalone v1.0.1

Version 1.0.1 improves database setup for websites that already use the WordPress edition or Radio Music Analytics.

## New in 1.0.1

- Detects existing tables by their compatible Song Ratings schema.
- Shows each detected table's exact name, vote count, and rated-song count.
- Lets the user explicitly reuse an existing table or create a separate new table.
- Keeps backward compatibility with v1.0.0 table-prefix configurations.
- Displays the active ratings table on the Settings page.

## Highlights

- Upload-and-run five-step setup wizard.
- Uses an existing MySQL or MariaDB database; no separate database is required.
- Reuses a selected compatible table or automatically creates a new prefixed table when requested.
- Tests the Now Playing API during setup.
- Recommended rating widget for placement directly below an existing player.
- Separate rating page for use in a second browser tab.
- Included neutral player with play/pause, volume, metadata, cover, and ratings.
- Configurable accent, background, text, logo, language, size, corners, cover visibility, and custom CSS.
- Responsive JavaScript embed and plain iFrame option.
- Protected ratings dashboard with filters, scores, and up to four stations.
- Salted visitor hashes, prepared SQL, vote rate limiting, login rate limiting, and automatic setup locking.

## Installation

Upload the extracted folder to PHP hosting and open `/setup/`. The user supplies an existing database connection, station details, a Now Playing API, and—only for the included player—a direct stream URL.

The database itself must already exist. The wizard reuses a selected compatible table or creates a new one automatically; no SQL import is needed.

## Recommended integration

Keep the existing player and place the generated rating widget immediately below it. The player remains unchanged because the widget reads the current song from the configured metadata API.

## License

GPL-2.0-or-later. This is an unofficial community project and is not affiliated with or endorsed by DJSoft.

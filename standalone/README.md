# RadioBOSS Song Ratings — Standalone Edition

The Standalone Edition adds current-song ratings to websites that do not use WordPress. It runs on ordinary PHP hosting and may share an existing MySQL or MariaDB database—and an existing compatible Song Ratings table—with another application.

## Recommended placement

Keep the website's existing player and place the rating widget **directly below it**. The player does not need to be replaced or modified. Song Ratings reads artist and title independently from the configured Now Playing API.

Three listener experiences are included:

1. rating widget below an existing player;
2. a separate rating page that can stay open in a second tab;
3. an included neutral player with stream controls and ratings.

## Requirements

- PHP 8.0 or newer
- PDO MySQL extension
- MySQL 5.7+, MariaDB 10.3+, or a compatible newer version
- PHP cURL or `allow_url_fopen`
- an existing empty or shared database with permission to create tables
- an AzuraCast or compatible JSON Now Playing endpoint
- a direct HTTP(S) stream URL when using the included player

A separate database is **not** required. The setup wizard detects compatible rating tables already present in the selected database. The user can reuse one of them or create a new prefixed `song_votes` table. Unrelated tables remain untouched.

## Installation

1. Upload the complete folder to a PHP-enabled webspace.
2. Open `https://your-site.example/song-ratings/setup/`.
3. Complete the server check.
4. Enter the existing database credentials.
5. Choose a detected compatible ratings table or create a new prefixed table.
6. Configure the station and test the Now Playing API.
7. Customize the colors, layout, logo, language, and administrator login.
8. Finish the installation. The setup wizard locks itself automatically.
9. Sign in to `/admin/` and open **Embed & Integration**.

The database itself must already exist. When no existing table is selected, the required table is created automatically with `CREATE TABLE IF NOT EXISTS`; no SQL import is necessary.

Compatible tables are identified by their required Song Ratings columns, not merely by their prefix. The wizard displays the number of stored votes and rated songs to make the correct existing table easier to recognize.

## Configurable neutral player

The included player provides:

- play and pause;
- volume and mute;
- current artist and title;
- cover art with station-logo fallback;
- Dislike, It's okay, and Love it buttons;
- normal and compact sizes;
- rounded and square styles;
- accent, background, and text colors;
- English and German widget labels;
- optional custom CSS.

Browser autoplay is intentionally not promised because modern browsers normally require a listener click before audio may start.

## Multiple stations

The administration supports up to four stations. Each station has a stable ID, metadata URL, optional stream URL, logo, and design. All stations can use the same ratings table because every vote contains the station ID.

Do not change a station ID after ratings have been collected. The ID is part of the song identity and database lookup.

## Embedding

The administration generates the exact snippets for every enabled station. The recommended JavaScript loader creates a responsive iFrame automatically:

~~~html
<div class="rbsr-embed" data-station="main-station" data-mode="ratings"></div>
<script src="https://your-site.example/song-ratings/embed.js" defer></script>
~~~

Use `data-mode="player"` for the included player. A plain iFrame alternative and the direct URL for a separate rating page are also displayed in the administration.

## Database compatibility

The default table is `rbsr_song_votes`. Its columns match the WordPress edition and Radio Music Analytics integration:

- `station`
- `song_key`
- `artist`
- `title`
- `visitor_hash`
- `rating`
- `created_at`
- `updated_at`

Allowed rating values are `dislike`, `ok`, and `love`.

## Privacy and protection

- no listener account, name, or email address is collected;
- raw visitor IDs and IP addresses are not stored in MySQL;
- a salted visitor hash is stored for one current rating per browser and song;
- vote and administrator-login requests are rate-limited;
- all SQL values use prepared statements;
- the administrator password is stored as a password hash;
- the generated configuration and runtime storage are protected from web access on Apache.

For Nginx, deny public access to the `/config/` and `/storage/` directories in the server configuration.

## License

GPL-2.0-or-later. This is an unofficial community project and is not affiliated with or endorsed by DJSoft.

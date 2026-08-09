# RadioBOSS Song Ratings v1.0.1

This first public release turns the song-rating function into a neutral, independent project for both WordPress and non-WordPress websites.

Two ready-to-install packages are included: the WordPress plugin and the Standalone Edition for ordinary PHP hosting. Both can keep an existing radio player, use a separate rating page, or use the included neutral player.

## Highlights

- Keep an existing radio player and attach only the rating widget.
- Read current-song data from AzuraCast or compatible JSON.
- Use the included neutral player only when wanted.
- Collect Dislike, It's okay, and Love it feedback.
- Review ratings in a protected WordPress dashboard.
- Configure the first station through a four-step setup wizard.
- Add up to four stations through the settings page.
- Connect the resulting database table to Radio Music Analytics.
- Preserve compatible legacy ratings through a non-destructive migration copy.
- Install the Standalone Edition through a browser-based setup wizard.
- Detect and reuse a compatible existing ratings table instead of creating a duplicate.
- Customize the included standalone player, colors, logo, language, and layout.

## Installation

For WordPress, upload `RadioBOSS-Song-Ratings-v1.0.1.zip` through **Plugins > Add New > Upload Plugin**, activate it, and start the setup wizard.

Without WordPress, unpack and upload `RadioBOSS-Song-Ratings-Standalone-v1.0.1.zip`, open `/setup/`, and follow the server, database, station, and design steps.

## Important

The included player is optional. Stations can keep their current player. WordPress players can pass artist/title to the widget with `window.RBSR.setTrack()`. The Standalone Edition can read the configured Now Playing API independently.

This is an unofficial community project and is not affiliated with or endorsed by DJSoft.

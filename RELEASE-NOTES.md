# RadioBOSS Song Ratings v1.1.1

Version 1.1.1 completes the SongSync-based catalog workflow and makes RadioBOSS MySQL and SQLite installations behave the same way from the ratings plugin's point of view.

## Highlights

- Each station now uses its own SongSync `songs.json` catalog.
- Main and Rock catalogs stay strictly separated.
- RadioBOSS MySQL and SQLite are both supported through SongSync.
- No RadioBOSS database host, username, password, or database name is required by Song Ratings.
- The setup wizard now asks for the station's `songs.json` URL and validates it before setup can continue.
- Now Playing and vote metadata are resolved against the selected station catalog before creating the rating identity.
- Refreshing or replacing `songs.json` does not overwrite stored WordPress ratings.

## Recommended upgrade path

1. Install or update SongSync and make sure every station publishes its own `songs.json`.
2. Update RadioBOSS Song Ratings to v1.1.1.
3. Open **Song Ratings > Settings** and configure the correct catalog URL for every station.
4. For new installations, the setup wizard will request and validate this URL automatically.

Example:

```text
Main: https://example.com/radioboss-data/main/public/songs.json
Rock: https://example.com/radioboss-data/rock/public/songs.json
```

## Important

The WordPress ratings database remains independent from the SongSync catalog. Catalog refreshes can add or remove songs without automatically deleting existing listener votes.

The demo player remains optional. Stations can keep their current player and pass artist/title to the widget with `window.RBSR.setTrack()`.

This is an unofficial community project and is not affiliated with or endorsed by DJSoft.

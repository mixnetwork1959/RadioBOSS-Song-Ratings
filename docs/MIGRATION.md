# Migration from the legacy player plugin

RadioBOSS Song Ratings uses the new WordPress table:

~~~text
<wordpress-prefix>rbsr_song_votes
~~~

On first activation, the plugin checks for the legacy table:

~~~text
<wordpress-prefix>rap_song_votes
~~~

If the legacy table exists, compatible ratings are copied with INSERT IGNORE. The legacy table is not modified or deleted.

## Recommended migration order

1. Back up the WordPress database.
2. Install and activate RadioBOSS Song Ratings.
3. Complete the setup wizard.
4. Verify the copied totals under **Song Ratings > Ratings**.
5. Replace the old rating shortcode with the new widget shortcode.
6. If using an existing player, add the window.RBSR.setTrack() call.
7. Deactivate the legacy player plugin only after the new widget works.
8. Update Radio Music Analytics DB_TABLE to the new table name.

Do not run both voting implementations for an extended period. After the one-time copy, the two plugins would write new votes to different tables.


# Standalone Edition installieren

## Wichtig

Eine MySQL- oder MariaDB-Datenbank ist erforderlich, aber es muss **keine eigene zusätzliche Datenbank** sein. Eine bereits vorhandene WordPress-Datenbank kann mitbenutzt werden. Song Ratings legt darin nur seine eigene Tabelle mit einem eindeutigen Präfix an.

## Installation

1. Die Standalone-ZIP entpacken.
2. Den vollständigen Ordner per FTP auf einen PHP-Webspace hochladen.
3. Im Browser `https://deine-domain.de/song-ratings/setup/` öffnen.
4. Die Serverprüfung durchführen.
5. Datenbankserver, Datenbankname, Benutzer, Passwort und Tabellenpräfix eintragen.
6. **Test connection and find rating tables** anklicken.
7. Eine erkannte kompatible Ratingtabelle auswählen oder eine neue Tabelle erstellen.
8. Sendername, feste Sender-ID und Now-Playing-API eintragen.
9. Bei Verwendung des mitgelieferten Players zusätzlich die direkte Stream-URL eintragen.
10. Farben, Größe, Ecken, Logo, Sprache und Administrator-Zugang konfigurieren.
11. Installation abschließen und anschließend `/admin/` öffnen.

Der Wizard zeigt bei vorhandenen kompatiblen Tabellen deren Namen, Stimmenzahl und Anzahl bewerteter Songs. Dadurch kann beispielsweise eine bereits vom WordPress-Plugin oder von Radio Music Analytics genutzte Tabelle übernommen werden. Wird stattdessen eine neue Tabelle gewählt, erstellt der Wizard sie automatisch. Eine SQL-Datei muss nicht importiert werden. Nach erfolgreicher Installation wird der Setup-Wizard gesperrt.

## Empfohlene Einbindung

Das Bewertungs-Widget sollte direkt unter dem bereits vorhandenen Webplayer eingefügt werden. Der Player muss weder ersetzt noch verändert werden. Das Widget bezieht den aktuellen Titel selbst aus der konfigurierten Now-Playing-API.

Alternativ kann eine eigene Bewertungsseite verwendet werden. Der Player läuft in einem Tab weiter, während der Hörer die Bewertungsseite in einem zweiten Tab öffnet.

Wer keinen eigenen Player besitzt, verwendet den mitgelieferten neutralen Player mit Play/Pause, Lautstärke, Titelanzeige, Cover und Bewertungsbuttons.

## Strato und vergleichbare Webhoster

Wenn nur eine einzige Datenbank verfügbar ist, werden einfach die Zugangsdaten dieser bestehenden Datenbank verwendet. Eine gefundene kompatible Tabelle kann direkt weiterverwendet werden. Das Standardpräfix `rbsr_` erzeugt nur dann die neue Tabelle `rbsr_song_votes`, wenn ausdrücklich **Create a new standalone table** gewählt wird.

Vorhandene Tabellen werden nicht verändert oder gelöscht.

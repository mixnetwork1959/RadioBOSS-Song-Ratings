# Installation und Einrichtung

## Wichtig vorab

Ein neuer Player ist **nicht erforderlich**. RadioBOSS Song Ratings kann an einen bereits vorhandenen Website-Player angeschlossen werden. Der mitgelieferte neutrale Player ist nur eine zusätzliche Möglichkeit.

## Plugin installieren

1. In WordPress **Plugins > Installieren > Plugin hochladen** öffnen.
2. Die Release-ZIP auswählen.
3. **Jetzt installieren** und anschließend **Aktivieren** anklicken.
4. Den Hinweis **Start Setup Wizard** öffnen.

Die Datenbanktabelle wird automatisch angelegt. Es müssen keine Datenbank-Zugangsdaten eingetragen werden.

## Setup-Wizard

### Schritt 1: Integrationsart

Wähle eine der drei Möglichkeiten:

- **Use an existing player:** Der vorhandene Player bleibt bestehen und übergibt Artist und Titel.
- **Read a metadata API:** Das Bewertungs-Widget holt die Metadaten selbst aus einer JSON-API.
- **Use the demo player:** Der neutrale Beispiel-Player zeigt Metadaten, spielt den Stream und enthält die Buttons.

### Schritt 2: Sender

- **Station name:** Sichtbarer Sendername.
- **Station ID:** Technische Kennung in Kleinbuchstaben, zum Beispiel main-station.
- **Accent color:** Farbe des Widgets.

Die Station ID muss bei Shortcode, Player-Anbindung und gespeicherten Bewertungen identisch sein.

### Schritt 3: Metadaten

Bei einem vorhandenen Player ist die Now-Playing-URL optional. Der Player kann Artist und Titel direkt an das Widget übergeben.

Für den API-Modus wird eine öffentliche JSON-URL benötigt. Der Wizard testet, ob die URL erreichbar ist und Artist sowie Titel enthält.

Für den Demo-Player werden benötigt:

- Now-Playing-API
- direkte Stream-URL

### Schritt 4: Fertigstellen

Der Wizard zeigt den passenden Shortcode. Diesen in eine WordPress-Seite, einen Shortcode-Block oder den Page Builder einsetzen.

## Vorhandenen Player anbinden

Shortcode:

~~~text
[radioboss_song_ratings station="main-station" source="external"]
~~~

Sobald der vorhandene Player einen neuen Titel erkennt:

~~~js
window.RBSR.setTrack({
  station: 'main-station',
  artist: currentArtist,
  title: currentTitle,
  art: currentCoverUrl
});
~~~

art ist optional. Artist und Titel sind notwendig.

## Widget ohne Player

Wenn das Widget die Now-Playing-API selbst abfragt:

~~~text
[radioboss_song_ratings station="main-station"]
~~~

Das Widget zeigt den Titel und die Bewertungsbuttons, spielt aber keinen Stream.

## Optionaler Demo-Player

~~~text
[radioboss_rating_player station="main-station"]
~~~

## Mehrere Sender

Unter **Song Ratings > Settings** können bis zu vier Sender aktiviert werden. Jeder Sender besitzt eine eigene ID, API, Stream-Adresse und Farbe. Bei mehreren aktivierten Sendern erscheint im Widget eine Senderauswahl.

## Auswertung

Unter **Song Ratings > Ratings** befinden sich:

- Gesamtzahlen
- Senderfilter
- Empfehlungsfilter
- Mindestanzahl Bewertungen
- Suche nach Artist oder Titel
- sortierbare Tabellen
- Detailansicht mit Bewertungsverlauf

## Direkte Stream-Metadaten

Ein Browser kann ICY-Metadaten eines MP3-Streams häufig nicht direkt und zuverlässig auslesen. Deshalb verwendet das System entweder:

- die Metadaten, die der vorhandene Player bereits besitzt,
- einen AzuraCast-Now-Playing-Endpunkt,
- oder einen anderen JSON-Endpunkt mit Artist und Titel.


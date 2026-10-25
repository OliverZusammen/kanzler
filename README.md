# Kanzlerrennen

Politik-Simulator als Browserspiel: Wahlkampf zum Bundestag, Koalitionsverhandlungen, Regieren oder Opposition,
Stellungnahmen zu echten Ereignissen. Fiktive Parteien, Lage angelehnt an die Umfragen vom Herbst 2026.
Live unter https://kanzler.stache-advisory.online

## Aufbau

```
public/                 Web-Root: index.html (das ganze Spiel), api.php, img/, 404.html
src/lib.php             PHP-Funktionen (DB, Nachrichten-Proxy), außerhalb des Webs
src/schema.sql          MySQL-Tabellen (werden von api.php automatisch angelegt)
src/config.php          ← wird beim Deploy aus /home/ploi/kanzler-config/config.php kopiert, nie im Repo
deploy/ploi-deploy.sh   Deploy-Skript für Ploi
deploy/nginx-kanzler.conf  nginx-Vorlage für die Site
config.example.php      Vorlage für die Konfiguration
```

Das Spiel selbst ist eine einzige HTML-Datei ohne Build-Schritt. `api.php` liefert Konten, Spielstände und
den serverseitigen Nachrichtenabruf (tagesschau.de, 15 Minuten Cache). Ohne `api.php` läuft das Spiel auch,
dann mit Konto und Spielstand nur im Browser.

## Einrichtung in Ploi (Server zusammen-1)

1. DNS bei United Domains: A-Record `kanzler.stache-advisory.online` → 37.27.158.57, AAAA → 2a01:4f9:c015:26b2::1.
2. Ploi → Sites → neue Site: Domain `kanzler.stache-advisory.online`, PHP 8.3, Web-Verzeichnis `/public`.
3. Site → Repository: GitHub `OliverZusammen/Kanzler`, Branch `main`.
4. Deploy-Skript ersetzen durch:
   ```
   cd /home/ploi/kanzler.stache-advisory.online
   git pull origin main
   bash deploy/ploi-deploy.sh
   ```
5. Quick Deploy an, Zero downtime aus, Deploy now. Beim ersten Deploy legt das Skript
   `/home/ploi/kanzler-config/config.php` aus der Vorlage an.
6. Ploi → Databases: Datenbank `kanzler` mit Benutzer `kanzler` anlegen. Zugang per SSH in
   `/home/ploi/kanzler-config/config.php` eintragen, dann noch einmal deployen (kopiert die Config).
7. nginx: Vorlage aus `deploy/nginx-kanzler.conf` einsetzen, Test configuration, nginx Reload.
8. SSL: Let's Encrypt.
9. Prüfen: `https://kanzler.stache-advisory.online/api.php?ping=1` muss `{"ok":true,...}` liefern;
   unter dem Login steht dann „Konten werden auf dem Server gespeichert“.

## Lokal testen

`index.html` direkt öffnen (ohne Server, Daten im Browser) oder im Ordner `public/` einen PHP-Server starten:
`php -S 127.0.0.1:8080` – ohne `src/config.php` nutzt `api.php` automatisch SQLite unter `data/`.

## Was nie ins Repo gehört

`src/config.php`, `.env`, Datenbanken, Nutzerdaten. Siehe `.gitignore`.

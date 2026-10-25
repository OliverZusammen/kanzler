<?php
// Kanzlerrennen – Konfiguration. Diese Vorlage liegt im Repo.
// Die echte Datei liegt NICHT im Repo, sondern in /home/ploi/kanzler-config/config.php
// und wird vom Deploy-Skript nach src/config.php kopiert.
return [
  // 'mysql' auf dem Server, 'sqlite' zum lokalen Testen ohne Datenbankserver
  'db_driver' => 'mysql',
  'db_host'   => '127.0.0.1',
  'db_name'   => 'kanzler',
  'db_user'   => 'kanzler',
  'db_pass'   => 'HIER-PASSWORT-EINTRAGEN',
  // Für sqlite: Pfad zur Datei (Ordner muss beschreibbar sein)
  'sqlite_path' => '/home/ploi/kanzler-data/kanzler.sqlite',

  // Datenordner (Logs, Caches), nie unter public/
  'data_dir' => '/home/ploi/kanzler-data',

  // Nachrichtenquelle, serverseitig abgerufen (umgeht CORS im Browser). Cache in Sekunden.
  'news_url'   => 'https://www.tagesschau.de/api2u/news/?ressort=inland',
  'news_cache' => 900,

  // Später: Claude API für maßgeschneiderte Stellungnahmen. Leer lassen = aus.
  'anthropic_key' => '',

  // Rate-Limit: neue Konten pro Tag und IP
  'register_per_day' => 20,
];

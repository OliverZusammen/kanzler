<?php
// Gemeinsame Funktionen für api.php. Liegt außerhalb von public/.
date_default_timezone_set('Europe/Berlin');

function kr_config(): array {
  $f = __DIR__.'/config.php';
  if (!is_file($f)) {
    // Lokaler Notbetrieb ohne Config: SQLite neben dem Projekt
    return ['db_driver'=>'sqlite','sqlite_path'=>dirname(__DIR__).'/data/kanzler.sqlite','data_dir'=>dirname(__DIR__).'/data',
            'news_url'=>'https://www.tagesschau.de/api2u/news/?ressort=inland','news_cache'=>900,'anthropic_key'=>'','register_per_day'=>20];
  }
  return require $f;
}

function kr_db(array $cfg): PDO {
  if (($cfg['db_driver'] ?? 'mysql') === 'sqlite') {
    $dir = dirname($cfg['sqlite_path']); if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $db = new PDO('sqlite:'.$cfg['sqlite_path']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT UNIQUE NOT NULL, hash TEXT NOT NULL, created INTEGER NOT NULL, last_login INTEGER)');
    $db->exec('CREATE TABLE IF NOT EXISTS sessions (token TEXT PRIMARY KEY, user_id INTEGER NOT NULL, created INTEGER NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS saves (user_id INTEGER PRIMARY KEY, blob TEXT NOT NULL, at INTEGER NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS registrations (ip TEXT NOT NULL, day TEXT NOT NULL, n INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (ip, day))');
    return $db;
  }
  $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $cfg['db_host'], $cfg['db_name']);
  $db = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  foreach (array_filter(array_map('trim', explode(';', file_get_contents(__DIR__.'/schema.sql')))) as $stmt) {
    if (stripos($stmt, 'CREATE TABLE') !== false) $db->exec($stmt);
  }
  return $db;
}

function kr_upsert_save(PDO $db, array $cfg, int $uid, string $blob): void {
  if (($cfg['db_driver'] ?? 'mysql') === 'sqlite') {
    $db->prepare('INSERT INTO saves (user_id,blob,at) VALUES (?,?,?) ON CONFLICT(user_id) DO UPDATE SET blob=excluded.blob, at=excluded.at')->execute([$uid,$blob,(int)(microtime(true)*1000)]);
  } else {
    $db->prepare('INSERT INTO saves (user_id,blob,at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE blob=VALUES(blob), at=VALUES(at)')->execute([$uid,$blob,(int)(microtime(true)*1000)]);
  }
}

function kr_news(array $cfg): array {
  // Serverseitiger Abruf mit Dateicache; der Browser bekommt immer gültiges JSON.
  $dir = $cfg['data_dir'] ?? sys_get_temp_dir(); if (!is_dir($dir)) @mkdir($dir, 0750, true);
  $cache = $dir.'/news-cache.json';
  if (is_file($cache) && time() - filemtime($cache) < ($cfg['news_cache'] ?? 900)) {
    $j = json_decode(file_get_contents($cache), true); if ($j) return $j;
  }
  $ctx = stream_context_create(['http'=>['timeout'=>6,'header'=>"User-Agent: Kanzlerrennen/1.0\r\nAccept: application/json\r\n"]]);
  $raw = @file_get_contents($cfg['news_url'], false, $ctx);
  $j = $raw ? json_decode($raw, true) : null;
  if (!$j || empty($j['news'])) {
    if (is_file($cache)) { $old = json_decode(file_get_contents($cache), true); if ($old) return $old; }
    return ['news'=>[], 'error'=>'Quelle nicht erreichbar'];
  }
  $out = ['news'=>[], 'fetched'=>date('c')];
  foreach ($j['news'] as $n) {
    if (empty($n['title'])) continue;
    $out['news'][] = ['title'=>$n['title'], 'detailweb'=>$n['detailweb'] ?? ($n['shareURL'] ?? ''), 'date'=>$n['date'] ?? ''];
    if (count($out['news']) >= 60) break;
  }
  @file_put_contents($cache, json_encode($out, JSON_UNESCAPED_UNICODE));
  return $out;
}

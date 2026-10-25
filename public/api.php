<?php
// Kanzlerrennen – Konten, Spielstände, Nachrichten. Konfiguration: src/config.php (aus /home/ploi/kanzler-config/).
require __DIR__.'/../src/lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$cfg = kr_config();

if (isset($_GET['ping'])) { echo json_encode(['ok'=>true,'service'=>'kanzlerrennen','news'=>true]); exit; }
if (isset($_GET['news'])) { echo json_encode(kr_news($cfg), JSON_UNESCAPED_UNICODE); exit; }
if (isset($_GET['diag'])) {
  // Diagnose ohne Geheimnisse: Woher kommt die Konfiguration, erreicht PHP die Datenbank?
  $env = kr_env(); $envFile = dirname(__DIR__).'/.env';
  $out = [
    'env_datei' => is_file($envFile) ? 'vorhanden' : 'fehlt',
    'env_schluessel' => array_keys($env),
    'config_php' => is_file(__DIR__.'/../src/config.php') ? 'vorhanden' : 'fehlt',
    'quelle' => (!empty($env['DB_DATABASE']) && !empty($env['DB_USERNAME'])) ? '.env' : (is_file(__DIR__.'/../src/config.php') ? 'src/config.php' : 'Notbetrieb SQLite'),
    'treiber' => $cfg['db_driver'] ?? '?', 'host' => $cfg['db_host'] ?? '-', 'datenbank' => $cfg['db_name'] ?? ($cfg['sqlite_path'] ?? '-'), 'benutzer' => $cfg['db_user'] ?? '-',
    'passwort_gesetzt' => !empty($cfg['db_pass']), 'pdo_treiber' => PDO::getAvailableDrivers(),
  ];
  try { kr_db($cfg); $out['verbindung'] = 'ok'; } catch (Exception $e) { $out['verbindung'] = 'FEHLER: '.$e->getMessage(); }
  echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); exit;
}

$fail = function($msg, $code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; };
try { $db = kr_db($cfg); }
catch (Exception $e) { $fail('Datenbank nicht erreichbar. Konfiguration prüfen.', 500); }

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $in['action'] ?? '';
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

function userFromToken(PDO $db, string $token): ?array {
  if ($token === '') return null;
  $q = $db->prepare('SELECT u.id, u.name FROM sessions s JOIN users u ON u.id=s.user_id WHERE s.token=?');
  $q->execute([$token]); $r = $q->fetch(PDO::FETCH_ASSOC); return $r ?: null;
}
function newSession(PDO $db, int $uid): string {
  $t = bin2hex(random_bytes(24));
  $db->prepare('INSERT INTO sessions (token,user_id,created) VALUES (?,?,?)')->execute([$t,$uid,time()]);
  return $t;
}

switch ($action) {
  case 'register':
    $u = strtolower(trim($in['user'] ?? '')); $p = (string)($in['pass'] ?? '');
    if (!preg_match('/^[a-z0-9_.-]{3,30}$/', $u)) $fail('Ungültiger Benutzername.');
    if (strlen($p) < 4) $fail('Passwort zu kurz.');
    $day = date('Y-m-d');
    $q = $db->prepare('SELECT n FROM registrations WHERE ip=? AND day=?'); $q->execute([$ip,$day]); $n = (int)($q->fetchColumn() ?: 0);
    if ($n >= ($cfg['register_per_day'] ?? 20)) $fail('Zu viele neue Konten von dieser Adresse heute.', 429);
    $q = $db->prepare('SELECT id FROM users WHERE name=?'); $q->execute([$u]);
    if ($q->fetch()) $fail('Benutzername ist vergeben.');
    $db->prepare('INSERT INTO users (name,hash,created,last_login) VALUES (?,?,?,?)')->execute([$u, password_hash($p, PASSWORD_DEFAULT), time(), time()]);
    $uid = (int)$db->lastInsertId();
    if ($n) $db->prepare('UPDATE registrations SET n=n+1 WHERE ip=? AND day=?')->execute([$ip,$day]);
    else $db->prepare('INSERT INTO registrations (ip,day,n) VALUES (?,?,1)')->execute([$ip,$day]);
    echo json_encode(['ok'=>true,'user'=>$u,'token'=>newSession($db,$uid)]); break;

  case 'login':
    $u = strtolower(trim($in['user'] ?? '')); $p = (string)($in['pass'] ?? '');
    $q = $db->prepare('SELECT id, hash FROM users WHERE name=?'); $q->execute([$u]); $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row || !password_verify($p, $row['hash'])) { usleep(300000); $fail('Benutzername oder Passwort falsch.', 401); }
    $db->prepare('UPDATE users SET last_login=? WHERE id=?')->execute([time(), (int)$row['id']]);
    echo json_encode(['ok'=>true,'user'=>$u,'token'=>newSession($db,(int)$row['id'])]); break;

  case 'me':
    $me = userFromToken($db, (string)($in['token'] ?? '')); if (!$me) $fail('Nicht angemeldet.', 401);
    echo json_encode(['ok'=>true,'user'=>$me['name']]); break;

  case 'logout':
    if (!empty($in['token'])) $db->prepare('DELETE FROM sessions WHERE token=?')->execute([$in['token']]);
    echo json_encode(['ok'=>true]); break;

  case 'save':
    $me = userFromToken($db, (string)($in['token'] ?? '')); if (!$me) $fail('Nicht angemeldet.', 401);
    $blob = json_encode($in['blob'] ?? null, JSON_UNESCAPED_UNICODE); if (strlen($blob) > 4000000) $fail('Spielstand zu groß.');
    kr_upsert_save($db, $cfg, (int)$me['id'], $blob);
    echo json_encode(['ok'=>true]); break;

  case 'load':
    $me = userFromToken($db, (string)($in['token'] ?? '')); if (!$me) $fail('Nicht angemeldet.', 401);
    $q = $db->prepare('SELECT payload, saved_at FROM saves WHERE user_id=?'); $q->execute([(int)$me['id']]); $row = $q->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['ok'=>true,'save'=>$row ? ['blob'=>json_decode($row['payload'], true),'at'=>(int)$row['saved_at']] : null]); break;

  case 'clear':
    $me = userFromToken($db, (string)($in['token'] ?? '')); if (!$me) $fail('Nicht angemeldet.', 401);
    $db->prepare('DELETE FROM saves WHERE user_id=?')->execute([(int)$me['id']]);
    echo json_encode(['ok'=>true]); break;

  default: $fail('Unbekannte Aktion.');
}

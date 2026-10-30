<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/questions.php';

// Never print PHP warnings on the page (they break JSON / headers). Errors go to the PHP log.
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

// Make mysqli throw exceptions (so we can show a clear message)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Human friendly hint for the admin page
function db_hint(Throwable $e): string {
    if (USE_PG) {
        if (!extension_loaded('pdo_pgsql')) { return 'PHP extension pdo_pgsql is not enabled.'; }
        return 'Check DATABASE_URL in Render Environment. Details: ' . $e->getMessage();
    }
    $code = (int)$e->getCode();
    if (!extension_loaded('mysqli')) {
        return 'PHP extension "mysqli" is not enabled. In php.ini remove the ; before extension=mysqli, then restart Apache.';
    }
    if ($code === 2002) {
        return 'MySQL is not running (or uses another port). Open XAMPP Control Panel and click Start next to MySQL. If it stops again, click the MySQL "Logs" button.';
    }
    if ($code === 1045) {
        return 'MySQL username or password is wrong. Set DB_USER and DB_PASS in config.php (XAMPP default: root with empty password).';
    }
    return 'Technical details: ' . $e->getMessage();
}

// Run a SELECT and return all rows as arrays
function rows($db, string $sql): array {
    if ($db instanceof PDO) { return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
    $out = [];
    $res = $db->query($sql);
    while ($r = $res->fetch_assoc()) {
        $out[] = $r;
    }
    $res->free();
    return $out;
}

// Run a SELECT and return the first column of the first row
function scalar($db, string $sql) {
    if ($db instanceof PDO) { $v = $db->query($sql)->fetchColumn(); return $v === false ? null : $v; }
    $res = $db->query($sql);
    $r = $res->fetch_row();
    $res->free();
    return $r ? $r[0] : null;
}


// Quote a column name for the active database
function qid(string $id): string {
    return USE_PG ? '"' . $id . '"' : '`' . $id . '`';
}

// Insert one survey response (array of question id => value|null)
function save_response(array $vals): void {
    $db   = db();
    $keys = array_keys($vals);
    $cols = implode(', ', array_map('qid', $keys));
    $ph   = implode(', ', array_fill(0, count($keys), '?'));
    $params = array_values($vals);
    if ($db instanceof PDO) {
        $st = $db->prepare("INSERT INTO responses ($cols) VALUES ($ph)");
        $st->execute($params);
        return;
    }
    $st = $db->prepare("INSERT INTO `responses` ($cols) VALUES ($ph)");
    $st->bind_param(str_repeat('s', count($params)), ...$params);
    $st->execute();
    $st->close();
}

// Postgres connection (Render). Table is created automatically.
function db_pg(): PDO {
    $u = parse_url(DATABASE_URL);
    if (!$u || empty($u['host'])) { throw new RuntimeException('DATABASE_URL is not valid'); }
    $dsn = 'pgsql:host=' . $u['host'] . ';port=' . ($u['port'] ?? 5432)
         . ';dbname=' . ltrim($u['path'] ?? '', '/') . ';sslmode=prefer';
    $pdo = new PDO($dsn, urldecode($u['user'] ?? ''), urldecode($u['pass'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $cols = '';
    foreach (questions() as $id => $_) { $cols .= '"' . $id . '" TEXT NULL, '; }
    $pdo->exec('CREATE TABLE IF NOT EXISTS responses (id SERIAL PRIMARY KEY, created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), ' . rtrim($cols, ', ') . ')');
    $pdo->exec('CREATE INDEX IF NOT EXISTS responses_created_at_idx ON responses (created_at)');
    return $pdo;
}

// Connect (with optional SSL for cloud MySQL)
function connect_db(string $name, int $port): mysqli {
    $m = mysqli_init();
    $flags = 0;
    if (defined('DB_SSL') && DB_SSL) {
        $m->ssl_set(null, null, null, null, null);
        $m->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        $flags = MYSQLI_CLIENT_SSL;
    }
    $m->real_connect(DB_HOST, DB_USER, DB_PASS, $name, $port, null, $flags);
    return $m;
}

function db() {
    static $db = null;
    if ($db !== null) {
        return $db;
    }
    if (USE_PG) {
        return $db = db_pg();
    }
    if (!extension_loaded('mysqli')) {
        throw new RuntimeException('mysqli extension missing');
    }

    // Try the configured port first, then the usual XAMPP ports (3306 / 3307)
    $ports = array_values(array_unique([(int)DB_PORT, 3306, 3307]));
    $last  = null;
    foreach ($ports as $port) {
        try {
            $db = connect_db(DB_NAME, $port);
            break;
        } catch (mysqli_sql_exception $e) {
            $last = $e;
            $code = (int)$e->getCode();
            if ($code === 1049) {
                // database does not exist yet -> create it (works on XAMPP / root)
                $tmp = connect_db('', $port);
                $tmp->query('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $tmp->close();
                $db = connect_db(DB_NAME, $port);
                break;
            }
            if ($code !== 2002) {
                throw $e;      // wrong password etc. -> no point trying other ports
            }
        }
    }
    if (!($db instanceof mysqli)) {
        throw $last ?: new RuntimeException('Database connection failed');
    }
    $db->set_charset('utf8mb4');

    // Create the table automatically (one column per question)
    $cols = '';
    foreach (questions() as $id => $_) {
        $cols .= '`' . $id . '` TEXT NULL, ';
    }
    $db->query(
        'CREATE TABLE IF NOT EXISTS `responses` ('
        . '`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, '
        . '`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, '
        . $cols
        . 'INDEX (`created_at`)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    return $db;
}

// ---- "Interested to join" contacts (name / email / phone) ----
function ensure_contacts($db): void {
    if ($db instanceof PDO) {
        $db->exec('CREATE TABLE IF NOT EXISTS contacts (id SERIAL PRIMARY KEY, created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), name TEXT NOT NULL, email TEXT NOT NULL, phone TEXT NOT NULL)');
        return;
    }
    $db->query('CREATE TABLE IF NOT EXISTS `contacts` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `name` VARCHAR(200) NOT NULL, `email` VARCHAR(200) NOT NULL, `phone` VARCHAR(50) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function save_contact(string $name, string $email, string $phone): void {
    $db = db();
    ensure_contacts($db);
    if ($db instanceof PDO) {
        $db->prepare('INSERT INTO contacts (name, email, phone) VALUES (?, ?, ?)')->execute([$name, $email, $phone]);
        return;
    }
    $st = $db->prepare('INSERT INTO `contacts` (`name`, `email`, `phone`) VALUES (?, ?, ?)');
    $st->bind_param('sss', $name, $email, $phone);
    $st->execute();
    $st->close();
}

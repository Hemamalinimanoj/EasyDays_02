<?php
// ===== Easy Days settings =====
// On Render / online hosting these come from Environment Variables.
// On XAMPP (laptop) the defaults after "?:" are used, so nothing changes there.
function env_or(string $k, $d) { $v = getenv($k); return ($v === false || $v === '') ? $d : $v; }

define('DB_HOST', env_or('DB_HOST', '127.0.0.1'));
define('DB_PORT', (int)env_or('DB_PORT', 3306));
define('DB_USER', env_or('DB_USER', 'root'));
define('DB_PASS', env_or('DB_PASS', ''));
define('DB_NAME', env_or('DB_NAME', 'easydays'));   // created automatically if it does not exist (local only)
// Render Postgres: paste the "Internal Database URL" into DATABASE_URL. When it is set, Postgres is used instead of MySQL.
define('DATABASE_URL', env_or('DATABASE_URL', ''));
define('USE_PG', DATABASE_URL !== '');
date_default_timezone_set(env_or('APP_TZ', 'Asia/Kolkata'));   // times shown in admin page
define('DB_SSL',  env_or('DB_SSL', '0') === '1');   // set DB_SSL=1 for cloud MySQL (Aiven, TiDB, etc.)

// Client login for admin.php  -> set ADMIN_PASS in Render env variables
define('ADMIN_USER', env_or('ADMIN_USER', 'admin'));
define('ADMIN_PASS', env_or('ADMIN_PASS', 'EasyDays@2026'));

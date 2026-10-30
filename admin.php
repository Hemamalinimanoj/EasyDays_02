<?php
require_once __DIR__ . '/db.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

const CHART_COLORS = ['#6f3a78', '#c27ccf', '#f08fb5', '#f5b95b', '#4fb3a1', '#5b8def'];

// Donut chart (pure SVG, no internet / libraries needed). $items = [[label, count], ...]
function donut_svg(array $items): string {
    $total = array_sum(array_column($items, 1));
    $r = 52; $c = 2 * M_PI * $r; $off = 0.0;
    $svg = '<svg viewBox="0 0 140 140" class="donut" role="img"><circle cx="70" cy="70" r="' . $r . '" fill="none" stroke="#f0eaf3" stroke-width="22"/>';
    foreach ($items as $i => $it) {
        if ($total <= 0 || $it[1] <= 0) continue;
        $len = $it[1] / $total * $c;
        $svg .= '<circle cx="70" cy="70" r="' . $r . '" fill="none" stroke="' . CHART_COLORS[$i % count(CHART_COLORS)] . '" stroke-width="22"'
              . ' stroke-dasharray="' . round($len, 2) . ' ' . round($c - $len, 2) . '" stroke-dashoffset="' . round(-$off, 2) . '" transform="rotate(-90 70 70)"/>';
        $off += $len;
    }
    $svg .= '<text x="70" y="68" text-anchor="middle" class="dn">' . (int)$total . '</text><text x="70" y="85" text-anchor="middle" class="dl">answers</text></svg>';
    return $svg;
}

// ---- logout ----
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ---- login ----
$loginError = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['password'])) {
    $okUser = hash_equals(ADMIN_USER, (string)($_POST['username'] ?? ''));
    $okPass = hash_equals(ADMIN_PASS, (string)$_POST['password']);
    if ($okUser && $okPass) {
        session_regenerate_id(true);
        $_SESSION['ok'] = true;
        header('Location: admin.php');
        exit;
    }
    sleep(1);
    $loginError = 'Wrong username or password.';
}
$logged = !empty($_SESSION['ok']);

$dbError = '';
$db = null;
if ($logged) {
    try {
        $db = db();
    } catch (Throwable $e) {
        error_log('EasyDays admin error: ' . $e->getMessage());
        $dbError = 'Could not connect to the database. ' . db_hint($e);
    }
}

// ---- delete ALL old data (responses + interested people) ----
$flash = '';
if ($logged && !empty($_SESSION['ok']) && empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
if ($logged && $db && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['wipe'])) {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? '')) || trim((string)($_POST['confirm'] ?? '')) !== 'DELETE') {
        $flash = 'Nothing was deleted. Type DELETE (capital letters) to confirm.';
    } else {
        try {
            ensure_contacts($db);
            if ($db instanceof PDO) {
                $db->exec('TRUNCATE TABLE responses, contacts RESTART IDENTITY');
            } else {
                $db->query('TRUNCATE TABLE `responses`');
                $db->query('TRUNCATE TABLE `contacts`');
            }
            $flash = 'All old responses and interested-people data were deleted.';
        } catch (Throwable $e) {
            error_log('EasyDays wipe error: ' . $e->getMessage());
            $flash = 'Could not delete the data. ' . db_hint($e);
        }
    }
}

// ---- CSV export: interested people ----
if ($logged && $db && isset($_GET['export_contacts'])) {
    ensure_contacts($db);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="easydays_interested_people.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    $safe = function ($v) { $v = (string)$v; return preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v; };
    fputcsv($out, ['ID', 'Date', 'Name', 'Email', 'Phone'], ',', '"', '');
    foreach (rows($db, 'SELECT * FROM contacts ORDER BY id ASC') as $r) {
        fputcsv($out, [$r['id'], $r['created_at'], $safe($r['name']), $safe($r['email']), $safe($r['phone'])], ',', '"', '');
    }
    fclose($out);
    exit;
}

// ---- CSV export ----
if ($logged && $db && isset($_GET['export'])) {
    $qs = questions();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="easydays_responses.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    $safe = function ($v) {
        $v = (string)$v;
        return preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
    };
    fputcsv($out, array_merge(['ID', 'Date'], array_column($qs, 't')), ',', '"', '');
    foreach (rows($db, 'SELECT * FROM responses ORDER BY id ASC') as $r) {
        $row = [$r['id'], $r['created_at']];
        foreach ($qs as $id => $_) { $row[] = $safe($r[$id] ?? ''); }
        fputcsv($out, $row, ',', '"', '');
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Easy Days – Survey Responses</title>
<link rel="stylesheet" href="admin.css">
</head>
<body>
<?php if (!$logged): ?>
  <div class="login card">
    <h1>Easy Days</h1>
    <p class="muted">Survey responses – please log in</p>
    <?php if ($loginError): ?><p class="err"><?= h($loginError) ?></p><?php endif; ?>
    <form method="post" autocomplete="off">
      <label>Username<input type="text" name="username" required autofocus></label>
      <label>Password<input type="password" name="password" required></label>
      <button class="btn" type="submit">Log in</button>
    </form>
  </div>
<?php else:
  $view = $_GET['view'] ?? 'summary';
  $view = in_array($view, ['responses', 'contacts'], true) ? $view : 'summary';
  $qs = questions();
?>
  <div class="top">
    <div>
      <h1>Easy Days – Survey Responses</h1>
      <p class="muted">Client dashboard</p>
    </div>
    <div class="actions">
      <a class="btn" href="admin.php?export=1">⬇ Download CSV</a>
      <a class="btn" href="admin.php?export_contacts=1">⬇ Interested people CSV</a>
      <a class="btn ghost" href="admin.php?logout=1">Log out</a>
    </div>
  </div>

<?php if ($dbError): ?>
  <div class="card"><p class="err"><?= h($dbError) ?></p></div>
<?php else:
  $total = (int)scalar($db, 'SELECT COUNT(*) FROM responses');
  $last  = scalar($db, 'SELECT MAX(created_at) FROM responses');
  ensure_contacts($db);
  $ctotal = (int)scalar($db, 'SELECT COUNT(*) FROM contacts');
?>
  <?php if ($flash): ?><div class="card"><p class="<?= str_starts_with($flash, 'All old') ? 'okmsg' : 'err' ?>"><?= h($flash) ?></p></div><?php endif; ?>
  <div class="stats">
    <div class="card stat"><b><?= $total ?></b><span>Total responses</span></div>
    <div class="card stat"><b><?= $last ? h(date('d M Y, h:i A', strtotime($last))) : '–' ?></b><span>Latest response</span></div>
    <div class="card stat"><b><?= $ctotal ?></b><span>Interested to join</span></div>
  </div>

  <div class="tabs">
    <a class="<?= $view === 'summary' ? 'on' : '' ?>" href="admin.php?view=summary">Summary</a>
    <a class="<?= $view === 'responses' ? 'on' : '' ?>" href="admin.php?view=responses">All responses</a>
    <a class="<?= $view === 'contacts' ? 'on' : '' ?>" href="admin.php?view=contacts">Interested people</a>
  </div>

<?php if ($view === 'contacts'):
  $crows = rows($db, 'SELECT * FROM contacts ORDER BY id DESC LIMIT 500'); ?>
  <div class="card tablewrap">
  <?php if (!$crows): ?><p class="muted">No one has shared their details yet.</p><?php else: ?>
    <table>
      <thead><tr><th>#</th><th>Date</th><th>Name</th><th>Email</th><th>Phone</th></tr></thead>
      <tbody>
      <?php foreach ($crows as $r): ?>
        <tr><td><?= (int)$r['id'] ?></td><td class="nw"><?= h(date('d M Y, h:i A', strtotime($r['created_at']))) ?></td>
          <td><?= h($r['name']) ?></td><td><?= h($r['email']) ?></td><td><?= h($r['phone']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  </div>

<?php elseif ($total === 0): ?>
  <div class="card"><p class="muted">No responses yet. They will appear here after someone submits the survey.</p></div>

<?php elseif ($view === 'summary'): ?>
  <?php
    // ---- responses per day (last 14 days) ----
    $days = [];
    for ($i = 13; $i >= 0; $i--) { $days[date('Y-m-d', strtotime("-$i day"))] = 0; }
    foreach (rows($db, 'SELECT created_at FROM responses ORDER BY id DESC LIMIT 5000') as $r) {
        $d = date('Y-m-d', strtotime($r['created_at']));
        if (isset($days[$d])) $days[$d]++;
    }
    $maxDay = max(1, max($days));

    // ---- interest summary (how many said Yes) ----
    $interest = [
        'q4'  => 'Would use a digital calendar',
        'q9'  => 'Would chat with a gynaecologist online',
        'q21' => 'Would use the full Easy Days platform',
        'q23' => 'Want to join and give feedback',
    ];
  ?>
  <div class="card">
    <h3>Responses in the last 14 days</h3>
    <svg viewBox="0 0 600 190" class="barchart" role="img">
      <?php $n = count($days); $bw = 600 / $n; $i = 0;
      foreach ($days as $d => $cnt): $bh = $cnt / $maxDay * 130; $x = $i * $bw + 6; ?>
        <rect x="<?= round($x, 1) ?>" y="<?= round(150 - $bh, 1) ?>" width="<?= round($bw - 12, 1) ?>" height="<?= round($bh, 1) ?>" rx="4" fill="#6f3a78"/>
        <?php if ($cnt > 0): ?><text x="<?= round($x + ($bw - 12) / 2, 1) ?>" y="<?= round(144 - $bh, 1) ?>" text-anchor="middle" class="bv"><?= $cnt ?></text><?php endif; ?>
        <text x="<?= round($x + ($bw - 12) / 2, 1) ?>" y="170" text-anchor="middle" class="bx"><?= h(date('d M', strtotime($d))) ?></text>
      <?php $i++; endforeach; ?>
      <line x1="0" y1="150" x2="600" y2="150" stroke="#dadce0"/>
    </svg>
  </div>

  <div class="card">
    <h3>Interest in the app (% who said Yes)</h3>
    <?php foreach ($interest as $id => $label):
      $yes = (int)scalar($db, "SELECT COUNT(*) FROM responses WHERE " . qid($id) . " LIKE 'Yes%'");
      $all = (int)scalar($db, "SELECT COUNT(*) FROM responses WHERE " . qid($id) . " IS NOT NULL");
      $pct = $all ? round($yes / $all * 100) : 0; ?>
      <div class="bar">
        <div class="lbl"><span><?= h($label) ?></span><span><b><?= $pct ?>%</b> (<?= $yes ?> of <?= $all ?>)</span></div>
        <div class="track big"><i style="width:<?= $pct ?>%"></i></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="chartgrid">
  <?php foreach ($qs as $id => $q): if ($q['type'] !== 'choice') continue;
    $rows = rows($db, "SELECT " . qid($id) . " AS v, COUNT(*) AS c FROM responses WHERE " . qid($id) . " IS NOT NULL GROUP BY " . qid($id) . " ORDER BY c DESC");
    $sum = array_sum(array_column($rows, 'c'));
    $items = array_map(fn($r) => [$r['v'], (int)$r['c']], $rows); ?>
    <div class="card">
      <h3><?= h($q['t']) ?></h3>
      <?php if (!$rows): ?><p class="muted">No answers yet.</p><?php else: ?>
      <div class="donutwrap">
        <?= donut_svg($items) ?>
        <ul class="legend">
          <?php foreach ($items as $i => $it): $pct = $sum ? round($it[1] / $sum * 100) : 0; ?>
            <li><i style="background:<?= CHART_COLORS[$i % count(CHART_COLORS)] ?>"></i><span><?= h($it[0]) ?></span><b><?= $pct ?>%</b><em><?= $it[1] ?></em></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>

<?php else:
  $per = 25;
  $page = max(1, (int)($_GET['page'] ?? 1));
  $pages = max(1, (int)ceil($total / $per));
  $page = min($page, $pages);
  $rows = rows($db, 'SELECT * FROM responses ORDER BY id DESC LIMIT ' . (int)$per . ' OFFSET ' . (int)(($page - 1) * $per));
?>
  <div class="card tablewrap">
    <table>
      <thead><tr><th>#</th><th>Date</th>
        <?php foreach ($qs as $q): ?><th><?= h($q['t']) ?></th><?php endforeach; ?>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr><td><?= (int)$r['id'] ?></td><td class="nw"><?= h(date('d M Y, h:i A', strtotime($r['created_at']))) ?></td>
          <?php foreach ($qs as $id => $_): ?><td><?= h($r[$id] ?? '') ?></td><?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="pager">
    <?php if ($page > 1): ?><a class="btn ghost" href="admin.php?view=responses&page=<?= $page - 1 ?>">← Newer</a><?php endif; ?>
    <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?><a class="btn ghost" href="admin.php?view=responses&page=<?= $page + 1 ?>">Older →</a><?php endif; ?>
  </div>
<?php endif; ?>
  <details class="card danger">
    <summary>Delete all old data</summary>
    <p class="muted">This permanently deletes <b>all survey responses and all “interested people” details</b>. It cannot be undone. Download the CSV files first if you need a copy.</p>
    <form method="post" onsubmit="return confirm('Delete ALL data permanently?')">
      <input type="hidden" name="wipe" value="1">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf'] ?? '') ?>">
      <input type="text" name="confirm" placeholder="Type DELETE to confirm" autocomplete="off" required>
      <button class="btn danger-btn" type="submit">Delete everything</button>
    </form>
  </details>
<?php endif; ?>
<?php endif; ?>
</body>
</html>

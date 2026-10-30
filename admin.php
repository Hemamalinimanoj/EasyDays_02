<?php
require_once __DIR__ . '/db.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

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
  <?php foreach ($qs as $id => $q): if ($q['type'] !== 'choice') continue;
    $rows = rows($db, "SELECT " . qid($id) . " AS v, COUNT(*) AS c FROM responses WHERE " . qid($id) . " IS NOT NULL GROUP BY " . qid($id) . " ORDER BY c DESC");
    $sum = array_sum(array_column($rows, 'c')); ?>
    <div class="card">
      <h3><?= h($q['t']) ?></h3>
      <?php if (!$rows): ?><p class="muted">No answers yet.</p><?php endif; ?>
      <?php foreach ($rows as $r): $pct = $sum ? round($r['c'] / $sum * 100) : 0; ?>
        <div class="bar">
          <div class="lbl"><span><?= h($r['v']) ?></span><span><?= (int)$r['c'] ?> (<?= $pct ?>%)</span></div>
          <div class="track"><i style="width:<?= $pct ?>%"></i></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <?php foreach ($qs as $id => $q): if ($q['type'] !== 'text') continue;
    $rows = rows($db, "SELECT created_at, " . qid($id) . " AS v FROM responses WHERE " . qid($id) . " IS NOT NULL ORDER BY id DESC LIMIT 20"); ?>
    <div class="card">
      <h3><?= h($q['t']) ?></h3>
      <?php if (!$rows): ?><p class="muted">No answers yet.</p><?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <p class="quote"><?= nl2br(h($r['v'])) ?></p>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

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
<?php endif; ?>
<?php endif; ?>
</body>
</html>

<?php
//session_start();
require_once 'db_connection.php';

$totalTrips     = 0;
$activeTrips    = 0;
$cancelledTrips = 0;
$totalSeats     = 0;

$res = mysqli_query($conn, "SELECT Status, TotalSeats FROM trip");
while ($row = mysqli_fetch_assoc($res)) {
    $totalTrips++;
    $totalSeats += (int)$row['TotalSeats'];
    if ($row['Status'] === 'Cancelled') $cancelledTrips++;
    elseif (in_array($row['Status'], ['Scheduled','Confirmed'])) $activeTrips++;
}

$recentTrips = [];
$res2 = mysqli_query($conn,
    "SELECT t.TripID, t.Origin, t.Destination, t.DepartureDate, t.DepartureTime,
            t.TotalSeats, t.AvailableSeats, t.Status, b.Bus_Number
     FROM trip t
     JOIN bus b ON t.BusID = b.BusID
     ORDER BY t.TripID DESC
     LIMIT 5");
while ($row = mysqli_fetch_assoc($res2)) {
    $recentTrips[] = $row;
}

$routes = [];
$res3 = mysqli_query($conn, "SELECT Origin, Destination, COUNT(*) AS cnt FROM trip GROUP BY Origin, Destination");
while ($row = mysqli_fetch_assoc($res3)) {
    $routes[] = $row;
}
$maxCnt = 1;
foreach ($routes as $r) { if ($r['cnt'] > $maxCnt) $maxCnt = $r['cnt']; }

function fmtTime($t) {
    if (!$t) return '';
    list($h, $m) = explode(':', $t);
    $h = (int)$h;
    return (($h % 12) ?: 12) . ':' . $m . ' ' . ($h >= 12 ? 'PM' : 'AM');
}
function fmtDate($d) {
    if (!$d) return '';
    $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $p = explode('-', $d);
    return $months[(int)$p[1]-1] . ' ' . (int)$p[2] . ', ' . $p[0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Dashboard — SAII Hajj Transport</title>
  <link rel="stylesheet" href="styles.css"/>
</head>
<body class="page-wrapper">

<!-- ── SHARED HEADER ── -->
<header class="navbar">
  <div class="navbar-inner">
    <a href="admin-dashboard.php" class="nav-logo">
      <img src="image/saii.png" alt="SAII Logo" class="logo-img"/>
    </a>
    <nav class="nav-links" style="align-items:center;justify-content:center;">
      <a href="admin-dashboard.php" class="nav-link active">Dashboard</a>
      <a href="manage-trips.php"    class="nav-link">Manage Trips</a>
      <a href="add-trip.php"        class="nav-link">Add Trip</a>
    </nav>
    <div class="nav-right">
      <span class="role-chip admin">&#9679; Admin</span>
      <span style="color:rgba(255,255,255,.65);font-size:.85rem;">Admin User</span>
      <a href="logout.php" class="btn btn-sm btn-outline-dark">Logout</a>
    </div>
    <button class="nav-toggle" onclick="document.getElementById('nm').classList.toggle('open')" aria-label="Menu">&#9776;</button>
  </div>
  <div class="nav-mobile" id="nm">
    <a href="admin-dashboard.php" class="nav-link active">Dashboard</a>
    <a href="manage-trips.php"    class="nav-link">Manage Trips</a>
    <a href="add-trip.php"        class="nav-link">Add Trip</a>
    <div class="nav-mobile-footer"><a href="logout.php" class="btn btn-sm btn-outline-dark">Logout</a></div>
  </div>
</header>

<!-- ── CONTENT ── -->
<div class="page-content">
  <div class="page-header">
    <div>
      <h1 class="page-title">Dashboard</h1>
      <p class="page-subtitle">Overview of Hajj transportation operations</p>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon-wrap gold">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 5H3C1.89 5 1 5.89 1 7v10c0 1.11.89 2 2 2h1a2 2 0 004 0h6a2 2 0 004 0h1c1.11 0 2-.89 2-2v-5l-3-5z"/>
        </svg>
      </div>
      <div class="stat-label">Total Trips</div>
      <div class="stat-value"><?= $totalTrips ?></div>
      <div class="stat-sub">All schedules</div>
    </div>
    <div class="stat-card green-top">
      <div class="stat-icon-wrap green">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <div class="stat-label">Active Trips</div>
      <div class="stat-value"><?= $activeTrips ?></div>
      <div class="stat-sub">Currently running</div>
    </div>
    <div class="stat-card red-top">
      <div class="stat-icon-wrap red">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
      </div>
      <div class="stat-label">Cancelled</div>
      <div class="stat-value"><?= $cancelledTrips ?></div>
      <div class="stat-sub">This period</div>
    </div>
    <div class="stat-card blue-top">
      <div class="stat-icon-wrap blue">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
        </svg>
      </div>
      <div class="stat-label">Total Seats</div>
      <div class="stat-value"><?= $totalSeats ?></div>
      <div class="stat-sub">Available capacity</div>
    </div>
  </div>

  <div class="dash-grid">
    <div class="card">
      <div class="card-header">
        <h3>Recent Trips</h3>
        <a href="manage-trips.php" class="btn btn-sm btn-outline">View All</a>
      </div>
      <div class="card-body">
        <?php foreach ($recentTrips as $t): ?>
          <div class="trip-mini">
            <div class="trip-mini-info">
              <div class="trip-mini-route">
                <?= htmlspecialchars($t['Origin']) ?>
                <span class="route-arrow"> → </span>
                <?= htmlspecialchars($t['Destination']) ?>
              </div>
              <div class="trip-mini-meta">
                <?= htmlspecialchars($t['Bus_Number']) ?> ·
                <?= fmtDate($t['DepartureDate']) ?> ·
                <?= fmtTime($t['DepartureTime']) ?>
              </div>
            </div>
            <?php
              $statusClass = strtolower($t['Status']);
              if ($statusClass === 'scheduled' || $statusClass === 'confirmed') $statusClass = 'active';
              elseif ($statusClass === 'completed') $statusClass = 'info';
              elseif ($statusClass === 'cancelled') $statusClass = 'cancelled';
            ?>
            <span class="badge badge-<?= $statusClass ?>"><?= htmlspecialchars($t['Status']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3>Route Summary</h3></div>
      <div class="card-body">
        <?php foreach ($routes as $r):
          $pct = round(($r['cnt'] / $maxCnt) * 100);
        ?>
          <div class="trip-mini">
            <div class="trip-mini-info">
              <div class="trip-mini-route">
                <?= htmlspecialchars($r['Origin']) ?> → <?= htmlspecialchars($r['Destination']) ?>
              </div>
              <div class="route-bar">
                <div class="route-bar-fill" style="width:<?= $pct ?>%"></div>
              </div>
            </div>
            <span style="font-size:1.25rem;font-weight:800;color:var(--fg)"><?= $r['cnt'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- ── SHARED FOOTER ── -->
<footer class="site-footer">
  <p>© 2026 SAII. All rights reserved.</p>
</footer>
</body>
</html>
<?php mysqli_close($conn); ?>

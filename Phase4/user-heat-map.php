<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

include "db_connection.php";

if (!isset($_SESSION['UserID'])) {
    header("Location: login.php");
    exit();
}

$userName = $_SESSION['User_Name'] ?? "User";

$sql = "SELECT location, crowdDensity FROM heatmap";
$result = $conn->query($sql);

$sites = [];

while ($row = $result->fetch_assoc()) {
    $location = $row['location'];
    $pct = round($row['crowdDensity']);

    if ($pct >= 85) {
        $barClass = "bar-red";
        $dotClass = "map-dot-red";
    } elseif ($pct >= 65) {
        $barClass = "bar-orange";
        $dotClass = "map-dot-orange";
    } elseif ($pct >= 40) {
        $barClass = "bar-yellow";
        $dotClass = "map-dot-yellow";
    } else {
        $barClass = "bar-green";
        $dotClass = "map-dot-green";
    }

    $sites[] = [
        "name" => $location,
        "pct" => $pct,
        "cls" => $barClass,
        "dot" => $dotClass
    ];
}

$conn->close();

function siteKey($name) {
    if ($name == "Masjid Al-Haram") return "makkah";
    return strtolower(str_replace(" ", "-", $name));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Heat Map — SAII Hajj Transport</title>
  <link rel="stylesheet" href="styles.css"/>
</head>

<body class="page-wrapper">

<header class="navbar">
  <div class="navbar-inner">
    <a href="user-dashboard.html" class="nav-logo">
      <img src="image/saii.png" alt="SAII Logo" class="logo-img"/>
    </a>

    <nav class="nav-links">
      <a href="user-dashboard.php" class="nav-link">Dashboard</a>
      <a href="view-trips.php" class="nav-link">View Trips</a>
      <a href="add-booking.php" class="nav-link">Book a Trip</a>
      <a href="my-bookings.php" class="nav-link">My Bookings</a>
      <a href="user-heat-map.php" class="nav-link active">Heat Map</a>
    </nav>

    <div class="nav-right">
      <span class="role-chip user">&#9679; pilgrim</span>
      <span style="color:rgba(255,255,255,.65);font-size:.85rem;">
     <?= htmlspecialchars($_SESSION['User_Name']) ?>
      </span>
      <a href="index.php" class="btn btn-sm btn-outline-dark">Logout</a>
    </div>
  </div>
</header>

<div class="page-content" id="heatmap">

  <div class="page-header" style="margin-bottom:2rem;">
    <div>
      <h1 class="heatmap-title">Live Congestion Heat Map</h1>
      <p class="heatmap-subtitle">Visualize real-time congestion at key Hajj locations</p>
    </div>
  </div>

  <div class="heatmap-wrap">

    <div class="heatmap-card" style="padding:1.5rem;">
      <div class="map-canvas" style="min-height:480px;">

        <svg viewBox="0 0 760 480" preserveAspectRatio="none">
          <line class="map-line" x1="200" y1="240" x2="380" y2="120"/>
          <line class="map-line" x1="380" y1="120" x2="560" y2="200"/>
          <line class="map-line" x1="560" y1="200" x2="560" y2="340"/>
          <line class="map-line" x1="560" y1="340" x2="380" y2="360"/>
          <line class="map-line" x1="380" y1="360" x2="200" y2="240"/>
          <line class="map-line" x1="380" y1="120" x2="380" y2="360"/>
          <line class="map-line" x1="200" y1="240" x2="560" y2="200"/>
        </svg>

        <?php
        $positions = [
            "Masjid Al-Haram" => "left:26%;top:50%;",
            "Arafat" => "left:50%;top:25%;",
            "Mina" => "left:74%;top:42%;",
            "Muzdalifah" => "left:74%;top:71%;",
            "Jamarat" => "left:50%;top:75%;",
            "Aziziyah" => "left:14%;top:30%;"
        ];

        foreach ($sites as $s):
            $key = siteKey($s['name']);
            $style = $positions[$s['name']] ?? "left:50%;top:50%;";
            $displayName = ($s['name'] == "Masjid Al-Haram") ? "Makkah" : $s['name'];
        ?>
          <div class="map-dot-wrapper"
               data-site="<?= $key ?>"
               style="<?= $style ?>"
               onclick="setActive(this,'<?= $key ?>')">
            <div class="map-dot <?= $s['dot'] ?>"></div>
            <div class="map-badge"><?= htmlspecialchars($displayName) ?></div>
          </div>
        <?php endforeach; ?>

      </div>
    </div>

    <div class="heatmap-side-card">

      <div class="legend-card" style="margin-bottom:1.25rem;">
        <h3 class="legend-title">Congestion Legend</h3>
        <div class="legend-list">
          <div class="legend-item"><div class="legend-dot" style="background:#e2574c"></div> Critical — 85% and above</div>
          <div class="legend-item"><div class="legend-dot" style="background:#eb7d32"></div> High — 65% to 84%</div>
          <div class="legend-item"><div class="legend-dot" style="background:#e0b43a"></div> Moderate — 40% to 64%</div>
          <div class="legend-item"><div class="legend-dot" style="background:#59c36a"></div> Low — less than 40%</div>
        </div>
      </div>

      <div class="legend-card">
        <h3 class="legend-title" style="margin-bottom:1.1rem;">Crowd Levels</h3>

        <div class="heat-sidebar" id="heat-sidebar">
          <?php foreach ($sites as $s):
              $key = siteKey($s['name']);
              $displayName = ($s['name'] == "Masjid Al-Haram") ? "Makkah" : $s['name'];
          ?>
            <div class="heat-row" id="row-<?= $key ?>">
              <div class="heat-row-top">
                <span class="heat-row-name"><?= htmlspecialchars($displayName) ?></span>
                <span class="heat-row-value"><?= $s['pct'] ?>%</span>
              </div>
              <div class="progress-bar-wrap">
                <div class="progress-bar <?= $s['cls'] ?>" style="width:<?= $s['pct'] ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <a href="add-booking.php" class="btn-book-trip">Book a Trip Now →</a>
      </div>

    </div>
  </div>
</div>

<footer class="site-footer">
  <p>© 2026 SAII. All rights reserved.</p>
</footer>

<script>
function setActive(el, site) {
  document.querySelectorAll('.map-dot-wrapper').forEach(function(d) {
    d.classList.remove('is-active');
  });

  document.querySelectorAll('.heat-row').forEach(function(r) {
    r.classList.remove('is-active');
  });

  el.classList.add('is-active');

  var row = document.getElementById('row-' + site);
  if (row) {
    row.classList.add('is-active');
    row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
}
</script>

</body>
</html>
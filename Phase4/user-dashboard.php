<?php
session_start();
include('db_connection.php');

if (!isset($_SESSION['UserID'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['UserID'];

/* ───── جلب اسم المستخدم ───── */
$stmt = $conn->prepare("SELECT User_Name FROM user WHERE UserID = ?");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $_SESSION['User_Name'] = $row['User_Name'];
} else {
    $_SESSION['User_Name'] = "User";
}

/* ───── جلب PilgrimID ───── */
$pilgrimID = null;

$stmt = $conn->prepare("
    SELECT PilgrimID 
    FROM pilgrim 
    WHERE UserID = ?
    LIMIT 1
");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $pilgrimID = $row['PilgrimID'];
}

/* ───── إذا ما عنده ملف حاج ───── */
if (!$pilgrimID) {
    header("Location: login.php");
    exit();
}

/* ───── الرحلات المتاحة ───── */
$trips = [];

$tripsResult = $conn->query("
    SELECT 
        t.TripID,
        t.Origin,
        t.Destination,
        t.DepartureDate,
        t.DepartureTime,
        t.TotalSeats,
        t.AvailableSeats,
        t.Status,
        b.Bus_Number,

        (t.TotalSeats - t.AvailableSeats) AS BookedSeats,

        CASE 
            WHEN t.TotalSeats > 0 
            THEN ROUND(((t.TotalSeats - t.AvailableSeats) / t.TotalSeats) * 100)
            ELSE 0
        END AS FillPercent

    FROM trip t
    JOIN bus b ON t.BusID = b.BusID

    WHERE 
        t.Status = 'Confirmed'
        AND t.DepartureDate >= CURDATE()
        AND t.TotalSeats > 0

    ORDER BY 
        BookedSeats DESC,
        t.DepartureDate ASC,
        t.DepartureTime ASC

    LIMIT 3
");

while ($row = $tripsResult->fetch_assoc()) {
    $trips[] = $row;
}

/* ───── حجوزات المستخدم ───── */
$myBookings = [];

$stmt = $conn->prepare("
    SELECT t.Origin, t.Destination, t.DepartureDate, t.DepartureTime, b.Bus_Number
    FROM booking bk
    JOIN trip t ON bk.TripID = t.TripID
    JOIN bus b ON t.BusID = b.BusID
    WHERE bk.PilgrimID = ?
      AND bk.BookingStatus = 'Confirmed'
      AND t.Status = 'Confirmed'
    ORDER BY t.DepartureDate ASC, t.DepartureTime ASC
    
");

$stmt->bind_param("i", $pilgrimID);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $myBookings[] = $row;
}

/* ───── Helpers ───── */
function fmtDate($d) {
    if (!$d) return '';
    $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $parts = explode('-', $d);
    return $months[(int)$parts[1] - 1] . ' ' . (int)$parts[2] . ', ' . $parts[0];
}

function fmtTime($t) {
    if (!$t) return '';
    $parts = explode(':', $t);
    $h = (int)$parts[0];
    return (($h % 12) ?: 12) . ':' . $parts[1] . ' ' . ($h >= 12 ? 'PM' : 'AM');
}

function getTripImage($destination) {
    $map = [
        'Mina' => 'image/Mina.png',
        'Muzdalifah' => 'image/Muzdalifah.png',
        'Arafat' => 'image/Arafat.jpg',
        'Masjid Al-Haram' => 'image/Makkah.jpg',
        'Aziziyah' => 'image/Aziziyah.jpg',
        'Jamarat' => 'image/jamarat.webp',
    ];

    foreach ($map as $key => $img) {
        if (stripos($destination, $key) !== false) return $img;
    }

    return 'image/default.jpg';
}

function getSeatBadge($total, $available) {
    $booked = $total - $available;
    $pct = $total > 0 ? round(($booked / $total) * 100) : 0;

    if ($available <= 0) return ['class' => 'full', 'text' => 'Full'];
    if ($pct >= 75) return ['class' => 'soon', 'text' => 'Filling Fast'];

    return ['class' => 'available', 'text' => $available . ' seats left'];
}

function getFillClass($total, $available) {
    $booked = $total - $available;
    $pct = $total > 0 ? round(($booked / $total) * 100) : 0;

    if ($pct >= 90) return 'fill-red';
    if ($pct >= 65) return 'fill-yellow';
    return 'fill-green';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>My Dashboard — SAII Hajj Transport</title>
  <link rel="stylesheet" href="styles.css"/>
</head>
<body class="page-wrapper">

<header class="navbar">
  <div class="navbar-inner">
    <a href="user-dashboard.php" class="nav-logo">
      <img src="image/saii.png" alt="SAII Logo" class="logo-img"/>
    </a>
    <nav class="nav-links">
      <a href="user-dashboard.php"  class="nav-link active">Dashboard</a>
      <a href="view-trips.php"      class="nav-link">View Trips</a>
      <a href="add-booking.php"     class="nav-link">Book a Trip</a>
      <a href="my_bookings.php"     class="nav-link">My Bookings</a>
      <a href="user-heat-map.php"   class="nav-link">Heat Map</a>
    </nav>
    <div class="nav-right">
      <span class="role-chip user">&#9679; pilgrim</span>
<span style="color:rgba(255,255,255,.65);font-size:.85rem;">
  <?= htmlspecialchars($_SESSION['User_Name']) ?>
</span>
      <a href="logout.php" class="btn btn-sm btn-outline-dark">Logout</a>
    </div>
    <button class="nav-toggle" onclick="document.getElementById('nm').classList.toggle('open')" aria-label="Menu">&#9776;</button>
  </div>
  <div class="nav-mobile" id="nm">
    <a href="user-dashboard.php"  class="nav-link active">Dashboard</a>
    <a href="view-trips.php"      class="nav-link">View Trips</a>
    <a href="add-booking.php"     class="nav-link">Book a Trip</a>
    <a href="my_bookings.php"     class="nav-link">My Bookings</a>
    <a href="user-heat-map.php"   class="nav-link">Heat Map</a>
    <div class="nav-mobile-footer">
      <a href="logout.php" class="btn btn-sm btn-outline-dark">Logout</a>
    </div>
  </div>
</header>

<div class="page-content">

  <div class="user-hero">
    <div class="user-hero-inner">

      <!-- Notification Bell -->
      <a href="notifications.php"
         style="position:absolute;margin-bottom:0.5rem;top:0.1rem;right:1rem;font-size:1.5rem;color:#333;text-decoration:none;"
         title="View Notifications">🔔</a>

      <div class="user-hero-text">
        <h2>Assalamu Alaikum, <span><?= htmlspecialchars($_SESSION['User_Name']) ?></span> 👋</h2>
        <p>Manage your Hajj journey from one place — book trips, track congestion, and stay on schedule.</p>
        <div class="user-hero-actions" style="margin-top:1.1rem;">
          <a href="add-booking.php"  class="btn btn-accent">+ Book a Trip</a>
          <a href="view-trips.php"   class="btn btn-outline-dark">Browse Trips →</a>
        </div>
      </div>

      <div style="display:flex;gap:.65rem;flex-wrap:wrap;">
        <div class="user-hero-stat">
          <strong><?= date('j M Y') ?></strong> Today
        </div>
      </div>

    </div>
  </div>

  <!-- ── Explore Trips ── -->
  <div class="section-header">
    <h2>Explore Available Trips</h2>
    <a href="view-trips.php">View all trips →</a>
  </div>

  <div class="trips-grid">
    <?php if (empty($trips)): ?>
      <p style="color:var(--fg-muted)">No available trips at the moment.</p>
    <?php else: ?>
      <?php foreach ($trips as $t):
        $booked    = $t['TotalSeats'] - $t['AvailableSeats'];
        $pct       = $t['TotalSeats'] > 0 ? round(($booked / $t['TotalSeats']) * 100) : 0;
        $badge     = getSeatBadge($t['TotalSeats'], $t['AvailableSeats']);
        $fillClass = getFillClass($t['TotalSeats'], $t['AvailableSeats']);
        $img       = getTripImage($t['Destination']);
        $tripID    = htmlspecialchars($t['TripID']);
      ?>
      <div class="trip-photo-card"
           onclick="showToast('Trip TRP-<?= $tripID ?> – tap Book a Trip to reserve your seat','info')">

        <div class="trip-photo-wrap">
          <div class="trip-photo-gradient">
            <img src="<?= htmlspecialchars($img) ?>" class="trip-img"/>
          </div>
          <span class="trip-photo-badge <?= $badge['class'] ?>">
            <?= htmlspecialchars($badge['text']) ?>
          </span>
        </div>

        <div class="trip-photo-body">
          <div class="trip-photo-route">
            <?= htmlspecialchars($t['Origin']) ?>
            <span class="arrow"> → </span>
            <?= htmlspecialchars($t['Destination']) ?>
          </div>
          <div class="trip-photo-meta">
            <span>📅 <?= fmtDate($t['DepartureDate']) ?></span>
            <span>🕐 <?= fmtTime($t['DepartureTime']) ?></span>
            <span>🚌 <?= htmlspecialchars($t['Bus_Number']) ?></span>
          </div>
          <div class="trip-photo-footer">
            <div class="trip-seats-bar-wrap">
              <div class="trip-seats-label"><?= $booked ?> / <?= $t['TotalSeats'] ?> seats booked</div>
              <div class="trip-seats-bar">
                <div class="trip-seats-fill <?= $fillClass ?>" style="width:<?= $pct ?>%"></div>
              </div>
            </div>
            <a href="add-booking.php" class="btn btn-sm btn-accent"
               onclick="event.stopPropagation()">Book</a>
          </div>
        </div>

      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="user-lower-grid">

    <!-- My Upcoming Trips -->
    <div class="card">
      <div class="card-header">
        <h3>My Upcoming Trips</h3>
        <a href="my_bookings.php" class="btn btn-sm btn-outline">View All</a>
      </div>
      <div class="card-body">
        <?php if (empty($myBookings)): ?>
          <p style="color:var(--fg-muted)">No upcoming bookings.</p>
        <?php else: ?>
          <?php foreach ($myBookings as $b):
            $day   = date('j',   strtotime($b['DepartureDate']));
            $month = date('M',   strtotime($b['DepartureDate']));
          ?>
          <div class="upcoming-item">
            <div class="upcoming-date-box">
              <span class="day"><?= $day ?></span>
              <span class="month"><?= $month ?></span>
            </div>
            <div class="upcoming-info">
              <div class="upcoming-route">
                <?= htmlspecialchars($b['Origin']) ?> → <?= htmlspecialchars($b['Destination']) ?>
              </div>
              <div class="upcoming-meta">
                <?= fmtTime($b['DepartureTime']) ?> · <?= htmlspecialchars($b['Bus_Number']) ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick Actions -->
    <div style="display:flex;flex-direction:column;gap:1.1rem;">
      <div class="card">
        <div class="card-header"><h3>Quick Actions</h3></div>
        <div class="card-body">
          <div class="quick-actions-grid">
            <a href="add-booking.php" class="quick-action-btn">
              <div class="quick-action-icon" style="background:hsla(37,45%,61%,.15);">🎫</div>
              Book a Trip
            </a>
            <a href="view-trips.php" class="quick-action-btn">
              <div class="quick-action-icon" style="background:hsla(217,91%,60%,.1);">🚌</div>
              View Trips
            </a>
            <a href="my_bookings.php" class="quick-action-btn">
              <div class="quick-action-icon" style="background:hsla(142,71%,45%,.12);">📋</div>
              My Bookings
            </a>
            <a href="user-heat-map.php" class="quick-action-btn">
              <div class="quick-action-icon" style="background:hsla(0,72%,51%,.08);">🗺️</div>
              Live Heat Map
            </a>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<footer class="site-footer">
  <p>© 2026 SAII. All rights reserved.</p>
</footer>

<div class="toast" id="toast"></div>

<script>
var _tt;
function showToast(msg, type) {
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast ' + (type||'info') + ' show';
  clearTimeout(_tt);
  _tt = setTimeout(function(){ t.classList.remove('show'); }, 3200);
}
</script>
</body>
</html>
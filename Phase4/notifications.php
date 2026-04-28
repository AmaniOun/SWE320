<?php
session_start();
include('db_connection.php');

/* ───── Login check ───── */
if (!isset($_SESSION['UserID'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['UserID'];

/* ───── Get User Name ───── */
$stmt = $conn->prepare("SELECT User_Name FROM user WHERE UserID = ?");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

$userName = "User";
if ($row = $result->fetch_assoc()) {
    $userName = $row['User_Name'];
}

/* ───── Get PilgrimID ───── */
$stmt = $conn->prepare("SELECT PilgrimID FROM pilgrim WHERE UserID = ?");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

$pilgrimID = null;
if ($row = $result->fetch_assoc()) {
    $pilgrimID = $row['PilgrimID'];
}

if (!$pilgrimID) {
    die("❌ No pilgrim profile found");
}

/* ───── Notifications (ONLY user-related) ───── */
$notifResult = $conn->prepare("
    SELECT DISTINCT
        n.notification_id,
        n.message,
        n.sent_at,
        t.TripID,
        t.Origin,
        t.Destination,
        t.DepartureDate,
        t.DepartureTime,
        b.Bus_Number
    FROM notification n
    JOIN trip t ON n.TripID = t.TripID
    JOIN bus b ON t.BusID = b.BusID
    JOIN booking bk ON bk.TripID = t.TripID
    WHERE bk.PilgrimID = ?
    AND t.DepartureDate >= CURDATE()
    ORDER BY n.sent_at DESC
");

$notifResult->bind_param("i", $pilgrimID);
$notifResult->execute();
$result = $notifResult->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

/* ───── Format helpers ───── */
function fmtTime24($t) {
    if (!$t) return '';
    return substr($t, 0, 5);
}

function fmtDate($d) {
    if (!$d) return '';
    return date('j M Y', strtotime($d));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Trip Notification — SAII Hajj Transport</title>
  <link rel="stylesheet" href="styles.css"/>
</head>
<body class="page-wrapper">

<header class="navbar">
  <div class="navbar-inner">
    <a href="user-dashboard.php" class="nav-logo">
      <img src="image/saii.png" alt="SAII Logo" class="logo-img"/>
    </a>
    <nav class="nav-links">
      <a href="user-dashboard.php"  class="nav-link">Dashboard</a>
      <a href="view-trips.php"      class="nav-link">View Trips</a>
      <a href="add-booking.php"     class="nav-link">Book a Trip</a>
      <a href="my_bookings.php"     class="nav-link">My Bookings</a>
      <a href="user-heat-map.php"   class="nav-link">Heat Map</a>
    </nav>
    <div class="nav-right">
      <span class="role-chip user">&#9679; pilgrim</span>
      <span style="color:rgba(255,255,255,.65);font-size:.85rem;"><?= htmlspecialchars($userName) ?></span>
      <a href="logout.php" class="btn btn-sm btn-outline-dark">Logout</a>
    </div>
    <button class="nav-toggle"
            onclick="document.getElementById('nm').classList.toggle('open')"
            aria-label="Menu">&#9776;</button>
  </div>
  <div class="nav-mobile" id="nm">
    <a href="user-dashboard.php"  class="nav-link">Dashboard</a>
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

  <a href="user-dashboard.php" class="back-arrow"
     style="text-decoration:none;color:#333;font-size:0.9rem;margin-bottom:0.5rem;float:right;">
    <b>← Back to Dashboard</b>
  </a>

  <h2>Trip Notifications</h2>
  <p>Please check your trips and review any potential delays.</p>
  <br>

  <div class="trips-grid">
    <?php if (empty($notifications)): ?>
      <p style="color:var(--fg-muted)">No notifications at the moment.</p>
    <?php else: ?>
      <?php foreach ($notifications as $n): ?>
        <div class="trip-photo-card">
          <div class="trip-photo-body">

            <div class="trip-photo-route">
              <?= htmlspecialchars($n['Origin']) ?>
              <span class="arrow"> → </span>
              <?= htmlspecialchars($n['Destination']) ?>
            </div>

            <div class="trip-photo-meta">
              <span>🚌 <?= htmlspecialchars($n['Bus_Number']) ?></span>
              <!-- ✅ وقت الرحلة بصيغة 24 ساعة -->
              <span>🕐 <?= fmtTime24($n['DepartureTime']) ?></span>
              <!-- ✅ تاريخ الرحلة بدل تاريخ الإشعار -->
              <span>📅 <?= fmtDate($n['DepartureDate']) ?></span>
            </div>

            <div class="trip-photo-footer">
              <span style="color:#d9534f;">
                ⚠ <?= htmlspecialchars($n['message']) ?>
              </span>

            </div>

          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
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
  t.className = 'toast ' + (type || 'info') + ' show';
  clearTimeout(_tt);
  _tt = setTimeout(function () { t.classList.remove('show'); }, 3200);
}
</script>
</body>
</html>
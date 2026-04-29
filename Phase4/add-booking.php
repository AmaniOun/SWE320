<?php
session_start();

// ── Auth check (unified session key) ──────────────────────────
if (!isset($_SESSION['UserID'])) {
    header('Location: signin.php');
    exit;
}

include('db_connection.php');

$userID = $_SESSION['UserID'];

// ── Get user info ──────────────────────────────────────────────
$stmt = $conn->prepare("SELECT User_Name, Email FROM user WHERE UserID = ?");
$stmt->bind_param("i", $userID);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$userName  = $userRow['User_Name'] ?? 'User';
$userEmail = $userRow['Email']     ?? '';

// ── Get PilgrimID ──────────────────────────────────────────────
$stmt = $conn->prepare("SELECT PilgrimID FROM pilgrim WHERE UserID = ? LIMIT 1");
$stmt->bind_param("i", $userID);
$stmt->execute();
$pilgrimRow = $stmt->get_result()->fetch_assoc();

if (!$pilgrimRow) {
    header('Location: login.php');
    exit;
}

$pilgrimID = $pilgrimRow['PilgrimID'];

$successData = null;
$errorMsg    = '';

// ── Handle POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tripID = (int)($_POST['trip_id'] ?? 0);

    if (!$tripID) {
        $errorMsg = 'Please select a trip.';
    } else {
        // ── 1. Check for duplicate booking ────────────────────
        $dup = $conn->prepare("
            SELECT BookingID FROM booking
            WHERE PilgrimID = ? AND TripID = ? AND BookingStatus = 'Confirmed'
            LIMIT 1
        ");
        $dup->bind_param("ii", $pilgrimID, $tripID);
        $dup->execute();

        if ($dup->get_result()->num_rows > 0) {
            $errorMsg = 'You already have a confirmed booking for this trip.';
        } else {
            // ── 2. Fetch trip using prepared statement ─────────
            $stmt = $conn->prepare("
                SELECT t.TripID, t.Origin, t.Destination, t.DepartureDate,
                       t.DepartureTime, t.AvailableSeats, t.TotalSeats, b.Bus_Number
                FROM trip t
                JOIN bus b ON b.BusID = t.BusID
                WHERE t.TripID = ? AND t.Status = 'Confirmed'
                LIMIT 1
            ");
            $stmt->bind_param("i", $tripID);
            $stmt->execute();
            $trip = $stmt->get_result()->fetch_assoc();

            if (!$trip) {
                $errorMsg = 'Trip not found or not available.';
            } elseif ($trip['AvailableSeats'] <= 0) {
                $errorMsg = 'No available seats for this trip.';
            } else {
                $seatNumber = $trip['TotalSeats'] - $trip['AvailableSeats'] + 1;

                // ── 3. Insert booking (prepared) ───────────────
                $ins = $conn->prepare("
                    INSERT INTO booking (BookingDate, SeatNumber, BookingStatus, PilgrimID, TripID)
                    VALUES (CURDATE(), ?, 'Confirmed', ?, ?)
                ");
                $ins->bind_param("iii", $seatNumber, $pilgrimID, $tripID);
                $ins->execute();
                $bookingID = $conn->insert_id;

                // ── 4. Decrease available seats (prepared) ─────
                $upd = $conn->prepare("
                    UPDATE trip SET AvailableSeats = AvailableSeats - 1 WHERE TripID = ?
                ");
                $upd->bind_param("i", $tripID);
                $upd->execute();

                // ── 5. Generate QR (prepared) ──────────────────
                $qrValue = 'QR-SAII-BK' . str_pad($bookingID, 3, '0', STR_PAD_LEFT);
                $expiry  = date('Y-m-d H:i:s', strtotime(
                    $trip['DepartureDate'] . ' ' . $trip['DepartureTime'] . ' +3 hours'
                ));

                $qr = $conn->prepare("
                    INSERT INTO qrcode (BookingID, QR_Value, ExpiryTime, QR_Status)
                    VALUES (?, ?, ?, 'Active')
                ");
                $qr->bind_param("iss", $bookingID, $qrValue, $expiry);
                $qr->execute();

                $successData = [
                    'booking_id' => $bookingID,
                    'qr_value'   => $qrValue,
                    'from'       => $trip['Origin'],
                    'to'         => $trip['Destination'],
                    'date'       => date('M d, Y', strtotime($trip['DepartureDate'])),
                    'time'       => substr($trip['DepartureTime'], 0, 5), // 24-hour HH:MM
                    'bus'        => $trip['Bus_Number'],
                ];
            }
        }
    }
}

// ── Load available trips (prepared) ───────────────────────────
$stmt = $conn->prepare("
    SELECT t.TripID, t.Origin, t.Destination, t.DepartureDate,
           t.DepartureTime, t.AvailableSeats, t.TotalSeats, b.Bus_Number
    FROM trip t
    JOIN bus b ON b.BusID = t.BusID
    WHERE t.Status = 'Confirmed'
      AND t.AvailableSeats > 0
      AND t.DepartureDate >= CURDATE()
    ORDER BY t.DepartureDate, t.DepartureTime
");
$stmt->execute();
$tripsResult = $stmt->get_result();

$trips = [];
while ($row = $tripsResult->fetch_assoc()) {
    $trips[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Book a Trip — SAII Hajj Transport</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <link rel="stylesheet" href="styles.css"/>
  <style>
    :root {
      --primary:#1f3566;
      --accent:#e2a94b;
      --success:#2e7d32;
      --danger:#d92d20;
      --warning:#f3a000;
      --bg:#f7f8fc;
    }
    .container { width:92%; max-width:700px; margin:30px auto; }
    h2 { font-size:22px; font-weight:700; color:#1f2937; margin-bottom:4px; }
    .subtitle { font-size:13px; color:#667085; margin-bottom:24px; }

    .form-card {
      background:#fff;
      border:1px solid #eceef3;
      border-radius:12px;
      padding:24px;
      margin-bottom:16px;
    }
    .form-card-title {
      font-size:14px; font-weight:700; color:#1f3566;
      display:flex; align-items:center; gap:8px; margin-bottom:20px;
    }
    .form-card-title i { color:var(--accent); }

    .form-group { margin-bottom:16px; }
    .form-group label { display:block; font-size:13px; font-weight:600; color:#344054; margin-bottom:6px; }
    .form-group input,
    .form-group select {
      width:100%; padding:10px 12px;
      border:1px solid #d0d5dd; border-radius:8px;
      font-size:14px; color:#1f2937; background:#fff;
      outline:none; transition:0.2s;
      font-family:inherit; appearance:none;
    }
    .form-group input:focus,
    .form-group select:focus {
      border-color:var(--accent);
      box-shadow:0 0 0 3px rgba(226,169,75,0.15);
    }
    .form-group input[readonly] { background:#f9fafb; color:#667085; cursor:not-allowed; }

    /* Trip preview */
    .trip-preview-card {
      background:#fff; border:1px solid #eceef3;
      border-radius:12px; padding:24px; margin-bottom:16px;
      border-left:5px solid var(--accent); display:none;
    }
    .trip-preview-card.visible { display:block; }
    .trip-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; }
    .trip-bus-name { font-size:15px; font-weight:700; color:#1f3566; display:flex; align-items:center; gap:6px; }
    .badge-active { background:#e8f5e9; color:var(--success); font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; }
    .trip-route-row { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
    .trip-loc { display:flex; align-items:center; gap:5px; font-size:14px; font-weight:600; color:#1f3566; }
    .trip-loc i { color:var(--accent); font-size:13px; }
    .route-arrow { color:#667085; font-size:16px; flex:1; text-align:center; }
    .trip-meta { display:flex; gap:16px; flex-wrap:wrap; }
    .trip-meta span { font-size:12px; color:#667085; display:flex; align-items:center; gap:5px; }
    .trip-meta i { color:var(--accent); }
    .seats-tag { font-size:12px; font-weight:600; padding:2px 10px; border-radius:20px; }
    .seats-tag.good { background:#e8f5e9; color:var(--success); }
    .seats-tag.low  { background:#fff3e0; color:var(--warning); }
    .seats-tag.crit { background:#ffebee; color:var(--danger);  }

    /* Confirm button */
    .btn-confirm {
      width:100%; padding:13px;
      background:var(--accent); color:#1f2937;
      border:none; border-radius:8px;
      font-size:15px; font-weight:700; cursor:pointer;
      display:flex; align-items:center; justify-content:center; gap:8px;
      transition:0.2s; font-family:inherit;
    }
    .btn-confirm:hover:not(:disabled) { background:#f4b860; }
    .btn-confirm:disabled { opacity:0.55; cursor:not-allowed; }

    /* Alerts */
    .alert { padding:10px 14px; border-radius:8px; font-size:13px; font-weight:500; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
    .alert-error   { background:#ffebee; color:var(--danger);  border:1px solid #f5c2c7; }
    .alert-warning { background:#fff3e0; color:var(--warning); border:1px solid #ffd180; }

    /* Modal */
    .modal { position:fixed; inset:0; background:rgba(0,0,0,0.6); display:none; align-items:center; justify-content:center; z-index:9999; }
    .modal.active { display:flex !important; }
    .modal-box { background:#fff; padding:25px; border-radius:16px; width:380px; text-align:center; }
    .btn-primary-modal {
      background:var(--primary); color:#fff;
      padding:12px; border:none; border-radius:8px;
      width:100%; font-weight:bold; cursor:pointer;
      margin-top:15px; font-family:inherit;
    }
  </style>
</head>
<body class="page-wrapper">

<header class="navbar">
  <div class="navbar-inner">
    <a href="user-dashboard.php" class="nav-logo">
      <img src="image/saii.png" alt="SAII Logo" class="logo-img"/>
    </a>
    <nav class="nav-links">
      <a href="user-dashboard.php" class="nav-link">Dashboard</a>
      <a href="view-trips.php"     class="nav-link">View Trips</a>
      <a href="add-booking.php"    class="nav-link active">Book a Trip</a>
      <a href="my_bookings.php"    class="nav-link">My Bookings</a>
      <a href="user-heat-map.php"  class="nav-link">Heat Map</a>
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
    <a href="user-dashboard.php" class="nav-link">Dashboard</a>
    <a href="view-trips.php"     class="nav-link">View Trips</a>
    <a href="add-booking.php"    class="nav-link active">Book a Trip</a>
    <a href="my_bookings.php"    class="nav-link">My Bookings</a>
    <a href="user-heat-map.php"  class="nav-link">Heat Map</a>
    <div class="nav-mobile-footer">
      <a href="logout.php" class="btn btn-sm btn-outline-dark">Logout</a>
    </div>
  </div>
</header>

<main class="container">
  <h2>Add Booking</h2>
  <p class="subtitle">Reserve a seat on an available trip</p>

  <?php if ($errorMsg): ?>
    <div class="alert alert-error">
      <i class="fa-solid fa-circle-xmark"></i>
      <?= htmlspecialchars($errorMsg) ?>
    </div>
  <?php endif; ?>

  <?php if (empty($trips)): ?>
    <div class="alert alert-warning">
      <i class="fa-solid fa-triangle-exclamation"></i>
      No available trips at the moment.
    </div>
  <?php endif; ?>

  <form method="POST" action="add-booking.php" id="bookingForm">

    <div class="form-card">
      <div class="form-card-title">
        <i class="fa-regular fa-calendar-plus"></i>
        Booking Details
      </div>

      <div class="form-group">
        <label>Full Name</label>
        <input type="text" readonly value="<?= htmlspecialchars($userName) ?>"/>
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="email" readonly value="<?= htmlspecialchars($userEmail) ?>"/>
      </div>

      <div class="form-group">
        <label>Select Trip</label>
        <select id="tripSelect" name="trip_id" onchange="onTripChange()">
          <option value="">Choose a trip...</option>
          <?php foreach ($trips as $t): ?>
            <option value="<?= (int)$t['TripID'] ?>"
                    data-bus="<?= htmlspecialchars($t['Bus_Number']) ?>"
                    data-from="<?= htmlspecialchars($t['Origin']) ?>"
                    data-to="<?= htmlspecialchars($t['Destination']) ?>"
                    data-date="<?= date('M d, Y', strtotime($t['DepartureDate'])) ?>"
                    data-time="<?= substr($t['DepartureTime'], 0, 5) ?>"
                    data-seats="<?= (int)$t['AvailableSeats'] ?>"
                    data-total="<?= (int)$t['TotalSeats'] ?>">
              <?= htmlspecialchars($t['Origin']) ?> → <?= htmlspecialchars($t['Destination']) ?>
              | <?= date('Y-m-d', strtotime($t['DepartureDate'])) ?>
              <?= substr($t['DepartureTime'], 0, 5) ?>
              (<?= (int)$t['AvailableSeats'] ?> seats)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Trip Preview -->
    <div id="tripPreview" class="trip-preview-card">
      <div class="trip-header">
        <div class="trip-bus-name">
          <i class="fa-solid fa-bus"></i>
          <span id="pvBus"></span>
        </div>
        <span class="badge-active">active</span>
      </div>
      <div class="trip-route-row">
        <div class="trip-loc"><i class="fa-solid fa-location-dot"></i><span id="pvFrom"></span></div>
        <div class="route-arrow"><i class="fa-solid fa-arrow-right"></i></div>
        <div class="trip-loc"><i class="fa-solid fa-location-dot"></i><span id="pvTo"></span></div>
      </div>
      <div class="trip-meta">
        <span><i class="fa-regular fa-calendar"></i><span id="pvDate"></span></span>
        <span><i class="fa-regular fa-clock"></i><span id="pvTime"></span></span>
        <span id="pvSeatsBadge" class="seats-tag good">
          <i class="fa-solid fa-users"></i>&nbsp;<span id="pvSeats"></span>
        </span>
      </div>
    </div>

    <button type="submit" class="btn-confirm" id="confirmBtn" disabled>
      <i class="fa-solid fa-circle-check"></i>
      <span id="btnText">Confirm Booking</span>
    </button>

  </form>
</main>

<!-- SUCCESS MODAL -->
<div id="successModal" class="modal <?= $successData ? 'active' : '' ?>">
  <div class="modal-box" style="border-top:5px solid var(--success);">
    <i class="fa-solid fa-circle-check" style="font-size:50px;color:var(--success);"></i>
    <h3 style="margin-top:15px;">Booking Confirmed!</h3>
    <?php if ($successData): ?>
      <p style="font-size:14px;color:#666;margin-top:10px;">
        Seat reserved for <?= htmlspecialchars($successData['date']) ?>
        at <?= htmlspecialchars($successData['time']) ?>
      </p>
      <div style="margin:20px 0;border:2px dashed #ddd;padding:20px;display:inline-block;border-radius:8px;">
        <i class="fa-solid fa-qrcode" style="font-size:100px;color:#1f3566;"></i>
        <p style="font-weight:bold;font-size:13px;margin-top:8px;color:#667085;">
          <?= htmlspecialchars($successData['qr_value']) ?><br>
          <?= htmlspecialchars($successData['from']) ?> → <?= htmlspecialchars($successData['to']) ?><br>
          <?= htmlspecialchars($successData['bus']) ?>
        </p>
      </div>
    <?php endif; ?>
    <button class="btn-primary-modal" onclick="window.location.href='my_bookings.php'">
      <i class="fa-regular fa-bookmark"></i> View My Bookings
    </button>
    <button class="btn-primary-modal"
            style="background:#f2f4f7;color:#344054;margin-top:8px;"
            onclick="document.getElementById('successModal').classList.remove('active')">
      Book Another
    </button>
  </div>
</div>

<footer style="border-top:1px solid #eceef3;padding:1.25rem 1rem;text-align:center;background:var(--bg);margin-top:40px;">
  <p style="color:#667085;font-size:13px;">© 2026 SAII. All rights reserved.</p>
</footer>

<script>
function onTripChange() {
  const sel     = document.getElementById('tripSelect');
  const opt     = sel.options[sel.selectedIndex];
  const preview = document.getElementById('tripPreview');
  const btn     = document.getElementById('confirmBtn');

  if (!sel.value) {
    preview.classList.remove('visible');
    btn.disabled = true;
    return;
  }

  const seats = parseInt(opt.dataset.seats);
  const total = parseInt(opt.dataset.total);

  document.getElementById('pvBus').textContent   = opt.dataset.bus;
  document.getElementById('pvFrom').textContent  = opt.dataset.from;
  document.getElementById('pvTo').textContent    = opt.dataset.to;
  document.getElementById('pvDate').textContent  = opt.dataset.date;
  document.getElementById('pvTime').textContent  = opt.dataset.time;
  document.getElementById('pvSeats').textContent = seats + ' / ' + total + ' seats';

  const badge = document.getElementById('pvSeatsBadge');
  badge.className = 'seats-tag';
  if      (seats <= 5)  badge.classList.add('crit');
  else if (seats <= 15) badge.classList.add('low');
  else                  badge.classList.add('good');

  preview.classList.add('visible');
  btn.disabled = (seats === 0);
}

document.getElementById('bookingForm').addEventListener('submit', function () {
  const btn = document.getElementById('confirmBtn');
  btn.disabled = true;
  document.getElementById('btnText').textContent = 'Booking...';
});
</script>
</body>
</html>
<?php
//session_start();
require_once 'db_connection.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $origin      = trim($_POST['Origin']        ?? '');
    $destination = trim($_POST['Destination']   ?? '');
    $date        = trim($_POST['DepartureDate'] ?? '');
    $time        = trim($_POST['DepartureTime'] ?? '');
    $totalSeats  = (int)($_POST['TotalSeats']   ?? 0);
    $busNum      = trim($_POST['Bus_Number']    ?? '');
    $pickup      = trim($_POST['Pickup_Location'] ?? '');

    if (!$origin)                    $errors['Origin']      = 'Please select a pickup location';
    if (!$destination)               $errors['Destination'] = 'Please select a drop-off location';
    if ($origin && $destination && $origin === $destination)
                                     $errors['Destination'] = 'Drop-off cannot be same as pickup';
    if (!$date)                      $errors['DepartureDate'] = 'Please select a date';
    if (!$time)                      $errors['DepartureTime'] = 'Please select a departure time';
    if ($totalSeats < 1 || $totalSeats > 200)
                                     $errors['TotalSeats']  = 'Seats must be between 1 and 200';
    if (!$busNum)                    $errors['Bus_Number']  = 'Please select a bus';

    $busId = null;
    if (!isset($errors['Bus_Number'])) {
        $escaped = mysqli_real_escape_string($conn, $busNum);
        $bRes = mysqli_query($conn, "SELECT BusID FROM bus WHERE Bus_Number='$escaped' LIMIT 1");
        $bRow = mysqli_fetch_assoc($bRes);
        if ($bRow) {
            $busId = $bRow['BusID'];
        } else {
            $errors['Bus_Number'] = 'Bus not found in database';
        }
    }

    $adminId = $_SESSION['AdminID'] ?? 1;

    if (empty($errors) && $busId) {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO trip
             (Origin, Destination, DepartureDate, DepartureTime, TotalSeats, AvailableSeats,
              Status, Pickup_Location, BusID, AdminID)
             VALUES (?, ?, ?, ?, ?, ?, 'Scheduled', ?, ?, ?)");

        mysqli_stmt_bind_param($stmt, 'ssssiisii',
            $origin, $destination, $date, $time,
            $totalSeats, $totalSeats,   
            $pickup, $busId, $adminId);

        if (mysqli_stmt_execute($stmt)) {
            $newId = mysqli_insert_id($conn);
            $_SESSION['toast'] = ['msg' => "Trip #$newId created successfully!", 'type' => 'success'];
            mysqli_stmt_close($stmt);
            header("Location: manage-trips.php");
            exit();
        } else {
            $errors['db'] = 'Database error: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

$buses = [];
$bRes = mysqli_query($conn, "SELECT BusID, Bus_Number, Capacity FROM bus WHERE Status='Active' ORDER BY Bus_Number");
while ($bRow = mysqli_fetch_assoc($bRes)) {
    $buses[] = $bRow;
}

$locations = [
    'Masjid Al-Haram', 'Mina', 'Arafat', 'Muzdalifah',
    'Makkah Hotel Zone', 'Madinah', 'Aziziyah', 'Jamarat'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Add Trip — SAII Hajj Transport</title>
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
      <a href="admin-dashboard.php" class="nav-link">Dashboard</a>
      <a href="manage-trips.php"    class="nav-link">Manage Trips</a>
      <a href="add-trip.php"        class="nav-link active">Add Trip</a>
    </nav>
    <div class="nav-right">
      <span class="role-chip admin">&#9679; Admin</span>
      <span style="color:rgba(255,255,255,.65);font-size:.85rem;">Admin User</span>
      <a href="logout.php" class="btn btn-sm btn-outline-dark">Logout</a>
    </div>
    <button class="nav-toggle" onclick="document.getElementById('nm').classList.toggle('open')" aria-label="Menu">&#9776;</button>
  </div>
  <div class="nav-mobile" id="nm">
    <a href="admin-dashboard.php" class="nav-link">Dashboard</a>
    <a href="manage-trips.php"    class="nav-link">Manage Trips</a>
    <a href="add-trip.php"        class="nav-link active">Add Trip</a>
    <div class="nav-mobile-footer"><a href="logout.php" class="btn btn-sm btn-outline-dark">Logout</a></div>
  </div>
</header>

<!-- ── CONTENT ── -->
<div class="page-content">
  <div class="page-header">
    <div>
      <h1 class="page-title">Add New Trip</h1>
      <p class="page-subtitle">Create a new transportation schedule</p>
    </div>
    <a href="manage-trips.php" class="btn btn-outline">&#8592; Back to Trips</a>
  </div>

  <?php if (isset($errors['db'])): ?>
    <div class="alert alert-error" style="margin-bottom:1rem;padding:.75rem 1rem;background:#fee;border:1px solid #fcc;border-radius:.5rem;color:#c00;">
      <?= htmlspecialchars($errors['db']) ?>
    </div>
  <?php endif; ?>

  <div class="form-card">
    <div class="form-card-title">
      <div class="form-card-icon">
        <svg viewBox="0 0 24 24"><path d="M17 5H3C1.89 5 1 5.89 1 7v10c0 1.11.89 2 2 2h1a2 2 0 004 0h6a2 2 0 004 0h1c1.11 0 2-.89 2-2v-5l-3-5zm-1 1.5l2.28 3.5H13V6.5h3zm-11 9a1 1 0 11-2 0 1 1 0 012 0zm9 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
      </div>
      Trip Details
    </div>

    <form method="POST" action="add-trip.php">
      <div class="form-grid">

        <!-- From -->
        <div class="form-field">
          <label class="form-label" for="Origin">From</label>
          <div class="select-wrap">
            <select class="form-select <?= isset($errors['Origin']) ? 'error' : '' ?>"
                    name="Origin" id="Origin">
              <option value="">Select pickup location</option>
              <?php foreach ($locations as $l): ?>
                <option value="<?= $l ?>" <?= (($_POST['Origin'] ?? '') === $l) ? 'selected' : '' ?>>
                  <?= $l ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if (isset($errors['Origin'])): ?>
            <span class="field-error show"><?= $errors['Origin'] ?></span>
          <?php endif; ?>
        </div>

        <!-- To -->
        <div class="form-field">
          <label class="form-label" for="Destination">To</label>
          <div class="select-wrap">
            <select class="form-select <?= isset($errors['Destination']) ? 'error' : '' ?>"
                    name="Destination" id="Destination">
              <option value="">Select drop-off location</option>
              <?php foreach ($locations as $l): ?>
                <option value="<?= $l ?>" <?= (($_POST['Destination'] ?? '') === $l) ? 'selected' : '' ?>>
                  <?= $l ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if (isset($errors['Destination'])): ?>
            <span class="field-error show"><?= $errors['Destination'] ?></span>
          <?php endif; ?>
        </div>

        <!-- Date -->
        <div class="form-field">
          <label class="form-label" for="DepartureDate">Date</label>
          <input class="form-input <?= isset($errors['DepartureDate']) ? 'error' : '' ?>"
                 type="date" name="DepartureDate" id="DepartureDate"
                 min="<?= date('Y-m-d') ?>"
                 value="<?= htmlspecialchars($_POST['DepartureDate'] ?? '') ?>"/>
          <?php if (isset($errors['DepartureDate'])): ?>
            <span class="field-error show"><?= $errors['DepartureDate'] ?></span>
          <?php endif; ?>
        </div>

        <!-- Time -->
        <div class="form-field">
          <label class="form-label" for="DepartureTime">Departure Time</label>
          <input class="form-input <?= isset($errors['DepartureTime']) ? 'error' : '' ?>"
                 type="time" name="DepartureTime" id="DepartureTime"
                 value="<?= htmlspecialchars($_POST['DepartureTime'] ?? '') ?>"/>
          <?php if (isset($errors['DepartureTime'])): ?>
            <span class="field-error show"><?= $errors['DepartureTime'] ?></span>
          <?php endif; ?>
        </div>

        <!-- Bus -->
        <div class="form-field">
          <label class="form-label" for="Bus_Number">Bus</label>
          <div class="select-wrap">
            <select class="form-select <?= isset($errors['Bus_Number']) ? 'error' : '' ?>"
                    name="Bus_Number" id="Bus_Number">
              <option value="">Select bus</option>
              <?php foreach ($buses as $b): ?>
                <option value="<?= htmlspecialchars($b['Bus_Number']) ?>"
                  <?= (($_POST['Bus_Number'] ?? '') === $b['Bus_Number']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($b['Bus_Number']) ?> (cap: <?= $b['Capacity'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if (isset($errors['Bus_Number'])): ?>
            <span class="field-error show"><?= $errors['Bus_Number'] ?></span>
          <?php endif; ?>
        </div>

        <!-- Seats -->
        <div class="form-field">
          <label class="form-label" for="TotalSeats">Total Seats</label>
          <input class="form-input <?= isset($errors['TotalSeats']) ? 'error' : '' ?>"
                 type="number" name="TotalSeats" id="TotalSeats"
                 min="1" max="200"
                 value="<?= htmlspecialchars($_POST['TotalSeats'] ?? '50') ?>"/>
          <?php if (isset($errors['TotalSeats'])): ?>
            <span class="field-error show"><?= $errors['TotalSeats'] ?></span>
          <?php endif; ?>
        </div>

        <!-- Pickup Location -->
        <div class="form-field full">
          <label class="form-label" for="Pickup_Location">Pickup Location <span style="opacity:.6;font-size:.8rem;">(optional)</span></label>
          <input class="form-input" type="text" name="Pickup_Location" id="Pickup_Location"
                 placeholder="e.g. King Fahd Gate"
                 value="<?= htmlspecialchars($_POST['Pickup_Location'] ?? '') ?>"/>
        </div>

      </div>

      <button type="submit" class="btn btn-accent btn-full" style="margin-top:1.25rem;">
        Create Trip
      </button>
    </form>
  </div>
</div>

<!-- ── SHARED FOOTER ── -->
<footer class="site-footer">
  <p>© 2026 SAII. All rights reserved.</p>
</footer>

<div class="toast" id="toast"></div>
</body>
</html>
<?php mysqli_close($conn); ?>

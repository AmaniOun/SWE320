<?php
session_start();

include('db_connection.php');

/* ───── Login check ───── */
if (!isset($_SESSION['UserID'])) {
    header("Location: signin.php");
    exit();
}

$userID = $_SESSION['UserID'];

if (!$pilgrimID && $userID) {
    $stmt = mysqli_prepare($conn, 'SELECT PilgrimID FROM pilgrim WHERE UserID = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $userID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $pilgrimID = (int)$row['PilgrimID'];
        $_SESSION['pilgrim_id'] = $pilgrimID;
    }
    mysqli_stmt_close($stmt);
}

$errorMsg = '';
$successData = null;
$selectedTripKey = '';
$fullName = $userName;
$email = $userEmail;

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatTripRow($row) {
    return [
        'id' => 'TRP-' . str_pad($row['TripID'], 3, '0', STR_PAD_LEFT),
        'bus' => $row['Bus_Number'],
        'from' => $row['Origin'],
        'to' => $row['Destination'],
        'date' => date('M d, Y', strtotime($row['DepartureDate'])),
        'date_value' => date('Y-m-d', strtotime($row['DepartureDate'])),
        'time' => date('h:i A', strtotime($row['DepartureTime'])),
        'seats' => $row['AvailableSeats'] . '/' . $row['TotalSeats'] . ' seats',
        'pickup' => $row['Pickup_Location'] ?? '',
        'duration' => '',
        'busType' => '',
        'note' => '',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedTripKey = $_POST['trip_id'] ?? '';
    $tripID = (int)$selectedTripKey;
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!$pilgrimID) {
        $errorMsg = 'Unable to find your pilgrim account.';
    } elseif ($fullName === '') {
        $errorMsg = 'Please enter your full name.';
    } elseif ($email === '') {
        $errorMsg = 'Please enter your email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = 'Please enter a valid email.';
    } elseif (!$tripID) {
        $errorMsg = 'Please select a trip.';
    } else {
        mysqli_begin_transaction($conn);

        try {
            $stmt = mysqli_prepare($conn,
                "SELECT t.TripID, t.Origin, t.Destination, t.DepartureDate, t.DepartureTime,
                        t.TotalSeats, t.AvailableSeats, t.Status, t.Pickup_Location, b.Bus_Number
                 FROM trip t
                 JOIN bus b ON b.BusID = t.BusID
                 WHERE t.TripID = ? AND t.Status = 'Confirmed'
                 LIMIT 1
                 FOR UPDATE"
            );
            mysqli_stmt_bind_param($stmt, 'i', $tripID);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $trip = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$trip) {
                throw new Exception('Trip not found or not available.');
            }

            if ((int)$trip['AvailableSeats'] <= 0) {
                throw new Exception('No available seats for this trip.');
            }

            $seatNumber = (int)$trip['TotalSeats'] - (int)$trip['AvailableSeats'] + 1;

            $stmt = mysqli_prepare($conn,
                "INSERT INTO booking (BookingDate, SeatNumber, BookingStatus, PilgrimID, TripID)
                 VALUES (CURDATE(), ?, 'Confirmed', ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, 'iii', $seatNumber, $pilgrimID, $tripID);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Could not create booking.');
            }
            mysqli_stmt_close($stmt);

            $bookingID = mysqli_insert_id($conn);

            $stmt = mysqli_prepare($conn,
                'UPDATE trip SET AvailableSeats = AvailableSeats - 1 WHERE TripID = ? AND AvailableSeats > 0'
            );
            mysqli_stmt_bind_param($stmt, 'i', $tripID);
            if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) {
                throw new Exception('Could not reserve a seat for this trip.');
            }
            mysqli_stmt_close($stmt);

            $qrValue = 'QR-SAII-BK' . str_pad($bookingID, 3, '0', STR_PAD_LEFT);
            $expiry = date('Y-m-d H:i:s', strtotime($trip['DepartureDate'] . ' ' . $trip['DepartureTime'] . ' +3 hours'));

            $stmt = mysqli_prepare($conn,
                "INSERT INTO qrcode (BookingID, QR_Value, ExpiryTime, QR_Status)
                 VALUES (?, ?, ?, 'Active')"
            );
            mysqli_stmt_bind_param($stmt, 'iss', $bookingID, $qrValue, $expiry);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Could not generate QR code.');
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);

            $successData = formatTripRow($trip);
            $successData['booking_id'] = $bookingID;
            $successData['qr_value'] = $qrValue;
        } catch (Exception $ex) {
            mysqli_rollback($conn);
            $errorMsg = $ex->getMessage();
        }
    }
}

$trips = [];
$stmt = mysqli_prepare($conn,
    "SELECT t.TripID, t.Origin, t.Destination, t.DepartureDate, t.DepartureTime,
            t.TotalSeats, t.AvailableSeats, t.Status, t.Pickup_Location, b.Bus_Number
     FROM trip t
     JOIN bus b ON b.BusID = t.BusID
     WHERE t.Status = 'Confirmed' AND t.AvailableSeats > 0
     ORDER BY t.DepartureDate, t.DepartureTime"
);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $trips[(string)$row['TripID']] = formatTripRow($row);
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <title>Book a Trip - SAI System</title>
  <link rel="stylesheet" href="styles.css"/>
</head>
<body class="page-wrapper">

  <!-- SHARED HEADER -->
  <header class="navbar">
    <div class="navbar-inner">
      <a href="user-dashboard.php" class="nav-logo">
        <img src="image/saii.png" alt="SAII Logo" class="logo-img"/>
      </a>
      <nav class="nav-links">
        <a href="user-dashboard.php" class="nav-link">Dashboard</a>
        <a href="view-trips.php" class="nav-link">View Trips</a>
        <a href="add-booking.php" class="nav-link active">Book a Trip</a>
        <a href="my_bookings.php" class="nav-link">My Bookings</a>
        <a href="user-heat-map.php" class="nav-link">Heat Map</a>
      </nav>
      <div class="nav-right">
        <span class="role-chip user">&#9679; User</span>
        <span style="color:rgba(255,255,255,.65);font-size:.85rem;"><?= e($userName) ?></span>
        <a href="logout.php" class="btn btn-sm btn-outline-dark">Logout</a>
      </div>
      <button class="nav-toggle" onclick="document.getElementById('nm').classList.toggle('open')" aria-label="Menu">&#9776;</button>
    </div>

    <div class="nav-mobile" id="nm">
      <a href="user-dashboard.php" class="nav-link">Dashboard</a>
      <a href="view-trips.php" class="nav-link">View Trips</a>
      <a href="add-booking.php" class="nav-link active">Book a Trip</a>
      <a href="my_bookings.php" class="nav-link">My Bookings</a>
      <a href="user-heat-map.php" class="nav-link">Heat Map</a>
      <div class="nav-mobile-footer">
        <a href="logout.php" class="btn btn-sm btn-outline-dark">Logout</a>
      </div>
    </div>
  </header>

  <div class="page-content">

    <div class="search-page-head">
      <h1 class="page-title">Add Booking</h1>
      <p class="page-subtitle">Reserve a seat on an available trip</p>
    </div>

    <div id="bookingAlert" class="no-trips-message" style="<?= $errorMsg ? '' : 'display:none;' ?>"><?= e($errorMsg) ?></div>

    <form method="post" action="add-booking.php" id="bookingForm">
      <!-- Booking form -->
      <div class="card" style="padding:1.5rem; margin-bottom:1rem;">
        <div class="form-card-title" style="margin-bottom:1.25rem;">
          <i class="fa-regular fa-calendar-plus" style="color:var(--accent);"></i>
          Booking Details
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
          <label class="form-label" for="fullName">Full Name</label>
          <input class="form-input" type="text" id="fullName" name="full_name" value="<?= e($fullName) ?>" placeholder="Enter your full name">
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
          <label class="form-label" for="email">Email</label>
          <input class="form-input" type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="Enter your email">
        </div>

        <div class="form-group">
          <label class="form-label" for="tripSelect">Select Trip</label>
          <select class="form-select" id="tripSelect" name="trip_id">
            <option value="">Choose a trip...</option>
            <?php foreach ($trips as $tripKey => $trip): ?>
              <option value="<?= e($tripKey) ?>" <?= $selectedTripKey === $tripKey ? 'selected' : '' ?>>
                <?= e($trip['from']) ?> &rarr; <?= e($trip['to']) ?> | <?= e($trip['date_value']) ?> <?= e($trip['time']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Available trips -->
      <div class="search-results" id="availableTrips">
        <?php if (empty($trips)): ?>
          <div class="no-trips-message">No available trips at the moment.</div>
        <?php endif; ?>

        <?php foreach ($trips as $tripKey => $trip): ?>
          <div class="trip-search-card selectable-trip" data-trip="<?= e($tripKey) ?>">
            <div class="trip-search-bar"></div>
            <div class="trip-search-content">
              <div class="trip-search-top">
                <div class="trip-search-title-wrap">
                  <div class="trip-search-title">Bus <?= e($trip['bus']) ?></div>
                  <div class="trip-search-id">#<?= e($trip['id']) ?></div>
                </div>
                <span class="search-status-badge">active</span>
              </div>
              <div class="trip-search-route">
                <div class="trip-search-place"><?= e($trip['from']) ?></div>
                <div class="trip-search-line"></div>
                <div class="trip-search-place"><?= e($trip['to']) ?></div>
              </div>
              <div class="trip-search-meta">
                <span><?= e($trip['date']) ?></span>
                <span><?= e($trip['time']) ?></span>
                <span><?= e($trip['seats']) ?></span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <button type="submit" id="confirmBookingBtn" class="btn btn-accent btn-full" style="margin-top:1rem; padding:1rem 1.25rem; font-size:1rem; font-weight:800;">
        <i class="fa-solid fa-circle-check"></i>
        Confirm Booking
      </button>
    </form>

  </div>

  <!-- Confirmation modal -->
  <div id="successModal" class="modal <?= $successData ? 'active' : '' ?>">
    <div class="modal-box qr-pass-box" style="border-top: 5px solid var(--primary);">
      <h3 id="qrTitle">Boarding Pass</h3>

      <div class="qr-frame">
        <div id="qrCodeBox">
          <i class="fa-solid fa-qrcode" style="font-size: 100px; color:#1f3566;"></i>
        </div>
      </div>

      <p id="successMsg" class="qr-success-text">
        <?php if ($successData): ?>
          Reservation confirmed for <?= e($successData['date']) ?> at <?= e($successData['time']) ?>
        <?php endif; ?>
      </p>
      <p id="qrInfo" class="qr-info-text">
        <?php if ($successData): ?>
          <?= e($successData['from']) ?> &rarr; <?= e($successData['to']) ?> | <?= e($successData['bus']) ?><br>
          <?= e($successData['qr_value'] ?? '') ?>
        <?php endif; ?>
      </p>

      <button class="btn-primary" type="button" onclick="hideModals()">Close</button>
    </div>
  </div>

  <footer style="border-top:1px solid var(--border);padding:1.25rem 1rem;text-align:center;background:var(--bg);">
    <p class="text-muted text-sm">&copy; 2026 SAII. All rights reserved.</p>
  </footer>

<script>
  const tripSelect = document.getElementById("tripSelect");
  const selectableTrips = document.querySelectorAll(".selectable-trip");
  const confirmBookingBtn = document.getElementById("confirmBookingBtn");
  const bookingAlert = document.getElementById("bookingAlert");
  const successModal = document.getElementById("successModal");
  const bookingForm = document.getElementById("bookingForm");

  let selectedTripKey = <?= json_encode($selectedTripKey) ?>;

  function showAlert(message, type = "error") {
    bookingAlert.style.display = "block";
    bookingAlert.textContent = message;
    bookingAlert.style.color = type === "error" ? "var(--destructive)" : "var(--fg)";
    bookingAlert.style.borderColor = type === "error" ? "hsla(0,72%,51%,.25)" : "var(--border)";
    bookingAlert.style.background = type === "error" ? "hsla(0,72%,51%,.06)" : "var(--card-bg)";
  }

  function hideAlert() {
    bookingAlert.style.display = "none";
    bookingAlert.textContent = "";
  }

  function showAllTrips() {
    selectableTrips.forEach(card => {
      card.style.display = "flex";
      card.style.borderColor = "var(--border)";
      card.style.boxShadow = "var(--shadow)";
    });
  }

  function showOnlySelectedTrip(tripKey) {
    selectableTrips.forEach(card => {
      const cardTrip = card.getAttribute("data-trip");

      if (cardTrip === tripKey) {
        card.style.display = "flex";
        card.style.borderColor = "var(--accent)";
        card.style.boxShadow = "var(--shadow-md)";
      } else {
        card.style.display = "none";
      }
    });
  }

  function setSelectedTrip(tripKey) {
    selectedTripKey = tripKey;
    tripSelect.value = tripKey;
    showOnlySelectedTrip(tripKey);
    hideAlert();
  }

  function hideModals() {
    successModal.classList.remove("active");
  }

  tripSelect.addEventListener("change", function () {
    if (!this.value) {
      selectedTripKey = "";
      showAllTrips();
      return;
    }

    setSelectedTrip(this.value);
  });

  selectableTrips.forEach(card => {
    card.addEventListener("click", function () {
      const tripKey = this.getAttribute("data-trip");
      setSelectedTrip(tripKey);
    });
  });

  bookingForm.addEventListener("submit", function (e) {
    const val = tripSelect.value;
    const fullName = document.getElementById("fullName").value.trim();
    const email = document.getElementById("email").value.trim();

    if (!val) {
      e.preventDefault();
      showAlert("Please select a trip.");
      return;
    }

    if (!fullName) {
      e.preventDefault();
      alert("Please enter your full name.");
      return;
    }

    if (!email) {
      e.preventDefault();
      alert("Please enter your email.");
      return;
    }

    confirmBookingBtn.disabled = true;
    confirmBookingBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Booking...`;
  });

  successModal.addEventListener("click", function (e) {
    if (e.target === this) hideModals();
  });

  window.addEventListener("DOMContentLoaded", () => {
    if (selectedTripKey) {
      showOnlySelectedTrip(selectedTripKey);
    } else {
      showAllTrips();
    }
  });
</script>
</body>
</html>
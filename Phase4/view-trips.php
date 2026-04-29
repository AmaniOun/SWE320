<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

include "db_connection.php";

if (!isset($_SESSION['UserID'])) {
    header("Location: signin.php");
    exit();
}

$userName = $_SESSION['User_Name'] ?? "User";

$search = $_GET['search'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$locationsResult = mysqli_query($conn, "
    SELECT Origin AS location FROM trip
    UNION
    SELECT Destination AS location FROM trip
    ORDER BY location
");

$locations = [];

while ($locationRow = mysqli_fetch_assoc($locationsResult)) {
    $locations[] = $locationRow['location'];
}

$sql = "
SELECT 
    trip.TripID,
    trip.Origin,
    trip.Destination,
    trip.DepartureDate,
    trip.DepartureTime,
    trip.TotalSeats,
    trip.AvailableSeats,
    trip.Status,
    trip.Pickup_Location,
    bus.Bus_Number,
    bus.Capacity
FROM trip
JOIN bus ON trip.BusID = bus.BusID
WHERE trip.Status = 'Confirmed'
";

if (!empty($search)) {
    $searchEscaped = mysqli_real_escape_string($conn, $search);

    $sql .= " AND (
        trip.Origin LIKE '%$searchEscaped%' OR
        trip.Destination LIKE '%$searchEscaped%' OR
        bus.Bus_Number LIKE '%$searchEscaped%' OR
        trip.TripID LIKE '%$searchEscaped%'
    )";
}

if (!empty($from)) {
    $fromEscaped = mysqli_real_escape_string($conn, $from);
    $sql .= " AND trip.Origin = '$fromEscaped'";
}

if (!empty($to)) {
    $toEscaped = mysqli_real_escape_string($conn, $to);
    $sql .= " AND trip.Destination = '$toEscaped'";
}

$sql .= " ORDER BY trip.DepartureDate, trip.DepartureTime";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>View Trips – SAII</title>
  <link rel="stylesheet" href="styles.css" />
</head>

<body class="page-wrapper">

<header class="navbar">
  <div class="navbar-inner">
    <a href="user-dashboard.php" class="nav-logo">
      <img src="image/saii.png" alt="SAII Logo" class="logo-img"/>
    </a>

    <nav class="nav-links">
      <a href="user-dashboard.php" class="nav-link">Dashboard</a>
      <a href="view-trips.php" class="nav-link active">View Trips</a>
      <a href="add-booking.php" class="nav-link">Book a Trip</a>
      <a href="my_bookings.php" class="nav-link">My Bookings</a>
      <a href="user-heat-map.php" class="nav-link">Heat Map</a>
    </nav>

    <div class="nav-right">
      <span class="role-chip user">&#9679; pilgrim</span>
      <span style="color:rgba(255,255,255,.65);font-size:.85rem;">
          <?= htmlspecialchars($_SESSION['User_Name']) ?>
      </span>
      <a href="index.php" class="btn btn-sm btn-outline-dark">Logout</a>
    </div>

    <button class="nav-toggle" onclick="document.getElementById('nm').classList.toggle('open')" aria-label="Menu">&#9776;</button>
  </div>

  <div class="nav-mobile" id="nm">
    <a href="user-dashboard.php" class="nav-link">Dashboard</a>
    <a href="view-trips.php" class="nav-link active">View Trips</a>
    <a href="add-booking.php" class="nav-link">Book a Trip</a>
    <a href="my_bookings.php" class="nav-link">My Bookings</a>
    <a href="user-heat-map.php" class="nav-link">Heat Map</a>

    <div class="nav-mobile-footer">
      <a href="index.php" class="btn btn-sm btn-outline-dark">Logout</a>
    </div>
  </div>
</header>

<div class="page-content">

  <div class="search-page-head">
    <h1 class="page-title">View Trips</h1>
    <p class="page-subtitle">Browse, search, and filter available bus trips between holy sites</p>
  </div>

  <div class="card search-filter-card mb-6">
    <div class="card-body">

      <form method="GET" action="view-trips.php" class="search-filter-grid">

        <div class="form-group">
          <label class="form-label">Search</label>
          <input
            type="text"
            class="form-input"
            id="tripSearchInput"
            name="search"
            placeholder="Search by place, bus number, or trip ID"
            value="<?php echo htmlspecialchars($search); ?>"
          />
        </div>

        <div class="form-group">
          <label class="form-label">From</label>
          <select class="form-select" name="from">
            <option value="">All Locations</option>

            <?php foreach ($locations as $location): ?>
              <option value="<?php echo htmlspecialchars($location); ?>"
                <?php echo ($from == $location) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($location); ?>
              </option>
            <?php endforeach; ?>

          </select>
        </div>

        <div class="form-group">
          <label class="form-label">To</label>
          <select class="form-select" name="to">
            <option value="">All Locations</option>

            <?php foreach ($locations as $location): ?>
              <option value="<?php echo htmlspecialchars($location); ?>"
                <?php echo ($to == $location) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($location); ?>
              </option>
            <?php endforeach; ?>

          </select>
        </div>

        <div class="search-clear-wrap">

  <!-- زر البحث -->
  <button type="submit" class="btn btn-accent search-clear-btn">
    <b>Search</b>
  </button>

  <!-- زر الكلير -->
  <button type="button" class="btn btn-accent search-clear-btn" onclick="clearFilters()">
    <b>Clear</b>
  </button>

</div>

      </form>

    </div>
  </div>

  <br>

  <div class="search-results">

    <?php if (mysqli_num_rows($result) == 0): ?>

      <div id="noTripsMessage" class="no-trips-message" style="display:block;">
        No trips were found.
      </div>

    <?php else: ?>

      <?php while ($row = mysqli_fetch_assoc($result)): ?>

        <?php
          $bookedSeats = $row['TotalSeats'] - $row['AvailableSeats'];
          $date = date("M d, Y", strtotime($row['DepartureDate']));
          $time = date("h:i A", strtotime($row['DepartureTime']));
        ?>

        <div class="trip-search-card">
          <div class="trip-search-bar"></div>

          <div class="trip-search-content">

            <div class="trip-search-top">
              <div class="trip-search-title-wrap">
                <div class="trip-search-title">
                  <?php echo htmlspecialchars($row['Bus_Number']); ?>
                </div>

                <div class="trip-search-id">
                  #TRP-<?php echo htmlspecialchars($row['TripID']); ?>
                </div>
              </div>

              <span class="search-status-badge">
  <?php
    if ($row['Status'] == 'Confirmed') {
        echo 'active';
    } else {
        echo htmlspecialchars($row['Status']);
    }
  ?>
</span>
            </div>

            <div class="trip-search-route">
              <div class="trip-search-place">
                <?php echo htmlspecialchars($row['Origin']); ?>
              </div>

              <div class="trip-search-line"></div>

              <div class="trip-search-place">
                <?php echo htmlspecialchars($row['Destination']); ?>
              </div>
            </div>

            <div class="trip-search-meta">
              <span><?php echo $date; ?></span>
              <span><?php echo $time; ?></span>
              <span>
                <?php echo $bookedSeats; ?>/<?php echo htmlspecialchars($row['TotalSeats']); ?> seats
              </span>
            </div>

            <div class="trip-extra-details">
              <span>
                <strong>Pickup Point:</strong>
                <?php echo htmlspecialchars($row['Pickup_Location']); ?>
              </span>

              <span>
                <strong>Available Seats:</strong>
                <?php echo htmlspecialchars($row['AvailableSeats']); ?>
              </span>

              <span>
                <strong>Bus Capacity:</strong>
                <?php echo htmlspecialchars($row['Capacity']); ?>
              </span>
            </div>

            <div class="trip-search-actions">
              <a href="add-booking.php?trip_id=<?php echo $row['TripID']; ?>" class="btn btn-accent">
                <b>Book Now</b>
              </a>
            </div>

          </div>
        </div>

      <?php endwhile; ?>

    <?php endif; ?>

  </div>

</div>

<footer class="site-footer">
  <p>© 2026 SAII. All rights reserved.</p>
</footer>
<script>
function clearFilters() {
    window.location.href = "view-trips.php";
}
</script>
</body>
</html>

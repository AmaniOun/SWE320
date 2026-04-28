<?php
session_start();
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $tripId  = (int)($_POST['TripID'] ?? 0);

      /* =========================
        CANCEL TRIP
      ========================= */
    if ($action === 'cancel' && $tripId) {

      // 1) إلغاء الرحلة
      $stmt = mysqli_prepare($conn, "UPDATE trip SET Status='Cancelled' WHERE TripID=?");
      mysqli_stmt_bind_param($stmt, 'i', $tripId);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);

      // 2) إلغاء كل الحجوزات المرتبطة
      $stmt = mysqli_prepare($conn, "UPDATE booking SET BookingStatus='Cancelled' WHERE TripID=?");
      mysqli_stmt_bind_param($stmt, 'i', $tripId);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);

      // 3) (اختياري 🔥 مهم) تحديث QR Codes إلى Expired
      $stmt = mysqli_prepare($conn, "
          UPDATE qrcode q
          JOIN booking b ON q.BookingID = b.BookingID
          SET q.QR_Status = 'Expired'
          WHERE b.TripID = ?
      ");
      mysqli_stmt_bind_param($stmt, 'i', $tripId);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);

      // 4) إشعار
      $msg = "The trip has been cancelled";
      $stmt = mysqli_prepare($conn, "INSERT INTO notification (message, TripID) VALUES (?, ?)");
      mysqli_stmt_bind_param($stmt, 'si', $msg, $tripId);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);

      $_SESSION['toast'] = ['msg' => "Trip #$tripId cancelled", 'type' => 'info'];
    }

    /* =========================
       DELETE TRIP
    ========================= */
    if ($action === 'delete' && $tripId) {

        mysqli_query($conn, "DELETE FROM notification WHERE TripID=$tripId");
        mysqli_query($conn, "DELETE FROM trip WHERE TripID=$tripId");

        $_SESSION['toast'] = ['msg' => "Trip #$tripId deleted", 'type' => 'error'];
    }

    /* =========================
       EDIT TRIP + NOTIFICATION
    ========================= */
    if ($action === 'edit' && $tripId) {

        $origin      = trim($_POST['Origin'] ?? '');
        $destination = trim($_POST['Destination'] ?? '');
        $date        = trim($_POST['DepartureDate'] ?? '');
        $time        = trim($_POST['DepartureTime'] ?? '');
        $seats       = (int)($_POST['TotalSeats'] ?? 0);
        $busNum      = trim($_POST['Bus_Number'] ?? '');

        // نجيب البيانات القديمة كاملة
        $oldRes = mysqli_query($conn, "SELECT DepartureDate, DepartureTime, BusID, Origin, Destination FROM trip WHERE TripID=$tripId");
        $oldRow = mysqli_fetch_assoc($oldRes);
        $oldTime   = $oldRow['DepartureTime']  ?? null;
        $oldDate   = $oldRow['DepartureDate']  ?? null;
        $oldBusId  = $oldRow['BusID']          ?? null;

        // نجيب BusID الجديد
        $stmt = mysqli_prepare($conn, "SELECT BusID FROM bus WHERE Bus_Number=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $busNum);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $busRow = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        $busId = $busRow['BusID'] ?? null;

        if ($busId) {

            // تحديث الرحلة
            $stmt = mysqli_prepare($conn,
                "UPDATE trip SET Origin=?, Destination=?, DepartureDate=?, DepartureTime=?, TotalSeats=?, BusID=? WHERE TripID=?");
            mysqli_stmt_bind_param($stmt, 'sssssii',
                $origin, $destination, $date, $time, $seats, $busId, $tripId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // 🔔 كل تغيير = إشعار منفصل في قاعدة البيانات
            $changes = [];

            if ($oldDate && $oldDate !== $date) {
                $changes[] = "date changed from $oldDate to $date";
            }

            if ($oldTime && substr($oldTime, 0, 5) !== $time) {
                $oldH = (int)explode(':', $oldTime)[0];
                $newH = (int)explode(':', $time)[0];
                $label = $newH > $oldH ? "Delay" : "Schedule Change";
                $changes[] = "$label: departure time changed from " . substr($oldTime, 0, 5) . " to $time";
            }

            if ($oldBusId && $oldBusId != $busId) {
                $changes[] = "bus changed to $busNum";
            }

            if ($oldRow['Origin'] !== $origin || $oldRow['Destination'] !== $destination) {
                $changes[] = "Route changed from {$oldRow['Origin']} → {$oldRow['Destination']} to $origin → $destination";
            }

            if (!empty($changes)) {
                // ✅ كل التغييرات في رسالة واحدة مفصولة بـ " | "
                $msg = implode(" | ", $changes);
                $stmt = mysqli_prepare($conn, "INSERT INTO notification (message, TripID) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, 'si', $msg, $tripId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            // لو ما في تغييرات ما يُرسل إشعار
        }

        $_SESSION['toast'] = ['msg' => "Trip #$tripId updated", 'type' => 'success'];
    }

    header("Location: manage-trips.php");
    exit();
}

/* =========================
   FETCH DATA
========================= */

$search = trim($_GET['q'] ?? '');
$where  = '';

if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $where = "WHERE t.Origin LIKE '%$s%'
              OR t.Destination LIKE '%$s%'
              OR b.Bus_Number LIKE '%$s%'
              OR t.TripID LIKE '%$s%'";
}

$trips = [];
$res = mysqli_query($conn,
    "SELECT t.*, b.Bus_Number
     FROM trip t
     JOIN bus b ON t.BusID = b.BusID
     $where
     ORDER BY t.DepartureDate ASC, t.DepartureTime ASC");

while ($row = mysqli_fetch_assoc($res)) {
    $trips[] = $row;
}

// جلب الباصات
$buses = [];
$busRes = mysqli_query($conn, "SELECT BusID, Bus_Number FROM bus ORDER BY Bus_Number ASC");
while ($busRow = mysqli_fetch_assoc($busRes)) {
    $buses[] = $busRow;
}

/* =========================
   HELPERS
========================= */

function fmtTime($t) {
    if (!$t) return '';
    return substr($t, 0, 5);
}

function fmtDate($d) {
    if (!$d) return '';
    $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $p = explode('-', $d);
    return $months[(int)$p[1]-1] . ' ' . (int)$p[2] . ', ' . $p[0];
}

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Manage Trips — SAII Hajj Transport</title>
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
      <a href="manage-trips.php"    class="nav-link active">Manage Trips</a>
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
    <a href="admin-dashboard.php" class="nav-link">Dashboard</a>
    <a href="manage-trips.php"    class="nav-link active">Manage Trips</a>
    <a href="add-trip.php"        class="nav-link">Add Trip</a>
    <div class="nav-mobile-footer"><a href="logout.php" class="btn btn-sm btn-outline-dark">Logout</a></div>
  </div>
</header>

<!-- ── CONTENT ── -->
<div class="page-content">
  <div class="page-header">
    <div>
      <h1 class="page-title">All Trips</h1>
      <p class="page-subtitle">Manage all transportation schedules</p>
    </div>
    <a href="add-trip.php" class="btn btn-accent">+ Add New Trip</a>
  </div>

  <form method="GET" action="manage-trips.php" style="margin-bottom:.5rem;">
    <div class="search-bar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input type="text" name="q" id="search-input"
             placeholder="Search by Trip ID, bus number or route…"
             value="<?= htmlspecialchars($search) ?>"
             onchange="this.form.submit()"/>
    </div>
  </form>

  <p class="trip-count-label">
    <?= $search
      ? count($trips) . ' result' . (count($trips) !== 1 ? 's' : '') . ' for "' . htmlspecialchars($search) . '"'
      : count($trips) . ' trip' . (count($trips) !== 1 ? 's' : '') . ' total'
    ?>
  </p>

  <div class="trips-list" id="trips-list">
    <?php if (empty($trips)): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/>
        </svg>
        <p>No trips match your search</p>
      </div>
    <?php else: ?>
      <?php foreach ($trips as $t):
        $booked = (int)$t['TotalSeats'] - (int)$t['AvailableSeats'];
        $statusClass = strtolower($t['Status']);
        if ($statusClass === 'confirmed') $statusClass = 'active';
        elseif ($statusClass === 'completed') $statusClass = 'info';
        elseif ($statusClass === 'cancelled') $statusClass = 'cancelled';
      ?>
      <div class="trip-card">
        <div class="trip-card-top">
          <div class="trip-card-title">
            <div class="bus-icon-wrap">
              <svg viewBox="0 0 24 24"><path d="M17 5H3C1.89 5 1 5.89 1 7v10c0 1.11.89 2 2 2h1a2 2 0 004 0h6a2 2 0 004 0h1c1.11 0 2-.89 2-2v-5l-3-5zm-1 1.5l2.28 3.5H13V6.5h3zm-11 9a1 1 0 11-2 0 1 1 0 012 0zm9 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
            </div>
            <div>
              <span class="trip-bus-name"><?= htmlspecialchars($t['Bus_Number']) ?></span>
              <span class="trip-id-tag"> #<?= $t['TripID'] ?></span>
            </div>
          </div>
          <span class="badge badge-<?= $statusClass ?>"><?= htmlspecialchars($t['Status']) ?></span>
        </div>

        <div class="trip-route">
          <span><?= htmlspecialchars($t['Origin']) ?></span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px">
            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
          </svg>
          <span class="to-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;color:var(--accent)">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>
            </svg>
            <?= htmlspecialchars($t['Destination']) ?>
          </span>
        </div>

        <div class="trip-meta">
          <span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <?= fmtDate($t['DepartureDate']) ?>
          </span>
          <span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            <?= fmtTime($t['DepartureTime']) ?>
          </span>
          <span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
            </svg>
            <?= $booked ?>/<?= $t['TotalSeats'] ?> seats
          </span>
        </div>

        <?php if ($t['Pickup_Location']): ?>
        <div style="font-size:.8rem;color:var(--fg-muted);padding:.25rem 0 .5rem;">
          📍 <?= htmlspecialchars($t['Pickup_Location']) ?>
        </div>
        <?php endif; ?>

        <div class="trip-actions">
          <button class="btn btn-sm btn-outline"
            onclick="openEdit(
              <?= $t['TripID'] ?>,
              '<?= htmlspecialchars(addslashes($t['Origin'])) ?>',
              '<?= htmlspecialchars(addslashes($t['Destination'])) ?>',
              '<?= $t['DepartureDate'] ?>',
              '<?= substr($t['DepartureTime'],0,5) ?>',
              <?= $t['TotalSeats'] ?>,
              '<?= htmlspecialchars(addslashes($t['Bus_Number'])) ?>'
            )">✏ Edit</button>

          <?php if ($t['Status'] === 'Confirmed'): ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action"  value="cancel"/>
            <input type="hidden" name="TripID"  value="<?= $t['TripID'] ?>"/>
            <button type="submit" class="btn btn-sm btn-ghost"
              onclick="return confirm('Cancel this trip?')">○ Cancel</button>
          </form>
          <?php endif; ?>

          <button class="btn btn-sm btn-outline"
            style="color:var(--destructive);border-color:var(--destructive);"
            onclick="openDelete(<?= $t['TripID'] ?>)">🗑 Delete</button>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- ── EDIT MODAL ── -->
<div class="modals-overlay" id="edit-modal">
  <div class="modals">
    <div class="modals-header">
      <h3>Edit Trip Schedule</h3>
      <button class="btn-modal-close" onclick="closeModal('edit-modal')">&#10005;</button>
    </div>
    <form method="POST" action="manage-trips.php">
      <input type="hidden" name="action" value="edit"/>
      <input type="hidden" name="TripID" id="edit-trip-id"/>
      <div class="modals-grid">
        <div class="modals-field">
          <label>From</label>
          <div class="select-wrap">
            <select class="form-select" name="Origin" id="edit-from">
              <?php $locs=['Masjid Al-Haram','Mina','Arafat','Muzdalifah','Makkah Hotel Zone','Madinah','Aziziyah','Jamarat'];
              foreach ($locs as $l): ?><option><?= $l ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modals-field">
          <label>To</label>
          <div class="select-wrap">
            <select class="form-select" name="Destination" id="edit-to">
              <?php foreach ($locs as $l): ?><option><?= $l ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modals-field">
          <label>Date</label>
          <input class="form-input" type="date" name="DepartureDate" id="edit-date"/>
        </div>
        <div class="modals-field">
          <label>Departure Time</label>
          <input class="form-input" type="time" name="DepartureTime" id="edit-time"/>
        </div>
        <div class="modals-field">
          <label>Total Seats</label>
          <input class="form-input" type="number" name="TotalSeats" id="edit-seats" min="1" max="200"/>
        </div>
        <div class="modals-field">
          <label>Bus Number</label>
          <div class="select-wrap">
            <select class="form-select" name="Bus_Number" id="edit-bus">
              <?php foreach ($buses as $b): ?>
                <option value="<?= htmlspecialchars($b['Bus_Number']) ?>">
                  <?= htmlspecialchars($b['Bus_Number']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-accent btn-full" style="margin-top:1.1rem;">Save Changes</button>
    </form>
  </div>
</div>

<!-- ── DELETE MODAL ── -->
<div class="modals-overlay" id="delete-modal">
  <div class="modals confirm-modal">
    <div class="confirm-icon-wrap">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="3 6 5 6 21 6"/>
        <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
      </svg>
    </div>
    <h3>Delete Trip?</h3>
    <p>This action cannot be undone. The trip will be permanently removed.</p>
    <form method="POST" action="manage-trips.php">
      <input type="hidden" name="action" value="delete"/>
      <input type="hidden" name="TripID" id="delete-trip-id"/>
      <div class="confirm-actions">
        <button type="button" class="btn btn-outline btn-full" onclick="closeModal('delete-modal')">Cancel</button>
        <button type="submit" class="btn btn-destructive btn-full">Delete</button>
      </div>
    </form>
  </div>
</div>

<!-- ── SHARED FOOTER ── -->
<footer class="site-footer">
  <p>© 2026 SAII. All rights reserved.</p>
</footer>

<!-- Toast -->
<div class="toast" id="toast"></div>

<?php if ($toast): ?>
<script>
  window.addEventListener('DOMContentLoaded', function(){
    showToast('<?= addslashes($toast['msg']) ?>', '<?= $toast['type'] ?>');
  });
</script>
<?php endif; ?>

<script>
  function openEdit(id, from, to, date, time, seats, bus) {
    document.getElementById('edit-trip-id').value = id;
    document.getElementById('edit-from').value    = from;
    document.getElementById('edit-to').value      = to;
    document.getElementById('edit-date').value    = date;
    document.getElementById('edit-time').value    = time;
    document.getElementById('edit-seats').value   = seats;
    document.getElementById('edit-bus').value     = bus;
    openModal('edit-modal');
  }
  function openDelete(id) {
    document.getElementById('delete-trip-id').value = id;
    openModal('delete-modal');
  }
  function openModal(id)  { document.getElementById(id).classList.add('open'); }
  function closeModal(id) { document.getElementById(id).classList.remove('open'); }
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') document.querySelectorAll('.modals-overlay.open').forEach(m => closeModal(m.id));
  });

  var _tt;
  function showToast(msg, type) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast ' + (type || 'info') + ' show';
    clearTimeout(_tt);
    _tt = setTimeout(function(){ t.classList.remove('show'); }, 3200);
  }
</script>
</body>
</html>
<?php mysqli_close($conn); ?>
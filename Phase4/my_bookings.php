<?php
ob_start();
session_start();


if (!isset($_SESSION['UserID'])) {
    header("Location: signin.php"); 
    exit();
}

$host = "localhost";
$dbname = "saii";
$dbuser = "root";
$dbpass = "root";

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $dbuser,
        $dbpass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection error: " . $e->getMessage());
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking_id'])) {
    $bookingID = $_POST['update_booking_id'];
    $newTripID = $_POST['new_trip_id'];

    try {
       
        $stmtOld = $pdo->prepare("SELECT t.DepartureDate, t.DepartureTime, b.TripID as OldTripID, bus.Capacity 
                                   FROM booking b 
                                   JOIN trip t ON b.TripID = t.TripID 
                                   JOIN bus bus ON t.BusID = bus.BusID
                                   WHERE b.BookingID = ?");
        $stmtOld->execute([$bookingID]);
        $currentTrip = $stmtOld->fetch(PDO::FETCH_ASSOC);

        if (!$currentTrip) die("Booking not found.");

        date_default_timezone_set('Asia/Riyadh');
        $departureTime = strtotime($currentTrip['DepartureDate'] . ' ' . $currentTrip['DepartureTime']);
        
        if (($departureTime - time()) < (3 * 3600)) { 
            header("Location: my_bookings.php?msg=too_late");
            exit();
        }
        
        $oldTripID = $currentTrip['OldTripID'];
        $maxCapacity = $currentTrip['Capacity']; 

        if ($oldTripID == $newTripID) {
            header("Location: my_bookings.php?msg=updated");
            exit();
        }

        $pdo->beginTransaction();

        
        $stmtLock = $pdo->prepare("SELECT AvailableSeats FROM trip WHERE TripID = ? FOR UPDATE");
        $stmtLock->execute([$newTripID]);
        $seats = $stmtLock->fetchColumn();

        if ($seats <= 0) {
            $pdo->rollBack();
            header("Location: my_bookings.php?msg=full");
            exit();
        }

     
        
       
        $pdo->prepare("UPDATE trip SET AvailableSeats = AvailableSeats - 1 WHERE TripID = ?")->execute([$newTripID]);
        
        
        $pdo->prepare("UPDATE trip SET AvailableSeats = AvailableSeats + 1 
                       WHERE TripID = ? AND AvailableSeats < ?")
            ->execute([$oldTripID, $maxCapacity]);

       
        $pdo->prepare("UPDATE booking SET TripID = ? WHERE BookingID = ?")->execute([$newTripID, $bookingID]);

       
        $newQRValue = "QR-SAII-BK" . str_pad($bookingID, 3, '0', STR_PAD_LEFT) . "-" . date("Y") . "-" . bin2hex(random_bytes(2));
        $pdo->prepare("UPDATE qrcode SET QR_Value = ?, GeneratedAt = NOW() WHERE BookingID = ?")->execute([$newQRValue, $bookingID]);

        $pdo->commit(); 
        header("Location: my_bookings.php?msg=updated");
        exit();
        
        
  


    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}



if (isset($_GET['cancel_id'])) {
    $id = $_GET['cancel_id'];
    
    
    $stmtCheck = $pdo->prepare("SELECT t.DepartureDate, t.DepartureTime, b.TripID, b.BookingStatus 
                                FROM booking b 
                                JOIN trip t ON b.TripID = t.TripID 
                                WHERE b.BookingID = ?");
    $stmtCheck->execute([$id]);
    $bookingData = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($bookingData && $bookingData['BookingStatus'] !== 'Cancelled') {
        
        $departureTime = strtotime($bookingData['DepartureDate'] . ' ' . $bookingData['DepartureTime']);
        $currentTime = time();

        
        if ($currentTime >= $departureTime) {
            header("Location: my_bookings.php?msg=too_late");
            exit();
        }

        try {
            $pdo->beginTransaction(); 

            
            $stmt = $pdo->prepare("UPDATE booking SET BookingStatus = 'Cancelled' WHERE BookingID = ?");
            $stmt->execute([$id]);
            
          

           
            $stmtSeat = $pdo->prepare("UPDATE trip SET AvailableSeats = AvailableSeats + 1 WHERE TripID = ?");
            $stmtSeat->execute([$bookingData['TripID']]);

            $pdo->commit();
            
            header("Location: my_bookings.php?status=cancelled");
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            die("Error during cancellation: " . $e->getMessage());
        }
    } else {
       
        header("Location: my_bookings.php");
        exit();
    }
}





$tripsQuery = $pdo->query("SELECT TripID, Origin, Destination, DepartureDate, DepartureTime, AvailableSeats 
                           FROM trip 
                           WHERE Status = 'Confirmed'
                           AND DepartureDate >= CURDATE() 
                           AND AvailableSeats > 0");
$availableTrips = $tripsQuery->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT b.BookingID as id, b.BookingStatus as status, b.TripID,
               t.Origin, t.Destination, t.DepartureDate as date, t.DepartureTime as time, 
               bus.Bus_Number as bus, q.QR_Value
        FROM booking b
        INNER JOIN trip t ON b.TripID = t.TripID
        INNER JOIN bus bus ON t.BusID = bus.BusID
        LEFT JOIN pilgrim p ON b.PilgrimID = p.PilgrimID
        LEFT JOIN qrcode q ON b.BookingID = q.BookingID
        WHERE p.UserID = :uid OR b.PilgrimID = (SELECT PilgrimID FROM pilgrim WHERE UserID = :uid LIMIT 1)
        ORDER BY t.DepartureDate DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['uid' => $_SESSION['UserID']]);
$bookingsDB = $stmt->fetchAll(PDO::FETCH_ASSOC);

date_default_timezone_set('Asia/Riyadh');
foreach($bookingsDB as &$b) {
    $b['route'] = $b['Origin'] . " → " . $b['Destination'];
    // نحول التاريخ والوقت بتوقيت الرياض صراحة
    $depDateTime = strtotime($b['date'] . ' ' . $b['time'] . ' Asia/Riyadh');
    $b['isPast'] = (time() > $depDateTime);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Bookings - SAII System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="styles.css"/>
    <style>
        :root { 
            --primary: #1f3566; 
            --accent: #e2a94b; 
            --success: #1f3566; 
            --danger: #d92d20; 
            --warning: #f3a000; 
            --muted: #667085;
        }
        body {
  font-family:'Inter',sans-serif;
  background:var(--bg);
  color:var(--fg);
  min-height:100vh;
  line-height:1.6;
  -webkit-font-smoothing:antialiased;
}
        .container { width: 92%; max-width: 1100px; margin: 30px auto; min-height: 80vh; }
        .booking-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 22px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; box-shadow: 0 3px 10px rgba(0,0,0,0.04); border-left: 6px solid #ccc; }
        .status-confirmed { border-left-color: #2e7d32 !important; }
        .status-cancelled { border-left-color: var(--danger) !important; }
        .status-past { border-left-color: #9ca3af !important; }
        .status-badge { font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 5px 12px; border-radius: 999px; display: inline-block; margin-bottom: 10px; }
        .badge-confirmed { background: #e8f7ec; color: #1f3566; }
        .badge-cancelled { background: #fdecec; color: #b42318; }
        .badge-past { background: #f0f0f0; color: #6b7280; }
        .edit-form-container { background: #fcfcfc; border: 1px dashed var(--accent); padding: 20px; border-radius: 12px; margin: 15px 0; }
        .edit-select { width: 100% !important; padding: 12px !important; border-radius: 8px !important; border: 2px solid var(--primary) !important; background: white !important; color: var(--primary) !important; font-weight: 600 !important; appearance: none !important; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%231f3566' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e") !important; background-repeat: no-repeat !important; background-position: right 15px center !important; background-size: 15px !important; }
        .edit-actions { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 12px !important; margin-top: 15px !important; }
        .save-inline-btn, .cancel-inline-btn { height: 48px !important; border-radius: 10px !important; font-weight: 700 !important; cursor: pointer !important; border: none !important; display: flex !important; align-items: center !important; justify-content: center !important; transition: 0.2s; }
        .save-inline-btn { background: var(--success) !important; color: white !important; }
        .cancel-inline-btn { background: #f2f4f7 !important; color: #667085 !important; border: 1px solid #d0d5dd !important; }
        .icon-btn { width: 38px; height: 38px; border-radius: 50%; border: 1px solid #d1d5db; background: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: 0.2s; margin-left: 8px; }
        .modal { display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.45); align-items:center; justify-content:center; z-index:9999; }
        .modal-box { background:white; padding:30px; border-radius:16px; text-align:center; width:90%; max-width:400px; position: relative; }
        .expired-overlay { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-15deg); background: rgba(217, 45, 32, 0.85); color: white; padding: 5px 15px; font-weight: bold; border-radius: 5px; font-size: 18px; z-index: 10; border: 2px solid white; pointer-events: none; }
    </style>
<header class="navbar">
  <div class="navbar-inner">
    <a href="user-dashboard.php" class="nav-logo">
      <img src="image/saii.png" alt="SAII Logo" class="logo-img"/>
    </a>

    <nav class="nav-links">
      <a href="user-dashboard.php" class="nav-link">Dashboard</a>
      <a href="view-trips.php" class="nav-link ">View Trips</a>
      <a href="add-booking.php" class="nav-link">Book a Trip</a>
      <a href="my_bookings.php" class="nav-link active">My Bookings</a>
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

<div class="container">
    <h2>My Bookings</h2>
    <p style="color:#6b7280;">Welcome back, <strong><?= htmlspecialchars($_SESSION['User_Name']) ?></strong></p>
    <div id="bookingsList"></div>
</div>

<div id="qrModal" class="modal">
    <div class="modal-box">
        <h3>QR Pass</h3>
        <div style="font-size: 80px; margin: 20px 0; position: relative; display: inline-block;">
            <i class="fa-solid fa-qrcode"></i>
            <div id="qrExpiredTag" class="expired-overlay" style="display:none;">EXPIRED</div>
        </div>
        <div id="qrValueDisplay" style="font-family: monospace; background: #f4f4f4; padding: 10px; border-radius: 8px; margin-bottom: 15px; color: #1f3566; font-weight: bold; border: 1px dashed #ccc;"></div>
        <p id="qrRouteName"></p>
        <button onclick="closeModal('qrModal')" class="save-inline-btn" style="width:100%">Close</button>
    </div>
</div>

<div id="cancelModal" class="modal">
    <div class="modal-box">
        <h3>Are you sure?</h3>
        <p>This action cannot be undone.</p>
        <div class="edit-actions">
            <button id="confirmCancelBtn" class="save-inline-btn" style="background:var(--danger) !important;">Yes, Cancel</button>
            <button onclick="closeModal('cancelModal')" class="cancel-inline-btn">Keep Booking</button>
        </div>
    </div>
</div>

<div id="deniedModal" class="modal">
    <div class="modal-box">
        <i class="fa-solid fa-circle-xmark" style="font-size:50px; color:var(--danger);"></i>
        <h3 id="deniedTitle"></h3>
        <p id="deniedDesc"></p>
        <button onclick="closeModal('deniedModal')" class="save-inline-btn">Understood</button>
    </div>
</div>
    
    
 <div id="successModal" class="modal">
    <div class="modal-box">
        <i class="fa-solid fa-circle-check" style="font-size:50px; color:var(--primary);"></i>
        <h3 id="successTitle">Success!</h3>
        <p id="successDesc"></p>
        <button onclick="closeModal('successModal')" class="save-inline-btn">Awesome</button>
    </div>
</div>   
    
    
    
    
    
    
    
      
<!-- ── SHARED FOOTER ── -->
<footer class="site-footer">
  <p>© 2026 SAII. All rights reserved.</p>
</footer>   
    
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script >
   
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    const status = urlParams.get('status');

    if (msg === 'updated') {
        
        document.getElementById("successTitle").innerText = "Success!";
        document.getElementById("successDesc").innerText = "Your booking has been updated successfully.";
        showModal("successModal");
    } 
    else if (msg === 'too_late') {
        showDenied("Too Late!", "Modification is only allowed up to 3 hours before departure.");
    } 
    else if (msg === 'full') {
        showDenied("No Seats!", "The selected trip is fully booked.");
    }

    if (status === 'cancelled') {
        document.getElementById("successTitle").innerText = "Cancelled";
        document.getElementById("successDesc").innerText = "Your booking has been cancelled successfully.";
        showModal("successModal"); // نستخدم مودال النجاح هنا أيضاً لأن الفعل تم بنجاح
    }
    
    if (msg || status) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }
};
       
  
const bookings = <?php echo json_encode($bookingsDB); ?>;
const availableTrips = <?php echo json_encode($availableTrips); ?>;
const list = document.getElementById("bookingsList");
let selectedId = null;

function render() {
    list.innerHTML = "";
    if (bookings.length === 0) { list.innerHTML = "<p>No bookings found.</p>"; return; }

    bookings.forEach(b => {
        let statusText = b.status;
        let badgeClass = 'badge-confirmed';
        let statusClass = 'status-confirmed';
        let isLocked = false;

        if(b.status === 'Cancelled') {
            statusText = 'Cancelled';
            badgeClass = 'badge-cancelled';
            statusClass = 'status-cancelled';
            isLocked = true;
        } else if(b.isPast) {
            statusText = 'Completed';
            badgeClass = 'badge-past';
            statusClass = 'status-past';
            isLocked = true;
        }

        const card = document.createElement("div");
        card.className = `booking-card ${statusClass}`;
        
        card.innerHTML = `
            <div style="flex:1;">
                <div class="status-badge ${badgeClass}">${statusText}</div>
                <div id="route-view-${b.id}">
                    <div style="font-size:18px; font-weight:700; color:var(--primary);">${b.route}</div>
                </div>

                <form id="route-edit-${b.id}" method="POST" action="my_bookings.php" style="display:none;" class="edit-form-container">
                    <input type="hidden" name="update_booking_id" value="${b.id}">
                    <label style="display:block; font-size:12px; color:var(--muted); margin-bottom:8px; font-weight:bold;">Select New Destination:</label>
                
<select name="new_trip_id" class="edit-select">
    ${availableTrips.map(t => `
        <option value="${t.TripID}" ${t.TripID == b.TripID ? 'selected' : ''}>
            [ID: ${t.TripID}] ${t.Origin} → ${t.Destination} (${t.DepartureDate} | ${t.DepartureTime})
        </option>
    `).join('')}
</select>
                    <div class="edit-actions">
                        <button type="submit" class="save-inline-btn"><i class="fa-solid fa-check"></i> Save Changes</button>
                        <button type="button" class="cancel-inline-btn" onclick="toggleEditMode('${b.id}', false)">Cancel</button>
                    </div>
                </form>

                <div class="trip-details" style="margin-top:10px; font-size:13px; color:var(--muted);">
                    <span><i class="fa-solid fa-calendar"></i> ${b.date}</span> | 
                    <span><i class="fa-solid fa-clock"></i> ${b.time}</span> | 
                    <span><i class="fa-solid fa-bus"></i> ${b.bus}</span>
                </div>
            </div>
            <div class="booking-actions">
                <button class="icon-btn" onclick="handleQR('${b.QR_Value}', '${statusText}', '${b.route}')">
                    <i class="fa-solid fa-qrcode" style="color:${isLocked && statusText !== 'Completed' ? '#ccc' : 'var(--primary)'}"></i>
                </button>
                <button class="icon-btn" onclick="handleEdit('${b.id}', '${statusText}')">
                    <i class="fa-solid fa-pen-to-square" style="color:${isLocked ? '#ccc' : 'var(--primary)'}"></i>
                </button>
                <button class="icon-btn" onclick="handleCancel('${b.id}', '${statusText}')">
                    <i class="fa-solid ${isLocked ? 'fa-ban' : 'fa-xmark'}" style="color:${isLocked ? '#ccc' : 'var(--danger)'}"></i>
                </button>
            </div>
        `;
        list.appendChild(card);
    });
}

function toggleEditMode(id, isEditing) {
    document.getElementById(`route-view-${id}`).style.display = isEditing ? 'none' : 'block';
    document.getElementById(`route-edit-${id}`).style.display = isEditing ? 'block' : 'none';
}

function handleQR(qrVal, status, route) {
    if (status === 'Cancelled') {
        showDenied("Action Denied", "QR is not available for cancelled trips.");
        return;
    }
    const displayLabel = document.getElementById("qrValueDisplay");
    if (displayLabel) displayLabel.innerText = qrVal && qrVal !== 'null' ? qrVal : "No ID Assigned";
    document.getElementById("qrRouteName").innerText = route;
    const expiredTag = document.getElementById("qrExpiredTag");
    if (expiredTag) expiredTag.style.display = (status === 'Completed') ? 'block' : 'none';
    showModal("qrModal");
}

function handleEdit(id, status) {
    if (status === 'Cancelled' || status === 'Completed') { showDenied("Locked", "You cannot modify this trip."); } 
    else { toggleEditMode(id, true); }
}

function handleCancel(id, status) {
    if (status === 'Cancelled' || status === 'Completed') { showDenied("Denied", "Already processed."); } 
    else { selectedId = id; showModal("cancelModal"); }
}






document.getElementById("confirmCancelBtn").onclick = () => { 
    if(selectedId) {
        window.location.href = "my_bookings.php?cancel_id=" + selectedId; 
    }
};



function showDenied(t, d) { 
    document.getElementById("deniedTitle").innerText = t; 
    document.getElementById("deniedDesc").innerText = d; 
    showModal("deniedModal"); 
}
function showModal(id) { document.getElementById(id).style.display = "flex"; }
function closeModal(id) { document.getElementById(id).style.display = "none"; }

render();
</script>
</body>
</html>
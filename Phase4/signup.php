<?php
session_start();
include("db_connection.php");

// تسجيل الحساب
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $email = $_POST['email'];
    $pass  = $_POST['password'];

    $fullName = $fname . " " . $lname;

    // التحقق من الإيميل
    $check = $conn->prepare("SELECT UserID FROM user WHERE Email=?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $error = "Email already exists";
    } else {

        // تشفير كلمة السر
        $hashed = password_hash($pass, PASSWORD_DEFAULT);

        // إدخال المستخدم
        $stmt = $conn->prepare("INSERT INTO user (Email, Password, User_Name) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $hashed, $fullName);

        if ($stmt->execute()) {
            echo "<script>
                    alert('Account created successfully');
                    window.location.href='signin.php';
                  </script>";
            exit();
        } else {
            $error = "Something went wrong";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Sign Up — SAII Hajj Transport</title>
  <link rel="stylesheet" href="styles.css"/>
</head>
<body>

<div class="auth-page">

<!-- ── Header ── -->
<header style="position:sticky;top:0;z-index:200;background:#F0ECE6;border-bottom:1px solid var(--border);box-shadow:0 4px 16px rgba(0,0,0,.1)">
  <div style="max-width:1152px;margin:0 auto;padding:0 1.5rem;height:64px;display:flex;align-items:center;justify-content:space-between">
      <a href="index.php" class="nav-logo">
        <img src="image/logo.png" alt="SAII Logo" class="saii-img"/>
      </a>
  </div>
</header>

<!-- ── Card ── -->
<div class="auth-body">
  <div class="auth-card wide">

    <div class="auth-logo-wrap">
      <img src="image/logo.png" alt="SAII Logo"/>
    </div>

    <h1>Create Your Account</h1>
    <p class="auth-subtitle">Join SAII Hajj Transport today</p>

    <?php if(isset($error)): ?>
      <script>
        alert("<?php echo $error; ?>");
      </script>
    <?php endif; ?>

    <form method="POST" onsubmit="return validateSignup()">

      <!-- First + Last Name -->
      <div class="name-row">
        <div class="form-group">
          <label class="form-label">First Name:</label>
          <input class="form-input" type="text" name="fname" id="su-fname" placeholder="Enter first name" required>
        </div>

        <div class="form-group">
          <label class="form-label">Last Name:</label>
          <input class="form-input" type="text" name="lname" id="su-lname" placeholder="Enter last name" required>
        </div>
      </div>

      <!-- Email -->
      <div class="form-group">
        <label class="form-label">Email Address:</label>
        <input class="form-input" type="email" name="email" id="su-email" placeholder="example@email.com" required>
      </div>

      <!-- Password -->
      <div class="form-group">
        <label class="form-label">Password:</label>
        <input class="form-input" type="password" name="password" id="su-pass" placeholder="Min. 8 characters"
               oninput="checkStrength()" required>

        <!-- Strength bar -->
        <div class="pw-strength-wrap" id="pw-strength">
          <div class="pw-bar"></div><div class="pw-bar"></div>
          <div class="pw-bar"></div><div class="pw-bar"></div>
          <span class="pw-label" id="pw-label"></span>
        </div>
      </div>

      <!-- Confirm -->
      <div class="form-group">
        <label class="form-label">Confirm Password:</label>
        <input class="form-input" type="password" id="su-confirm" placeholder="Re-enter password" required>
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-auth">
        Create Account
      </button>

    </form>

    <div class="auth-footer centered">
      <span>Already have an account?</span>
      <a href="signin.php" class="link-accent">Sign in</a>
    </div>

  </div>
</div>

<footer class="site-footer">
  <p>© 2026 SAII. All rights reserved.</p>
</footer>

</div>

<div class="toast" id="toast"></div>

<script>
var isStrong = false;

// 🔥 قوة كلمة السر
function checkStrength() {
  var pw = document.getElementById('su-pass').value;
  var wrap = document.getElementById('pw-strength');
  var label = document.getElementById('pw-label');

  if (!pw) {
    wrap.className = 'pw-strength-wrap';
    label.textContent = '';
    isStrong = false;
    return;
  }

  var score = 0;

  if (pw.length >= 8) score++;
  if (/[A-Z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;

  var levels = ['', 'pw-weak', 'pw-fair', 'pw-good', 'pw-strong'];
  var labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];

  wrap.className = 'pw-strength-wrap ' + (levels[score] || 'pw-weak');
  label.textContent = labels[score] || 'Weak';

  isStrong = (score >= 3);
}

// 🔥 تحقق قبل الإرسال
function validateSignup() {

  var pass = document.getElementById('su-pass').value;
  var confirm = document.getElementById('su-confirm').value;

  if (!isStrong) {
    alert("Password is too weak (must include uppercase, number, symbol)");
    return false;
  }

  if (pass !== confirm) {
    alert("Passwords do not match");
    return false;
  }

  return true;
}
</script>

</body>
</html>
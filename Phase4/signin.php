
<?php
session_start();
include("db_connection.php");

// تسجيل الدخول
if ($_SERVER["REQUEST_METHOD"] == "POST") {

  $email = $_POST['email'];
  $pass  = $_POST['password'];

  $sql = "SELECT * FROM user WHERE Email = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    if (password_verify($pass, $user['Password'])) {

      $_SESSION['UserID'] = $user['UserID'];
      $_SESSION['User_Name'] = $user['User_Name'];

      // هل هو admin؟
      $checkAdmin = $conn->prepare("SELECT * FROM admin WHERE UserID=?");
      $checkAdmin->bind_param("i", $user['UserID']);
      $checkAdmin->execute();
      $adminResult = $checkAdmin->get_result();

      if ($adminResult->num_rows > 0) {
        header("Location: admin-dashboard.php");
      } else {
        header("Location: user-dashboard.php");
      }
      exit();

    } else {
      $error = "Incorrect email or password";
    }
  } else {
    $error = "Incorrect email or password";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Sign In — SAII Hajj Transport</title>
  <link rel="stylesheet" href="styles.css"/>
</head>
<body>

<div class="auth-page">

<!-- ── Shared Header ── -->
<header style="position:sticky;top:0;z-index:200;background:#F0ECE6;border-bottom:1px solid var(--border);box-shadow:0 4px 16px rgba(0,0,0,.1)">
  <div style="max-width:1152px;margin:0 auto;padding:0 1.5rem;height:64px;display:flex;align-items:center;">
      <a href="index.php" class="nav-logo">
        <img src="image/logo.png" alt="SAII Logo" class="saii-img"/>
      </a>
  </div>
</header>

<!-- ── Card ── -->
<div class="auth-body">
  <div class="auth-card">

    <div class="auth-logo-wrap">
      <img src="image/logo.png" alt="SAII Logo"/>
    </div>

    <h1>Welcome Back</h1>
    <p class="auth-subtitle">Sign in to your SAII account</p>

    <?php if(isset($error)): ?>
  <script>
    alert("<?php echo $error; ?>");
  </script>
  <?php endif; ?>
    <form method="POST">

      <div class="form-group">
        <label class="form-label">Email Address:</label>
        <div class="input-wrapper">
          <span class="input-icon-left">✉</span>
          <input class="form-input input-with-icon" type="email" name="email"
                 placeholder="example@email.com" required/>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Password:</label>
        <div class="input-wrapper">
          <span class="input-icon-left">🔒</span>
          <input class="form-input input-with-icon" type="password" name="password"placeholder="Enter your password" required/>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-auth">Sign In</button>

    </form>

    <div class="auth-footer">
      <a href="#">Forgot password?</a>
      <a href="signup.php" class="link-accent">Need an account? Sign up</a>
    </div>

  </div>
</div>

<!-- ── Footer ── -->
<footer class="site-footer">
  <p>© 2026 SAII. All rights reserved.</p>
</footer>

</div>

</body>
</html>
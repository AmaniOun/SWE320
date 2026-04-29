<?php
session_start();
include("db_connection.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

  $email = $_POST['email'];
  $newPass = $_POST['new_password'];
  $confirmPass = $_POST['confirm_password'];

  if ($newPass !== $confirmPass) {
    $error = "Passwords do not match";
  } else {

    $sql = "SELECT * FROM user WHERE Email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

      $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);

      $update = $conn->prepare("UPDATE user SET Password = ? WHERE Email = ?");
      $update->bind_param("ss", $hashedPass, $email);

      if ($update->execute()) {
  echo "<script>
    alert('Password updated successfully');
    window.location.href = 'signin.php';
  </script>";
  exit();
  
      } else {
        $error = "Something went wrong. Please try again";
      }

    } else {
      $error = "Email not found";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Forgot Password — SAII Hajj Transport</title>
  <link rel="stylesheet" href="styles.css"/>
</head>
<body>

<div class="auth-page">

<header style="position:sticky;top:0;z-index:200;background:#F0ECE6;border-bottom:1px solid var(--border);box-shadow:0 4px 16px rgba(0,0,0,.1)">
  <div style="max-width:1152px;margin:0 auto;padding:0 1.5rem;height:64px;display:flex;align-items:center;">
      <a href="index.php" class="nav-logo">
        <img src="image/logo.png" alt="SAII Logo" class="saii-img"/>
      </a>
  </div>
</header>

<div class="auth-body">
  <div class="auth-card">

    <div class="auth-logo-wrap">
      <img src="image/logo.png" alt="SAII Logo"/>
    </div>

    <h1>Reset Password</h1>
    <p class="auth-subtitle">Enter your email and new password</p>

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
        <label class="form-label">New Password:</label>
        <div class="input-wrapper">
          <span class="input-icon-left">🔒</span>
          <input class="form-input input-with-icon" type="password" name="new_password"
                 placeholder="Enter new password" required/>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Confirm New Password:</label>
        <div class="input-wrapper">
          <span class="input-icon-left">🔒</span>
          <input class="form-input input-with-icon" type="password" name="confirm_password"
                 placeholder="Confirm new password" required/>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-auth">Reset Password</button>

    </form>

    <div class="auth-footer">
      <a href="signin.php">Back to Sign In</a>
      <a href="signup.php" class="link-accent">Need an account? Sign up</a>
    </div>

  </div>
</div>

<footer class="site-footer">
  <p>© 2026 SAII. All rights reserved.</p>
</footer>

</div>

</body>
</html>
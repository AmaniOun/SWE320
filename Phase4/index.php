<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>SAII | Smart Hajj Transportation</title>
  <link rel="stylesheet" href="styles.css"/>
</head>
<body class="page-wrapper">

<!-- ── NAV ── -->
<header style="position:sticky;top:0;z-index:200;background:#F0ECE6;border-bottom:1px solid var(--border);box-shadow:0 4px 16px rgba(0,0,0,.1)">
  <div style="max-width:1152px;margin:0 auto;padding:0 1.5rem;height:64px;display:flex;align-items:center;justify-content:space-between">
    <div class="nav-logo">
      <img src="image/logo.png" alt="SAII Logo" class="logo-img"/>
      <span class="logo-text">Smart Hajj Transportation</span>
    </div>

    <?php if(isset($_SESSION['UserID'])): ?>
      <a href="dashboard.php" class="btn btn-accent"><b>Dashboard →</b></a>
    <?php else: ?>
      <a href="signin.php" class="btn btn-accent"><b>Sign In →</b></a>
    <?php endif; ?>

  </div>
</header>

<!-- ── HERO ── -->
<section class="landing-hero">
  <div style="position:relative;max-width:720px;margin:0 auto">
    <div style="display:inline-flex;align-items:center;gap:.5rem;background:hsla(37,45%,61%,.2);color:var(--accent);padding:.35rem 1rem;border-radius:999px;font-size:.85rem;font-weight:600;margin-bottom:1.5rem">
      Hajj Transportation Platform
    </div>
    <h1 style="font-size:clamp(2rem,6vw,3.5rem);font-weight:900;color:#fff;line-height:1.1;margin-bottom:1rem">
      Travel Smarter<br/><span style="color:var(--accent)">During Hajj</span>
    </h1>
    <p style="color:rgba(255,255,255,.6);font-size:1.1rem;max-width:520px;margin:0 auto 2.5rem">
      Book buses, track congestion, and board with QR codes — all in one place.
    </p>
    <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
      <a href="signin.php" class="btn btn-accent btn-lg"><b>Get Started →</b></a>
    </div>
  </div>
</section>

<!-- ── STATS ── -->
<section class="landing-stats">
  <div style="max-width:640px;margin:0 auto;display:grid;grid-template-columns:repeat(3,1fr);gap:2rem;text-align:center">
    <div><p style="font-size:2rem;font-weight:800;color:var(--accent)">1.8M+</p><p class="text-muted text-sm">Pilgrims Annually</p></div>
    <div><p style="font-size:2rem;font-weight:800;color:var(--accent)">6</p><p class="text-muted text-sm">Holy Locations</p></div>
    <div><p style="font-size:2rem;font-weight:800;color:var(--accent)">QR</p><p class="text-muted text-sm">Instant Boarding</p></div>
  </div>
</section>

<!-- ── CTA ── -->
<section style="text-align:center;padding:4rem 1rem">
  <h2 style="font-size:2rem;font-weight:800;margin-bottom:.75rem">Ready to Travel Smarter?</h2>
  <p class="text-muted" style="margin-bottom:2rem">Sign in as a User or Admin to access your dashboard.</p>
  <a href="signin.php" class="btn btn-accent btn-lg"><b>Sign In Now →</b></a>
</section>

<!-- ── FOOTER ── -->
<footer class="site-footer">
  <p>© 2026 SAII. All rights reserved.</p>
</footer>

</body>
</html>
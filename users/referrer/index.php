<?php
require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/auth.php';
portal_require_role(['referrer']);
$user = portal_current_user();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Referrer Dashboard | Dakshayani Enterprises</title>
    <link rel="icon" href="/images/favicon.ico" />
    <link rel="stylesheet" href="/style.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
  </head>
  <body>
    <header class="site-header"></header>
    <main>
      <section class="section" style="padding:3rem 0 2rem;">
        <div class="container">
          <div class="head">
            <span class="badge">Dashboard</span>
            <h1>Hello, <?php echo htmlspecialchars($user['name'] ?? 'Referrer'); ?></h1>
            <p class="sub">Referrer workspace (empty state). Features coming soon.</p>
          </div>

          <div class="card" style="padding:1.5rem; margin-top:1rem;">
            <p>We will add lead submissions, payout tracking, and status.</p>
          </div>

          <div style="margin-top:1rem;">
            <a href="<?php echo htmlspecialchars(portal_url('logout.php')); ?>" class="btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
          </div>
        </div>
      </section>
    </main>
    <footer class="site-footer"></footer>
    <script src="/script.js" defer></script>
  </body>
  </html>

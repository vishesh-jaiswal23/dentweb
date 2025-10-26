<?php
require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/auth.php';
portal_require_role(['admin']);
$user = portal_current_user();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard | Dakshayani Enterprises</title>
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
            <h1>Welcome, <?php echo htmlspecialchars($user['name'] ?? 'Admin'); ?></h1>
            <p class="sub">This is your admin workspace. We will build features here.</p>
          </div>

          <div class="card" style="padding:1.5rem; margin-top:1rem;">
            <h2 style="margin-top:0;">Quick actions</h2>
            <ul class="footer-links" style="margin-top:0.5rem;">
              <li><a href="#">Create employee</a></li>
              <li><a href="#">Create installer</a></li>
              <li><a href="#">Create referrer</a></li>
              <li><a href="#">Create customer</a></li>
            </ul>
            <p class="text-sm" style="color:var(--base-500);">(Placeholders — to be implemented)</p>
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

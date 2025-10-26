<?php
require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/auth.php';
require_once __DIR__ . '/../common/dashboard.php';

portal_require_role(['customer']);
$user = portal_current_user();
$config = portal_dashboard_config('customer', $user ?? []);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Customer Dashboard | Dakshayani Enterprises</title>
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
      <?php portal_render_dashboard($config); ?>
    </main>
    <footer class="site-footer"></footer>
    <script src="/script.js" defer></script>
    <script src="/users/common/dashboard.js" defer></script>
  </body>
</html>

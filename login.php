<?php
require_once __DIR__ . '/users/common/config.php';
require_once __DIR__ . '/users/common/auth.php';

$error = '';

// If already logged in, send to their dashboard
if (portal_is_logged_in()) {
    $role = portal_current_user()['role'] ?? 'admin';
    header('Location: ' . portal_dashboard_for_role($role));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attemptRecorded = false;
    try {
        if (portal_login_throttle_enabled()) {
            throw new RuntimeException('Too many failed attempts. Please wait a few minutes before trying again.');
        }

        portal_verify_csrf($_POST['csrf_token'] ?? '');

        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }

        if ($email === strtolower(PORTAL_ADMIN_EMAIL) && portal_verify_admin_password($password)) {
            portal_login_user(PORTAL_ADMIN_EMAIL, 'admin', PORTAL_ADMIN_NAME);
            portal_record_login_attempt(true);
            header('Location: ' . portal_dashboard_for_role('admin'));
            exit;
        }

        portal_record_login_attempt(false);
        $attemptRecorded = true;
        throw new RuntimeException('Invalid credentials. Please try again.');
    } catch (Throwable $th) {
        if (!$attemptRecorded) {
            portal_record_login_attempt(false);
        }
        $error = $th->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dakshayani Enterprises Portal Login</title>
    <meta name="description" content="Login to Dakshayani Enterprises portal." />
    <link rel="icon" href="images/favicon.ico" />
    <link rel="stylesheet" href="style.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
  </head>
  <body>
    <header class="site-header"></header>

    <main>
      <section class="section" style="padding:6rem 0 4rem;">
        <div class="container" style="max-width:720px;">
          <div class="card" style="padding:2rem 2rem 2.5rem;">
            <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1rem;">
              <span class="badge">Portal</span>
              <h1 style="margin:0; font-size:1.5rem;">Sign in</h1>
            </div>
            <p class="lead" style="color:var(--base-600); margin-bottom:1.25rem;">
              Enter your credentials to access your dashboard.
            </p>

            <?php if (!empty($error)): ?>
              <div class="alert alert-danger" role="alert" style="margin-bottom:1rem;">
                <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
              </div>
            <?php endif; ?>

            <form method="post" action="<?php echo htmlspecialchars(portal_url('login.php')); ?>" class="form" autocomplete="on" style="display:grid; gap:1rem;">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(portal_csrf_token()); ?>" />
              <label>
                <span class="text-sm" style="display:block; color:var(--base-600);">Email</span>
                <input type="email" name="email" required placeholder="you@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" />
              </label>
              <label>
                <span class="text-sm" style="display:block; color:var(--base-600);">Password</span>
                <input type="password" name="password" required placeholder="••••••••" />
              </label>
              <?php if (portal_login_throttle_enabled()): ?>
                <p class="text-xs" style="color:var(--base-500);">Login temporarily locked due to multiple failed attempts.</p>
              <?php else: ?>
              <button type="submit" class="btn btn-primary" style="justify-self:start;">
                <i class="fa-solid fa-right-to-bracket"></i> Sign in
              </button>
              <p class="text-xs" style="color:var(--base-500);">Attempts remaining: <?php echo portal_login_attempts_remaining(); ?></p>
              <?php endif; ?>
            </form>

            <div style="margin-top:1.5rem; color:var(--base-500);">
              <p class="text-sm">
                Admin email: <code><?php echo htmlspecialchars(PORTAL_ADMIN_EMAIL); ?></code>
              </p>
              <p class="text-xs">Other users (employee, installer, referrer, customer) are created by admin.</p>
            </div>

            <div style="display:flex; flex-wrap:wrap; gap:1rem; margin-top:1.5rem;">
              <a href="index.html" class="btn btn-secondary">
                <i class="fa-solid fa-house"></i>
                Back to home
              </a>
              <a href="contact.html" class="btn">
                <i class="fa-solid fa-envelope"></i>
                Contact support
              </a>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer class="site-footer"></footer>

    <script src="script.js" defer></script>
  </body>
  </html>

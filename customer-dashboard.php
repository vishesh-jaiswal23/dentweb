<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_role('customer');
$user = current_user();

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if ($scriptDir === '/' || $scriptDir === '.') {
    $scriptDir = '';
}
$basePath = rtrim($scriptDir, '/');
$prefix = $basePath === '' ? '' : $basePath;
$pathFor = static function (string $path) use ($prefix): string {
    $clean = ltrim($path, '/');
    return ($prefix === '' ? '' : $prefix) . '/' . $clean;
};

$logoutUrl = $pathFor('logout.php');
$customerName = trim((string) ($user['full_name'] ?? 'Customer'));
if ($customerName === '') {
    $customerName = 'Customer';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Customer Workspace | Dakshayani Enterprises</title>
  <meta name="description" content="Secure customer workspace for Dakshayani Enterprises." />
  <link rel="icon" href="images/favicon.ico" />
  <link rel="stylesheet" href="style.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&display=swap"
    rel="stylesheet"
  />
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
  />
</head>
<body>
  <main class="dashboard">
    <div class="container dashboard-shell">
      <div class="dashboard-auth-bar" role="banner">
        <div class="dashboard-auth-user">
          <i class="fa-solid fa-house-signal" aria-hidden="true"></i>
          <div>
            <small>Signed in as</small>
            <strong><?= htmlspecialchars($customerName, ENT_QUOTES) ?> · Customer</strong>
          </div>
        </div>
        <div class="dashboard-auth-actions">
          <a href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES) ?>" class="btn btn-ghost">
            <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
            Log out
          </a>
        </div>
      </div>

      <section class="section dashboard-placeholder">
        <div class="dashboard-inner">
          <span class="hero-eyebrow"><i class="fa-solid fa-house-signal"></i> Customer workspace</span>
          <h1>Customer portal coming soon</h1>
          <p class="lead">
            Track subsidy documentation, installation progress, and service tickets in one place.
            Your customer hub will launch soon and stays protected behind secure authentication.
          </p>
          <a href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES) ?>" class="btn btn-secondary">Back to login portal</a>
        </div>
      </section>
    </div>
  </main>
</body>
</html>

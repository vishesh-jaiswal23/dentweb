<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_admin();

$admin = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>AI Studio | Admin</title>
  <link rel="stylesheet" href="style.css" />
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
  />
</head>
<body class="admin-ai" data-theme="light">
  <main class="admin-ai__shell">
    <header class="admin-ai__header">
      <div>
        <p class="admin-ai__subtitle">Admin workspace</p>
        <h1 class="admin-ai__title">AI Studio</h1>
        <p class="admin-ai__meta">Signed in as <strong><?= htmlspecialchars($admin['full_name'] ?? 'Administrator', ENT_QUOTES) ?></strong></p>
      </div>
      <div class="admin-ai__actions">
        <a href="admin-dashboard.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to overview</a>
        <a href="logout.php" class="btn btn-primary"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Log out</a>
      </div>
    </header>
    <section class="admin-panel ai-panel">
      <div class="admin-panel__header ai-panel__header">
        <div>
          <h2>Coming Soon</h2>
          <p>The AI Studio is currently under construction. Please check back later.</p>
        </div>
      </div>
    </section>
  </main>
</body>
</html>

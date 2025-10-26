<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="icon" href="<?php echo htmlspecialchars(portal_url('images/favicon.ico')); ?>" />
    <link rel="stylesheet" href="<?php echo htmlspecialchars(portal_url('style.css')); ?>" />
    <link rel="stylesheet" href="<?php echo htmlspecialchars(portal_url('users/admin/admin.css')); ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <?php if (!empty($extraStyles)): ?>
      <?php foreach ($extraStyles as $href): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($href); ?>" />
      <?php endforeach; ?>
    <?php endif; ?>
  </head>
  <body class="admin-body">
    <div class="admin-shell" data-active-nav="<?php echo htmlspecialchars($active); ?>">
      <aside class="admin-nav" aria-label="Admin navigation">
        <div class="admin-brand">
          <span class="admin-brand__logo"><i class="fa-solid fa-solar-panel"></i></span>
          <div>
            <p class="admin-brand__name">Dakshayani Admin</p>
            <p class="admin-brand__meta">Role: <?php echo htmlspecialchars(ucwords($user['role'] ?? 'admin')); ?></p>
          </div>
        </div>
        <nav>
          <ul>
            <?php foreach ($navItems as $item): ?>
              <li class="<?php echo $item['id'] === $active ? 'is-active' : ''; ?>" data-capability="<?php echo htmlspecialchars($item['cap']); ?>">
                <a href="<?php echo htmlspecialchars(portal_url('users/admin/' . $item['href'])); ?>" class="admin-nav__link">
                  <i class="fa-solid <?php echo htmlspecialchars($item['icon']); ?>"></i>
                  <span><?php echo htmlspecialchars($item['label']); ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </nav>
        <div class="admin-nav__footer">
          <form method="post" action="<?php echo htmlspecialchars(portal_url('logout.php')); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(portal_csrf_token()); ?>" />
            <button type="submit" class="btn btn-secondary btn-ghost" aria-label="Log out">
              <i class="fa-solid fa-arrow-right-from-bracket"></i>
              <span>Log out</span>
            </button>
          </form>
        </div>
      </aside>
      <div class="admin-main">
        <header class="admin-header">
          <button class="admin-nav-toggle" type="button" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars"></i>
          </button>
          <form class="admin-search" role="search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" placeholder="Search modules, records, commands" aria-label="Universal search" />
            <span class="admin-search__shortcut">⌘K</span>
          </form>
          <div class="admin-header__actions">
            <?php if (!empty($canSeeNotifications)): ?>
              <div class="admin-header__notifications" data-component="notification-center">
                <button type="button" class="btn btn-icon" aria-label="Open notifications" data-action="toggle-notifications">
                  <i class="fa-regular fa-bell"></i>
                  <?php if (!empty($notifications)): ?>
                    <span class="badge badge-dot"></span>
                  <?php endif; ?>
                </button>
                <?php portal_admin_render_notifications($notifications); ?>
              </div>
            <?php endif; ?>
            <div class="admin-header__profile">
              <button class="btn btn-profile" type="button" data-action="toggle-profile" aria-expanded="false">
                <span class="avatar" aria-hidden="true"><?php echo strtoupper(substr($userName, 0, 1)); ?></span>
                <span class="admin-profile__meta">
                  <strong><?php echo $userName; ?></strong>
                  <span><?php echo $maskedEmail; ?></span>
                </span>
                <i class="fa-solid fa-chevron-down"></i>
              </button>
              <div class="admin-profile__menu" role="menu">
                <a href="#profile" class="admin-profile__menu-item" role="menuitem">Profile & security</a>
                <a href="mailto:support@dakshayani.in" class="admin-profile__menu-item" role="menuitem">Contact support</a>
              </div>
            </div>
          </div>
        </header>
        <main class="admin-content">

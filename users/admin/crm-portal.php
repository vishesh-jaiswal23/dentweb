<?php
require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/auth.php';
require_once __DIR__ . '/layout.php';

$user = portal_require_capability_for_page('service.crm.access', 'crm-portal');

portal_admin_shell_open('CRM Workspace | Dakshayani Enterprises', 'crm-portal', $user, [], [
    'styles' => ['crm.css'],
    'scripts' => ['crm.js'],
]);
?>
      <section class="admin-section">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
          <a href="<?php echo htmlspecialchars(portal_url('users/admin/index.php')); ?>">Admin</a>
          <span class="admin-breadcrumb__sep" aria-hidden="true">/</span>
          <span>CRM workspace</span>
        </nav>
        <div class="portal-embedded-app portal-embedded-app--crm">
          <?php include __DIR__ . '/partials/crm-portal-body.php'; ?>
        </div>
      </section>
<?php
portal_admin_shell_close();
?>

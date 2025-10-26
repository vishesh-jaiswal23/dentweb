<?php
require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/auth.php';
require_once __DIR__ . '/layout.php';

$user = portal_require_capability_for_page('service.workflow.access', 'service-workflow');

portal_admin_shell_open('Service Workflow | Dakshayani Enterprises', 'service-workflow', $user, [], [
    'styles' => ['workflow.css'],
    'scripts' => ['workflow.js'],
]);
?>
      <section class="admin-section">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
          <a href="<?php echo htmlspecialchars(portal_url('users/admin/index.php')); ?>">Admin</a>
          <span class="admin-breadcrumb__sep" aria-hidden="true">/</span>
          <span>Service workflow</span>
        </nav>
        <div class="portal-embedded-app portal-embedded-app--workflow">
          <?php include __DIR__ . '/partials/service-workflow-body.php'; ?>
        </div>
      </section>
<?php
portal_admin_shell_close();
?>

<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_admin();

$db = get_db();
$admin = current_user();
$csrfToken = $_SESSION['csrf_token'] ?? '';

$flashData = consume_flash();
$flashMessage = '';
$flashTone = 'info';
$flashIcons = [
    'success' => 'fa-circle-check',
    'warning' => 'fa-triangle-exclamation',
    'error' => 'fa-circle-exclamation',
    'info' => 'fa-circle-info',
];
$flashIcon = $flashIcons[$flashTone];

if (is_array($flashData)) {
    if (isset($flashData['message']) && is_string($flashData['message'])) {
        $flashMessage = trim($flashData['message']);
    }
    if (isset($flashData['type']) && is_string($flashData['type'])) {
        $candidateTone = strtolower($flashData['type']);
        if (isset($flashIcons[$candidateTone])) {
            $flashTone = $candidateTone;
            $flashIcon = $flashIcons[$candidateTone];
        }
    }
}

$statusFilter = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$validStatuses = ['all', 'active', 'inactive', 'pending'];
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token(is_string($token) ? $token : null)) {
        set_flash('error', 'Your session expired. Please try again.');
        header('Location: admin-users.php?status=' . urlencode($statusFilter));
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');
    $actorId = (int) ($admin['id'] ?? 0);

    try {
        switch ($action) {
            case 'create_user':
                $payload = [
                    'full_name' => $_POST['full_name'] ?? '',
                    'email' => $_POST['email'] ?? '',
                    'username' => $_POST['username'] ?? '',
                    'password' => $_POST['password'] ?? '',
                    'role' => $_POST['role'] ?? 'employee',
                    'status' => $_POST['status'] ?? 'active',
                    'permissions_note' => $_POST['permissions_note'] ?? '',
                ];
                admin_create_user($db, $payload, $actorId);
                set_flash('success', 'User account created successfully.');
                break;
            case 'update_status':
                $userId = (int) ($_POST['user_id'] ?? 0);
                $targetStatus = (string) ($_POST['target_status'] ?? '');
                if ($userId <= 0) {
                    throw new RuntimeException('Invalid user reference.');
                }
                admin_update_user_status($db, $userId, $targetStatus, $actorId);
                set_flash('success', 'Account status updated.');
                break;
            case 'reset_password':
                $userId = (int) ($_POST['user_id'] ?? 0);
                $newPassword = (string) ($_POST['new_password'] ?? '');
                if ($userId <= 0) {
                    throw new RuntimeException('Invalid user reference.');
                }
                admin_reset_user_password($db, $userId, $newPassword, $actorId);
                set_flash('success', 'Password reset successfully.');
                break;
            default:
                throw new RuntimeException('Unsupported action.');
        }
    } catch (Throwable $exception) {
        set_flash('error', $exception->getMessage());
    }

    header('Location: admin-users.php?status=' . urlencode($statusFilter));
    exit;
}

$accounts = admin_list_accounts($db, ['status' => 'all']);
$filteredAccounts = $statusFilter === 'all'
    ? $accounts
    : array_values(array_filter($accounts, static fn (array $account): bool => strtolower((string) ($account['status'] ?? '')) === $statusFilter));

$totalUsers = count($accounts);
$activeUsers = count(array_filter($accounts, static fn (array $account): bool => strtolower((string) ($account['status'] ?? '')) === 'active'));
$pendingUsers = count(array_filter($accounts, static fn (array $account): bool => strtolower((string) ($account['status'] ?? '')) === 'pending'));
$inactiveUsers = count(array_filter($accounts, static fn (array $account): bool => strtolower((string) ($account['status'] ?? '')) === 'inactive'));

$roleBreakdown = [];
foreach ($accounts as $account) {
    $roleKey = strtolower((string) ($account['role'] ?? '')); 
    if ($roleKey === '') {
        $roleKey = 'unknown';
    }
    $roleBreakdown[$roleKey] = ($roleBreakdown[$roleKey] ?? 0) + 1;
}

ksort($roleBreakdown);

function admin_users_format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }
    return date('d M Y · H:i', $timestamp);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>User Management | Dakshayani Enterprises</title>
  <meta name="description" content="Administer Dentweb user accounts, access levels, and password resets." />
  <link rel="icon" href="images/favicon.ico" />
  <link rel="stylesheet" href="style.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap"
    rel="stylesheet"
  />
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
  />
</head>
<body class="admin-records" data-theme="light">
  <main class="admin-records__shell">
    <?php if ($flashMessage !== ''): ?>
    <div class="admin-alert admin-alert--<?= htmlspecialchars($flashTone, ENT_QUOTES) ?>" role="status" aria-live="polite">
      <i class="fa-solid <?= htmlspecialchars($flashIcon, ENT_QUOTES) ?>" aria-hidden="true"></i>
      <span><?= htmlspecialchars($flashMessage, ENT_QUOTES) ?></span>
    </div>
    <?php endif; ?>

    <header class="admin-records__header">
      <div>
        <h1>User Management</h1>
        <p class="admin-muted">Provision new accounts, adjust access, and handle password resets from a single console.</p>
      </div>
      <div class="admin-records__meta">
        <a class="admin-link" href="admin-dashboard.php"><i class="fa-solid fa-gauge-high"></i> Back to overview</a>
      </div>
    </header>

    <section class="admin-overview__cards admin-overview__cards--compact">
      <article class="admin-overview__card">
        <h2>Total accounts</h2>
        <p><?= $totalUsers ?></p>
      </article>
      <article class="admin-overview__card">
        <h2>Active</h2>
        <p><?= $activeUsers ?></p>
      </article>
      <article class="admin-overview__card">
        <h2>Pending</h2>
        <p><?= $pendingUsers ?></p>
      </article>
      <article class="admin-overview__card">
        <h2>Inactive</h2>
        <p><?= $inactiveUsers ?></p>
      </article>
    </section>

    <section class="admin-users__roles">
      <h2>Role distribution</h2>
      <ul class="admin-users__role-list">
        <?php foreach ($roleBreakdown as $role => $count): ?>
        <li><strong><?= htmlspecialchars(ucfirst($role), ENT_QUOTES) ?>:</strong> <?= $count ?></li>
        <?php endforeach; ?>
      </ul>
    </section>

    <section class="admin-users__create">
      <h2>Create account</h2>
      <form method="post" class="admin-form admin-form--stacked">
        <input type="hidden" name="action" value="create_user" />
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
        <div class="admin-form__grid">
          <label>
            <span>Full name</span>
            <input type="text" name="full_name" required placeholder="Employee name" />
          </label>
          <label>
            <span>Email</span>
            <input type="email" name="email" required placeholder="name@example.com" />
          </label>
        </div>
        <div class="admin-form__grid">
          <label>
            <span>Username</span>
            <input type="text" name="username" required placeholder="username" />
          </label>
          <label>
            <span>Initial password</span>
            <input type="password" name="password" required minlength="8" />
          </label>
        </div>
        <div class="admin-form__grid">
          <label>
            <span>Role</span>
            <select name="role" required>
              <option value="employee">Employee</option>
              <option value="installer">Installer</option>
              <option value="referrer">Referrer</option>
              <option value="customer">Customer</option>
              <option value="admin">Admin</option>
            </select>
          </label>
          <label>
            <span>Status</span>
            <select name="status">
              <option value="active">Active</option>
              <option value="pending">Pending</option>
              <option value="inactive">Inactive</option>
            </select>
          </label>
        </div>
        <label>
          <span>Permissions note (optional)</span>
          <textarea name="permissions_note" rows="2" placeholder="Scope, region, or restrictions"></textarea>
        </label>
        <button type="submit" class="btn btn-primary">Create user</button>
      </form>
    </section>

    <section class="admin-records__filter">
      <form method="get" class="admin-filter-form">
        <label>
          Status
          <select name="status" onchange="this.form.submit()">
            <?php foreach ($validStatuses as $statusOption): ?>
            <option value="<?= htmlspecialchars($statusOption, ENT_QUOTES) ?>" <?= $statusFilter === $statusOption ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($statusOption), ENT_QUOTES) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </form>
    </section>

    <div class="admin-table-wrapper">
      <table class="admin-table">
        <thead>
          <tr>
            <th scope="col">Name</th>
            <th scope="col">Role</th>
            <th scope="col">Email</th>
            <th scope="col">Username</th>
            <th scope="col">Status</th>
            <th scope="col">Created</th>
            <th scope="col">Last login</th>
            <th scope="col" class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($filteredAccounts)): ?>
          <tr>
            <td colspan="8" class="admin-records__empty">No users match this filter.</td>
          </tr>
          <?php else: ?>
          <?php foreach ($filteredAccounts as $account): ?>
          <?php
          $accountId = (int) ($account['id'] ?? 0);
          $status = strtolower((string) ($account['status'] ?? 'pending'));
          $nextStatus = $status === 'active' ? 'inactive' : 'active';
          ?>
          <tr>
            <td><?= htmlspecialchars($account['full_name'] ?? '—', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars(ucfirst((string) ($account['role'] ?? 'Unknown')), ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($account['email'] ?? '—', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($account['username'] ?? '—', ENT_QUOTES) ?></td>
            <td><span class="dashboard-status dashboard-status--<?= htmlspecialchars($status, ENT_QUOTES) ?>"><?= htmlspecialchars(ucfirst($status), ENT_QUOTES) ?></span></td>
            <td><?= htmlspecialchars(admin_users_format_datetime($account['created_at'] ?? null), ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars(admin_users_format_datetime($account['last_login_at'] ?? null), ENT_QUOTES) ?></td>
            <td class="admin-table__actions">
              <form method="post" class="admin-inline-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
                <input type="hidden" name="action" value="update_status" />
                <input type="hidden" name="user_id" value="<?= $accountId ?>" />
                <input type="hidden" name="target_status" value="<?= htmlspecialchars($nextStatus, ENT_QUOTES) ?>" />
                <button type="submit" class="btn btn-ghost btn-xs">
                  <?= $status === 'active' ? 'Deactivate' : 'Activate' ?>
                </button>
              </form>
              <form method="post" class="admin-inline-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
                <input type="hidden" name="action" value="reset_password" />
                <input type="hidden" name="user_id" value="<?= $accountId ?>" />
                <label class="admin-inline-form__field">
                  <span class="sr-only">New password</span>
                  <input type="password" name="new_password" placeholder="New password" minlength="8" required />
                </label>
                <button type="submit" class="btn btn-secondary btn-xs">Reset</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>

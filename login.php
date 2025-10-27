<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

if (!isset($bootstrapError) || !is_string($bootstrapError)) {
    $bootstrapError = '';
}

/**
 * Resolve the best support email without triggering fatals on undefined constants.
 */
$resolveSupportEmail = static function (): string {
    $supportEmail = '';

    $constants = get_defined_constants(true);
    if (isset($constants['user']['ADMIN_EMAIL']) && is_string($constants['user']['ADMIN_EMAIL'])) {
        $candidate = trim($constants['user']['ADMIN_EMAIL']);
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            return $candidate;
        }
    }

    $emailCandidates = [
        $_ENV['ADMIN_EMAIL'] ?? null,
        $_SERVER['ADMIN_EMAIL'] ?? null,
        getenv('ADMIN_EMAIL') ?: null,
    ];

    foreach ($emailCandidates as $candidate) {
        if (!is_string($candidate)) {
            continue;
        }

        $candidate = trim($candidate);
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            $supportEmail = $candidate;
            break;
        }
    }

    if ($supportEmail === '') {
        $supportEmail = 'support@dakshayani.in';
    }

    if (!defined('ADMIN_EMAIL')) {
        define('ADMIN_EMAIL', $supportEmail);
    }

    return $supportEmail;
};

$supportEmail = $resolveSupportEmail();

start_session();

$scriptDir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
if ($scriptDir === '/' || $scriptDir === '.') {
    $scriptDir = '';
}
$basePath = rtrim($scriptDir, '/');
$prefix = $basePath === '' ? '' : $basePath;
$routeFor = static function (string $path) use ($prefix): string {
    $clean = ltrim($path, '/');
    return ($prefix === '' ? '' : $prefix) . '/' . $clean;
};

$roleRoutes = [
    'admin' => $routeFor('admin-dashboard.php'),
    'employee' => $routeFor('employee-dashboard.php'),
];

$error = $bootstrapError;
$success = '';

$selectedRole = 'admin';
$submittedRole = isset($_POST['role']) && is_string($_POST['role']) ? trim($_POST['role']) : '';
$isRoleValid = true;
if ($submittedRole !== '') {
    if (array_key_exists($submittedRole, $roleRoutes)) {
        $selectedRole = $submittedRole;
    } else {
        $isRoleValid = false;
    }
}

$emailValue = '';
if (isset($_POST['email']) && is_string($_POST['email'])) {
    $emailValue = trim($_POST['email']);
}

$passwordValue = '';
if (isset($_POST['password']) && is_string($_POST['password'])) {
    $passwordValue = (string) $_POST['password'];
}

if (!empty($_SESSION['offline_session_invalidated'])) {
    $error = 'Your emergency administrator session ended because the secure database is available again. Please sign in using your standard credentials.';
    unset($_SESSION['offline_session_invalidated']);
}

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($requestMethod === 'POST') {
    if ($bootstrapError !== '') {
        $error = $bootstrapError;
    } else {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($csrfToken)) {
            $error = 'Your session expired. Please refresh and try again.';
        } elseif (!$isRoleValid) {
            $error = 'The selected portal is not available. Please choose a valid option and try again.';
        } else {
            $email = $emailValue;
            $password = $passwordValue;
            $user = null;
            try {
                $user = authenticate_user($email, $password, $selectedRole);
            } catch (Throwable $exception) {
                $error = 'Error: The login service is temporarily unavailable because the server cannot access its secure database. Please contact support.';
                if ($supportEmail !== '') {
                    $error .= ' Reach out to ' . $supportEmail . ' for assistance.';
                }
                error_log('Login attempt failed: ' . $exception->getMessage());
                $user = null;
            }

            if (empty($error)) {
                if (!$user) {
                    $error = 'The provided credentials were incorrect or the account is inactive.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'full_name' => $user['full_name'],
                        'email' => $user['email'],
                        'username' => $user['username'] ?? $user['email'],
                        'role_name' => $user['role_name'],
                        'offline_mode' => !empty($user['offline_mode']),
                    ];
                    $success = 'Login successful. Redirecting…';
                    header('Location: ' . $roleRoutes[$selectedRole]);
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login Portal | Dakshayani Enterprises</title>
  <meta
    name="description"
    content="Securely access the Dakshayani Enterprises portals for administrators and employees with unified security controls."
  />
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
<body data-active-nav="login">
  <header class="site-header"></header>

  <main>
    <section class="page-hero login-hero">
      <div class="container hero-inner">
        <div class="hero-copy">
          <span class="hero-eyebrow"><i class="fa-solid fa-lock"></i> Secure access</span>
          <h1>Login to your Dakshayani workspace</h1>
          <p class="lead" style="color: rgba(255, 255, 255, 0.85); max-width: 32rem;">
            Choose your portal to manage approvals, monitor service tickets, or stay updated with assignments.
            Dedicated workspaces for administrators and employees are ready for you.
          </p>
        </div>
        <div class="hero-art">
          <img src="images/illustrations/login-portal.svg" alt="Login illustration" onerror="this.remove();" />
        </div>
      </div>
    </section>

    <section class="section login-section">
      <div class="container login-layout">
        <div class="login-panel" aria-labelledby="portal-login-title">
          <h2 id="portal-login-title">Access your portal</h2>
          <p class="text-sm">
            Select the portal you need to access and enter your credentials. Administrators approve every employee account
            before it becomes active, and only authorised users may sign in.
          </p>

          <form
            id="login-form"
            class="login-form"
            method="post"
            action="<?= htmlspecialchars($_SERVER['PHP_SELF'] ?? 'login.php', ENT_QUOTES) ?>"
            novalidate
          >
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>" />
            <fieldset class="role-options">
              <legend>Portal type</legend>
              <?php foreach ($roleRoutes as $role => $route): ?>
              <label class="role-option">
                <input type="radio" name="role" value="<?= htmlspecialchars($role) ?>" <?= $selectedRole === $role ? 'checked' : '' ?> />
                <span>
                  <strong><?= ucfirst($role) ?></strong>
                  <small>
                    <?php switch ($role) {
                        case 'admin':
                            echo 'Manage operations, policies, approvals, and user permissions.';
                            break;
                        case 'employee':
                            echo 'Access assignments, documents, and service updates authorised by Admin.';
                            break;
                        default:
                            echo 'Use the credentials assigned to you to sign in securely.';
                    } ?>
                  </small>
                </span>
              </label>
              <?php endforeach; ?>
            </fieldset>

            <div class="form-field">
              <label for="login-email">Email ID</label>
              <input type="email" id="login-email" name="email" placeholder="you@example.com" autocomplete="username" required value="<?= htmlspecialchars($emailValue, ENT_QUOTES) ?>" />
            </div>

            <div class="form-field">
              <label for="login-password">Password</label>
              <input
                type="password"
                id="login-password"
                name="password"
                placeholder="Enter your password"
                autocomplete="current-password"
                required
              />
            </div>

            <p class="text-xs login-hint" data-role-hint>
              Use your assigned credentials to access the selected portal.
            </p>

            <button type="submit" class="btn btn-primary btn-block">Login</button>
            <p class="login-feedback <?= $error ? 'is-error' : ($success ? 'is-success' : '') ?>" role="status" aria-live="polite" data-login-feedback>
              <?= htmlspecialchars($error ?: $success, ENT_QUOTES) ?>
            </p>
            <?php if ($supportEmail !== ''): ?>
            <p class="text-xs login-support">Need help? Email <a href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES) ?>"><?= htmlspecialchars($supportEmail, ENT_QUOTES) ?></a>.</p>
            <?php endif; ?>
          </form>
        </div>

        <div class="login-summary" aria-label="Portal quick overview">
          <article class="login-card">
            <h3><i class="fa-solid fa-user-shield"></i> Admin portal</h3>
            <p>Approve subsidies, manage project workflows, and configure policy documents securely.</p>
          </article>
          <article class="login-card">
            <h3><i class="fa-solid fa-house-signal"></i> Customer portal</h3>
            <p>Monitor installation progress, view service tickets, and download essential paperwork.</p>
          </article>
          <article class="login-card">
            <h3><i class="fa-solid fa-helmet-safety"></i> Installer portal</h3>
            <p>Access installation checklists, upload on-site photographs, and record commissioning updates.</p>
          </article>
          <article class="login-card">
            <h3><i class="fa-solid fa-user-tie"></i> Employee portal</h3>
            <p>Stay updated on schedules, safety SOPs, and internal communications.</p>
          </article>
          <article class="login-card">
            <h3><i class="fa-solid fa-handshake-angle"></i> Referrer portal</h3>
            <p>Submit new leads, follow incentive payouts, and collaborate with our sales specialists.</p>
          </article>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer"></footer>

  <script src="login.js" defer></script>
  <script src="script.js" defer></script>
</body>
</html>

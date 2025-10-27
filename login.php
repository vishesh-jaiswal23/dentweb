<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

if (!isset($bootstrapError) || !is_string($bootstrapError)) {
    $bootstrapError = '';
}

$supportEmail = null;
if (defined('ADMIN_EMAIL')) {
    $configuredEmail = constant('ADMIN_EMAIL');
    if (is_string($configuredEmail) && filter_var($configuredEmail, FILTER_VALIDATE_EMAIL)) {
        $supportEmail = $configuredEmail;
    }
}

if ($supportEmail === null) {
    $emailCandidates = [
        $_ENV['ADMIN_EMAIL'] ?? null,
        $_SERVER['ADMIN_EMAIL'] ?? null,
    ];

    $envEmail = getenv('ADMIN_EMAIL');
    if (is_string($envEmail)) {
        $emailCandidates[] = $envEmail;
    }

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

    if ($supportEmail === null) {
        $supportEmail = 'support@dakshayani.in';
    }
}

if (!defined('ADMIN_EMAIL') && is_string($supportEmail) && $supportEmail !== '') {
    define('ADMIN_EMAIL', $supportEmail);
}

$supportEmail = resolve_admin_email();

start_session();

$flashData = consume_flash();
$flashMessage = '';
$flashTone = 'info';
$flashIcons = [
    'success' => 'fa-circle-check',
    'warning' => 'fa-triangle-exclamation',
    'error' => 'fa-circle-exclamation',
    'info' => 'fa-circle-info',
];
$flashIcon = $flashIcons[$flashTone] ?? 'fa-circle-info';
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
$roleLabel = static function (string $role): string {
    $clean = trim(str_replace(['_', '-'], ' ', strtolower($role)));
    return $clean === '' ? 'Selected' : ucwords($clean);
};

$error = $bootstrapError;
$success = '';
$recoveryError = '';
$recoverySuccess = '';
$rateLimitMessage = '';

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

$intent = 'login';
if (isset($_POST['intent']) && is_string($_POST['intent'])) {
    $candidateIntent = strtolower(trim($_POST['intent']));
    if (in_array($candidateIntent, ['login', 'recover'], true)) {
        $intent = $candidateIntent;
    }
}

$validateRecoveryPassword = static function (string $password): ?string {
    if (strlen($password) < 12) {
        return 'Choose a password with at least 12 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Include at least one uppercase letter in the new password.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Include at least one lowercase letter in the new password.';
    }
    if (!preg_match('/\d/', $password)) {
        return 'Include at least one number in the new password.';
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        return 'Include at least one special character in the new password.';
    }

    return null;
};

if (!empty($_SESSION['offline_session_invalidated'])) {
    $error = 'Your emergency administrator session ended because the secure database is available again. Please sign in using your standard credentials.';
    unset($_SESSION['offline_session_invalidated']);
}

$ipAddress = client_ip_address();

$db = null;
$loginPolicy = [
    'retry_limit' => 5,
    'lockout_minutes' => 30,
    'session_timeout' => 45,
];

try {
    $db = get_db();
    $loginPolicy = get_login_policy($db);
} catch (Throwable $dbInitialisationError) {
    $db = null;
}

$recoverySecretConfigured = admin_recovery_secret() !== '';
$recoveryAvailable = $db instanceof PDO ? is_admin_recovery_available($db) : false;

$requestMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
if ($requestMethod === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        if ($intent === 'recover') {
            $recoveryError = 'Your session expired. Please refresh and try again.';
        } else {
            $error = 'Your session expired. Please refresh and try again.';
        }
    } elseif ($bootstrapError !== '') {
        if ($intent === 'recover') {
            $recoveryError = $bootstrapError;
        } else {
            $error = $bootstrapError;
        }
    } elseif ($intent === 'recover') {
        if (!$recoverySecretConfigured) {
            $recoveryError = 'Emergency recovery is disabled on this server.';
        } elseif (!$db instanceof PDO) {
            $recoveryError = 'Emergency recovery requires the secure database to be online. Please try again once the database connection is restored.';
        } elseif (!$recoveryAvailable) {
            $recoveryError = 'Emergency recovery is unavailable because at least one administrator account remains active.';
        } else {
            $secretInput = isset($_POST['recovery_secret']) && is_string($_POST['recovery_secret']) ? trim($_POST['recovery_secret']) : '';
            $newPassword = isset($_POST['recovery_password']) && is_string($_POST['recovery_password']) ? (string) $_POST['recovery_password'] : '';
            $confirmPassword = isset($_POST['recovery_password_confirm']) && is_string($_POST['recovery_password_confirm']) ? (string) $_POST['recovery_password_confirm'] : '';

            if ($secretInput === '') {
                $recoveryError = 'Enter the recovery secret provided in the environment configuration.';
            } elseif (!hash_equals(admin_recovery_secret(), $secretInput)) {
                $recoveryError = 'The recovery secret was incorrect.';
                record_system_audit(
                    $db,
                    'admin_recovery_failed',
                    'security',
                    0,
                    sprintf('Invalid recovery secret attempt from %s', mask_ip_for_log($ipAddress))
                );
            } elseif ($newPassword !== $confirmPassword) {
                $recoveryError = 'The confirmation password did not match.';
            } else {
                $passwordIssue = $validateRecoveryPassword($newPassword);
                if ($passwordIssue !== null) {
                    $recoveryError = $passwordIssue;
                } else {
                    $recoveryEmailCandidates = [
                        $_ENV['ADMIN_RECOVERY_EMAIL'] ?? null,
                        $_SERVER['ADMIN_RECOVERY_EMAIL'] ?? null,
                        getenv('ADMIN_RECOVERY_EMAIL') ?: null,
                    ];
                    $recoveryEmail = 'd.entranchi@gmail.com';
                    foreach ($recoveryEmailCandidates as $candidateEmail) {
                        if (is_string($candidateEmail)) {
                            $candidateEmail = trim($candidateEmail);
                            if ($candidateEmail !== '' && filter_var($candidateEmail, FILTER_VALIDATE_EMAIL)) {
                                $recoveryEmail = $candidateEmail;
                                break;
                            }
                        }
                    }

                    $recoveryNameCandidates = [
                        $_ENV['ADMIN_RECOVERY_NAME'] ?? null,
                        $_SERVER['ADMIN_RECOVERY_NAME'] ?? null,
                        getenv('ADMIN_RECOVERY_NAME') ?: null,
                    ];
                    $recoveryName = 'Primary Administrator';
                    foreach ($recoveryNameCandidates as $candidateName) {
                        if (is_string($candidateName)) {
                            $candidateName = trim($candidateName);
                            if ($candidateName !== '') {
                                $recoveryName = $candidateName;
                                break;
                            }
                        }
                    }

                    $recoveryUsernameCandidates = [
                        $_ENV['ADMIN_RECOVERY_USERNAME'] ?? null,
                        $_SERVER['ADMIN_RECOVERY_USERNAME'] ?? null,
                        getenv('ADMIN_RECOVERY_USERNAME') ?: null,
                    ];
                    $recoveryUsername = 'admin';
                    foreach ($recoveryUsernameCandidates as $candidateUsername) {
                        if (is_string($candidateUsername)) {
                            $candidateUsername = trim($candidateUsername);
                            if ($candidateUsername !== '') {
                                $recoveryUsername = $candidateUsername;
                                break;
                            }
                        }
                    }

                    try {
                        $adminId = perform_admin_recovery(
                            $db,
                            $recoveryEmail,
                            $recoveryUsername,
                            $recoveryName,
                            $newPassword,
                            'Full access (reset ' . now_ist() . ')'
                        );

                        set_setting('admin_recovery_consumed_at', now_ist(), $db);
                        set_setting('admin_recovery_last_ip', mask_ip_for_log($ipAddress), $db);
                        set_setting('admin_recovery_last_email', mask_email_for_log($recoveryEmail), $db);

                        record_system_audit(
                            $db,
                            'admin_recovery_success',
                            'security',
                            $adminId,
                            sprintf('Emergency administrator credentials reset from %s', mask_ip_for_log($ipAddress))
                        );

                        login_rate_limit_register_success($db, $recoveryEmail, $ipAddress);

                        set_flash('success', 'Administrator password reset. Sign in with your new credentials.');
                        header('Location: ' . $routeFor('login.php'));
                        exit;
                    } catch (Throwable $exception) {
                        $recoveryError = 'Unable to reset administrator credentials. Please review the server logs for details.';
                        error_log('Admin recovery failed: ' . $exception->getMessage());
                    }
                }
            }
        }
    } else {
        if (!$isRoleValid) {
            $error = 'The selected portal is not available. Please choose a valid option and try again.';
        } else {
            $email = $emailValue;
            $password = $passwordValue;
            $user = null;

            if ($db instanceof PDO && $email !== '') {
                $rateStatus = login_rate_limit_status($db, $email, $ipAddress, $loginPolicy);
                if ($rateStatus['locked']) {
                    $minutes = max(1, (int) ceil($rateStatus['seconds_until_unlock'] / 60));
                    $error = sprintf('Too many failed login attempts. Please wait %d minute%s before trying again.', $minutes, $minutes === 1 ? '' : 's');
                } elseif ($rateStatus['attempts'] > 0) {
                    $rateLimitMessage = sprintf('Attempts remaining before lockout: %d of %d.', $rateStatus['remaining_attempts'], $loginPolicy['retry_limit']);
                }
            }

            if ($error === '') {
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
            }

            if ($error === '') {
                if (!$user) {
                    $error = 'The provided credentials were incorrect or the account is inactive.';
                    if ($db instanceof PDO && $email !== '') {
                        $lockState = login_rate_limit_register_failure($db, $email, $ipAddress, $loginPolicy);
                        if ($lockState['locked']) {
                            $minutes = max(1, (int) ceil($lockState['seconds_until_unlock'] / 60));
                            $error = sprintf('Too many incorrect attempts. Your login is locked for %d minute%s.', $minutes, $minutes === 1 ? '' : 's');
                        } elseif ($lockState['remaining_attempts'] > 0) {
                            $rateLimitMessage = sprintf('Attempts remaining before lockout: %d of %d.', $lockState['remaining_attempts'], $loginPolicy['retry_limit']);
                        }
                    }
                } else {
                    if ($db instanceof PDO && $email !== '') {
                        login_rate_limit_register_success($db, $email, $ipAddress);
                    }

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

          <?php if ($flashMessage !== ''): ?>
          <div class="portal-flash portal-flash--<?= htmlspecialchars($flashTone, ENT_QUOTES) ?>" role="status" aria-live="polite">
            <i class="fa-solid <?= htmlspecialchars($flashIcon, ENT_QUOTES) ?>" aria-hidden="true"></i>
            <span><?= htmlspecialchars($flashMessage, ENT_QUOTES) ?></span>
          </div>
          <?php endif; ?>

          <form
            id="login-form"
            class="login-form"
            method="post"
            action="<?= htmlspecialchars($_SERVER['PHP_SELF'] ?? 'login.php', ENT_QUOTES) ?>"
            novalidate
          >
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>" />
            <input type="hidden" name="intent" value="login" />
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
            <?php if ($rateLimitMessage !== ''): ?>
            <p class="login-feedback is-warning" aria-live="polite"><?= htmlspecialchars($rateLimitMessage, ENT_QUOTES) ?></p>
            <?php endif; ?>
            <?php if ($supportEmail !== ''): ?>
            <p class="text-xs login-support">Need help? Email <a href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES) ?>"><?= htmlspecialchars($supportEmail, ENT_QUOTES) ?></a>.</p>
            <?php endif; ?>
          </form>

          <?php if ($recoverySecretConfigured): ?>
          <section class="login-recovery" aria-labelledby="admin-recovery-title">
            <h3 id="admin-recovery-title"><i class="fa-solid fa-life-ring"></i> Emergency admin recovery</h3>
            <p class="text-xs">
              Use this option only when no administrator can sign in. Every reset is logged, disables further recovery, and
              requires the environment secret.
            </p>
            <?php if ($recoveryError !== ''): ?>
            <p class="login-feedback is-error" aria-live="polite"><?= htmlspecialchars($recoveryError, ENT_QUOTES) ?></p>
            <?php elseif ($recoverySuccess !== ''): ?>
            <p class="login-feedback is-success" aria-live="polite"><?= htmlspecialchars($recoverySuccess, ENT_QUOTES) ?></p>
            <?php endif; ?>
            <?php if ($recoveryAvailable): ?>
            <form method="post" class="recovery-form" novalidate>
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>" />
              <input type="hidden" name="intent" value="recover" />
              <div class="form-field">
                <label for="recovery-secret">Recovery secret</label>
                <input
                  type="password"
                  id="recovery-secret"
                  name="recovery_secret"
                  placeholder="Environment recovery secret"
                  autocomplete="off"
                  required
                />
              </div>
              <div class="form-field">
                <label for="recovery-password">New administrator password</label>
                <input
                  type="password"
                  id="recovery-password"
                  name="recovery_password"
                  placeholder="Minimum 12 characters with complexity"
                  autocomplete="new-password"
                  required
                />
              </div>
              <div class="form-field">
                <label for="recovery-password-confirm">Confirm password</label>
                <input
                  type="password"
                  id="recovery-password-confirm"
                  name="recovery_password_confirm"
                  placeholder="Re-enter the new password"
                  autocomplete="new-password"
                  required
                />
              </div>
              <p class="text-xs login-hint">
                Successful resets are timestamped, notify the audit log, and immediately disable recovery until re-enabled in the environment.
              </p>
              <button type="submit" class="btn btn-ghost btn-block">Reset administrator access</button>
            </form>
            <?php else: ?>
            <p class="text-xs login-hint">Recovery is currently locked because an active administrator account is available.</p>
            <?php endif; ?>
          </section>
          <?php endif; ?>
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

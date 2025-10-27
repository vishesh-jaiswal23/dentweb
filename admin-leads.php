<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_admin();
$admin = current_user();
$db = get_db();

$flashData = consume_flash();
$flashMessage = '';
$flashTone = 'info';
if (is_array($flashData)) {
    if (isset($flashData['message']) && is_string($flashData['message'])) {
        $flashMessage = trim($flashData['message']);
    }
    if (isset($flashData['type']) && is_string($flashData['type'])) {
        $flashTone = strtolower($flashData['type']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token(is_string($token) ? $token : null)) {
        set_flash('error', 'Your session expired. Please try again.');
        header('Location: admin-leads.php');
        exit;
    }

    $adminId = (int) ($admin['id'] ?? 0);

    try {
        switch ($action) {
            case 'create':
                admin_create_lead($db, $_POST, $adminId);
                set_flash('success', 'Lead created successfully.');
                break;
            case 'assign':
                $leadId = (int) ($_POST['lead_id'] ?? 0);
                $employeeId = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int) $_POST['assigned_to'] : null;
                admin_assign_lead($db, $leadId, $employeeId, $adminId);
                set_flash('success', 'Lead assignment updated.');
                break;
            case 'update-stage':
                $leadId = (int) ($_POST['lead_id'] ?? 0);
                $stage = (string) ($_POST['stage'] ?? '');
                $note = trim((string) ($_POST['note'] ?? ''));
                if ($stage === 'lost') {
                    admin_mark_lead_lost($db, $leadId, $adminId, $note);
                } else {
                    admin_update_lead_stage($db, $leadId, $stage, $adminId, $note);
                }
                set_flash('success', 'Lead stage updated.');
                break;
            case 'approve-proposal':
                $proposalId = (int) ($_POST['proposal_id'] ?? 0);
                admin_approve_lead_proposal($db, $proposalId, $adminId);
                set_flash('success', 'Proposal approved and lead converted.');
                break;
            case 'reject-proposal':
                $proposalId = (int) ($_POST['proposal_id'] ?? 0);
                $note = trim((string) ($_POST['review_note'] ?? ''));
                admin_reject_lead_proposal($db, $proposalId, $adminId, $note);
                set_flash('info', 'Proposal rejected.');
                break;
            case 'assign-referrer':
                $leadId = (int) ($_POST['lead_id'] ?? 0);
                $referrerId = isset($_POST['referrer_id']) && $_POST['referrer_id'] !== '' ? (int) $_POST['referrer_id'] : null;
                admin_assign_referrer($db, $leadId, $referrerId, $adminId);
                set_flash('success', $referrerId ? 'Referrer updated for the lead.' : 'Referrer removed from the lead.');
                break;
            default:
                throw new RuntimeException('Unsupported action.');
        }
    } catch (Throwable $exception) {
        set_flash('error', $exception->getMessage());
    }

    header('Location: admin-leads.php');
    exit;
}

function admin_format_datetime(?string $value): string
{
    if (!$value) {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($value);
        return $dt->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y · h:i A');
    } catch (Throwable $exception) {
        return $value;
    }
}

$employees = admin_active_employees($db);
$referrers = admin_active_referrers($db);
$referrerLookup = [];
foreach ($referrers as $referrerOption) {
    $referrerLookup[(int) $referrerOption['id']] = $referrerOption['name'];
}
$leads = admin_fetch_lead_overview($db);
$csrfToken = $_SESSION['csrf_token'] ?? '';
$stageOptions = [
    'new' => 'New',
    'visited' => 'Visited',
    'quotation' => 'Quotation',
    'lost' => 'Lost',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Leads & Site Visits | Admin</title>
  <meta name="description" content="Manage lead assignments, site visits, and proposal approvals." />
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
<body class="admin-leads" data-theme="light">
  <main class="admin-leads__shell">
    <header class="admin-leads__header">
      <div>
        <p class="admin-leads__subtitle">Admin workspace</p>
        <h1 class="admin-leads__title">Leads &amp; Site Visits</h1>
        <p class="admin-leads__meta">Signed in as <strong><?= htmlspecialchars($admin['full_name'] ?? 'Administrator', ENT_QUOTES) ?></strong></p>
      </div>
      <div class="admin-leads__actions">
        <a href="admin-dashboard.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to overview</a>
        <a href="logout.php" class="btn btn-primary"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Log out</a>
      </div>
    </header>

    <?php if ($flashMessage !== ''): ?>
    <div class="admin-alert admin-alert--<?= htmlspecialchars($flashTone, ENT_QUOTES) ?>" role="status" aria-live="polite">
      <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
      <span><?= htmlspecialchars($flashMessage, ENT_QUOTES) ?></span>
    </div>
    <?php endif; ?>

    <section class="admin-panel" aria-labelledby="lead-create">
      <div class="admin-panel__header">
        <div>
          <h2 id="lead-create">Create lead</h2>
          <p>Add a new enquiry and assign it to an employee for follow-up.</p>
        </div>
      </div>
      <form method="post" class="admin-form">
        <input type="hidden" name="action" value="create" />
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
        <div class="admin-form__grid">
          <label>
            Lead name
            <input type="text" name="name" required placeholder="Customer or organisation" />
          </label>
          <label>
            Phone
            <input type="tel" name="phone" placeholder="10-digit contact" pattern="[0-9]{10}" />
          </label>
          <label>
            Email
            <input type="email" name="email" placeholder="email@example.com" />
          </label>
          <label>
            Source
            <input type="text" name="source" placeholder="Web, referral, walk-in…" />
          </label>
          <label>
            Site location
            <input type="text" name="site_location" placeholder="City / neighbourhood" />
          </label>
          <label>
            Assign to
            <select name="assigned_to">
              <option value="">Unassigned</option>
              <?php foreach ($employees as $employee): ?>
              <option value="<?= (int) $employee['id'] ?>"><?= htmlspecialchars($employee['name'], ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Referrer (optional)
            <select name="referrer_id">
              <option value="">No referrer</option>
              <?php foreach ($referrers as $referrer): ?>
              <option value="<?= (int) $referrer['id'] ?>"><?= htmlspecialchars($referrer['name'], ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <label class="admin-form__full">
          Site details / notes
          <textarea name="site_details" rows="3" placeholder="Access instructions, load profile, or context to help the employee."></textarea>
        </label>
        <button type="submit" class="btn btn-primary btn-sm">Create lead</button>
      </form>
    </section>

    <section class="admin-panel" aria-labelledby="lead-pipeline">
      <div class="admin-panel__header">
        <div>
          <h2 id="lead-pipeline">Active pipeline</h2>
          <p>Track progress from first contact through quotation, approvals, and conversion.</p>
        </div>
        <span class="admin-panel__count"><?= count($leads) ?> leads</span>
      </div>
      <?php if (empty($leads)): ?>
      <p class="admin-empty">No leads available yet. Create a lead above to get started.</p>
      <?php else: ?>
      <div class="admin-lead-grid">
        <?php foreach ($leads as $lead): ?>
        <article class="admin-lead-card" data-lead-id="<?= (int) $lead['id'] ?>">
          <header class="admin-lead-card__header">
            <div>
              <h3><?= htmlspecialchars($lead['name'], ENT_QUOTES) ?></h3>
              <p class="admin-lead-card__meta">
                Stage: <span class="admin-badge admin-badge--<?= htmlspecialchars($lead['status'], ENT_QUOTES) ?>"><?= htmlspecialchars($lead['statusLabel'], ENT_QUOTES) ?></span>
              </p>
            </div>
            <ul class="admin-lead-card__info">
              <?php if ($lead['phone']): ?>
              <li><i class="fa-solid fa-phone" aria-hidden="true"></i> <?= htmlspecialchars($lead['phone'], ENT_QUOTES) ?></li>
              <?php endif; ?>
              <?php if ($lead['email']): ?>
              <li><i class="fa-solid fa-envelope" aria-hidden="true"></i> <?= htmlspecialchars($lead['email'], ENT_QUOTES) ?></li>
              <?php endif; ?>
              <?php if ($lead['siteLocation']): ?>
              <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= htmlspecialchars($lead['siteLocation'], ENT_QUOTES) ?></li>
              <?php endif; ?>
              <?php if ($lead['referrerName']): ?>
              <li><i class="fa-solid fa-handshake-angle" aria-hidden="true"></i> <?= htmlspecialchars($lead['referrerName'], ENT_QUOTES) ?></li>
              <?php endif; ?>
            </ul>
          </header>

          <?php if ($lead['notes']): ?>
          <p class="admin-lead-card__notes"><strong>Notes:</strong> <?= nl2br(htmlspecialchars($lead['notes'], ENT_QUOTES)) ?></p>
          <?php endif; ?>

          <div class="admin-lead-card__actions">
            <form method="post" class="admin-inline-form">
              <input type="hidden" name="action" value="assign" />
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
              <input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>" />
              <label>
                Assigned employee
                <select name="assigned_to">
                  <option value="">Unassigned</option>
                  <?php foreach ($employees as $employee): ?>
                  <option value="<?= (int) $employee['id'] ?>" <?= $lead['assignedId'] === (int) $employee['id'] ? 'selected' : '' ?>><?= htmlspecialchars($employee['name'], ENT_QUOTES) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <button type="submit" class="btn btn-secondary btn-xs">Save</button>
            </form>

            <form method="post" class="admin-inline-form">
              <input type="hidden" name="action" value="assign-referrer" />
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
              <input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>" />
              <label>
                Referrer
                <select name="referrer_id">
                  <option value="">No referrer</option>
                  <?php if ($lead['referrerId'] && !isset($referrerLookup[$lead['referrerId']])): ?>
                  <option value="<?= (int) $lead['referrerId'] ?>" selected><?= htmlspecialchars($lead['referrerName'], ENT_QUOTES) ?> (inactive)</option>
                  <?php endif; ?>
                  <?php foreach ($referrers as $referrer): ?>
                  <option value="<?= (int) $referrer['id'] ?>" <?= $lead['referrerId'] === (int) $referrer['id'] ? 'selected' : '' ?>><?= htmlspecialchars($referrer['name'], ENT_QUOTES) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <button type="submit" class="btn btn-secondary btn-xs">Update</button>
            </form>

            <?php if (!in_array($lead['status'], ['converted', 'lost'], true)): ?>
            <form method="post" class="admin-inline-form">
              <input type="hidden" name="action" value="update-stage" />
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
              <input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>" />
              <label>
                Stage
                <select name="stage">
                  <?php foreach ($stageOptions as $key => $label): ?>
                  <?php $disabled = lead_stage_index($key) < lead_stage_index($lead['status']); ?>
                  <option value="<?= htmlspecialchars($key, ENT_QUOTES) ?>" <?= $key === $lead['status'] ? 'selected' : '' ?> <?= $disabled ? 'disabled' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>
                Notes (optional)
                <input type="text" name="note" placeholder="Reason or outcome" />
              </label>
              <button type="submit" class="btn btn-secondary btn-xs">Update</button>
            </form>
            <?php endif; ?>
          </div>

          <div class="admin-lead-card__timeline">
            <div>
              <h4>Recent visits</h4>
              <?php if (empty($lead['visits'])): ?>
              <p class="admin-muted">No visits logged yet.</p>
              <?php else: ?>
              <ul class="admin-visit-list">
                <?php foreach (array_slice($lead['visits'], 0, 3) as $visit): ?>
                <li>
                  <time datetime="<?= htmlspecialchars($visit['createdAt'], ENT_QUOTES) ?>"><?= admin_format_datetime($visit['createdAt']) ?></time>
                  <p><?= nl2br(htmlspecialchars($visit['note'], ENT_QUOTES)) ?></p>
                  <?php if (!empty($visit['photoDataUrl'])): ?>
                  <a href="<?= htmlspecialchars($visit['photoDataUrl'], ENT_QUOTES) ?>" target="_blank" rel="noopener" class="admin-link">View photo</a>
                  <?php endif; ?>
                  <?php if ($visit['employeeName']): ?>
                  <span class="admin-muted">Logged by <?= htmlspecialchars($visit['employeeName'], ENT_QUOTES) ?></span>
                  <?php endif; ?>
                </li>
                <?php endforeach; ?>
              </ul>
              <?php endif; ?>
            </div>
            <div>
              <h4>Proposals</h4>
              <?php if (empty($lead['proposals'])): ?>
              <p class="admin-muted">No proposals submitted yet.</p>
              <?php else: ?>
              <ul class="admin-proposal-list">
                <?php foreach ($lead['proposals'] as $proposal): ?>
                <li class="admin-proposal admin-proposal--<?= htmlspecialchars($proposal['status'], ENT_QUOTES) ?>">
                  <div>
                    <strong><?= htmlspecialchars(ucfirst($proposal['status']), ENT_QUOTES) ?></strong>
                    <span><?= admin_format_datetime($proposal['createdAt']) ?></span>
                    <p><?= nl2br(htmlspecialchars($proposal['summary'], ENT_QUOTES)) ?></p>
                    <?php if ($proposal['estimate'] !== null): ?>
                    <span class="admin-muted">Estimate: ₹<?= number_format((float) $proposal['estimate']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($proposal['documentUrl'])): ?>
                    <a href="<?= htmlspecialchars($proposal['documentUrl'], ENT_QUOTES) ?>" target="_blank" rel="noopener" class="admin-link">Download <?= htmlspecialchars($proposal['documentName'] ?: 'attachment', ENT_QUOTES) ?></a>
                    <?php endif; ?>
                    <?php if ($proposal['reviewNote']): ?>
                    <span class="admin-muted">Review: <?= htmlspecialchars($proposal['reviewNote'], ENT_QUOTES) ?></span>
                    <?php endif; ?>
                  </div>
                  <?php if ($proposal['status'] === 'pending'): ?>
                  <div class="admin-proposal__actions">
                    <form method="post">
                      <input type="hidden" name="action" value="approve-proposal" />
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
                      <input type="hidden" name="proposal_id" value="<?= (int) $proposal['id'] ?>" />
                      <button type="submit" class="btn btn-success btn-xs">Approve</button>
                    </form>
                    <form method="post">
                      <input type="hidden" name="action" value="reject-proposal" />
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
                      <input type="hidden" name="proposal_id" value="<?= (int) $proposal['id'] ?>" />
                      <input type="text" name="review_note" placeholder="Reason" />
                      <button type="submit" class="btn btn-danger btn-xs">Reject</button>
                    </form>
                  </div>
                  <?php endif; ?>
                </li>
                <?php endforeach; ?>
              </ul>
              <?php endif; ?>
            </div>
          </div>

          <footer class="admin-lead-card__footer">
            <span>Created <?= admin_format_datetime($lead['createdAt']) ?></span>
            <span>Updated <?= admin_format_datetime($lead['updatedAt']) ?></span>
          </footer>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>

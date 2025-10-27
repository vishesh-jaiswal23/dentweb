<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_admin();
$user = current_user();
$db = get_db();

$module = strtolower(trim((string) ($_GET['module'] ?? 'employees')));

$modules = [
    'employees' => [
        'title' => 'Employees',
        'description' => 'Manage staff access and activity for the internal portals.',
        'defaultFilter' => 'active',
        'filters' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'pending' => 'Pending',
            'all' => 'All employees',
        ],
        'columns' => ['Name', 'Email', 'Status', 'Created', 'Last login'],
        'fetch' => static function (PDO $db, string $filter): array {
            return admin_list_employees($db, $filter);
        },
        'transform' => static function (array $row): array {
            return [
                $row['full_name'] ?? '',
                $row['email'] ?? '',
                ucfirst((string) ($row['status'] ?? '')),
                format_admin_datetime($row['created_at'] ?? ''),
                format_admin_datetime($row['last_login_at'] ?? ''),
            ];
        },
    ],
    'leads' => [
        'title' => 'Leads',
        'description' => 'Follow up on enquiries and site visits captured by the sales desk.',
        'defaultFilter' => 'new',
        'filters' => [
            'new' => 'New',
            'contacted' => 'Contacted',
            'qualified' => 'Qualified',
            'converted' => 'Converted',
            'lost' => 'Lost',
            'all' => 'All leads',
        ],
        'columns' => ['Lead', 'Status', 'Source', 'Assigned to', 'Updated'],
        'fetch' => static function (PDO $db, string $filter): array {
            return admin_list_leads($db, $filter);
        },
        'transform' => static function (array $row): array {
            return [
                $row['name'] ?? '',
                lead_status_label($row['status'] ?? ''),
                $row['source'] ? ucfirst((string) $row['source']) : '—',
                $row['assigned_to'] ? ('User #' . $row['assigned_to']) : 'Unassigned',
                format_admin_datetime($row['updated_at'] ?: ($row['created_at'] ?? '')),
            ];
        },
    ],
    'installations' => [
        'title' => 'Installations',
        'description' => 'Monitor ongoing projects and confirm commissioning milestones.',
        'defaultFilter' => 'in_progress',
        'filters' => [
            'in_progress' => 'In progress',
            'planning' => 'Planning',
            'completed' => 'Completed',
            'on_hold' => 'On hold',
            'cancelled' => 'Cancelled',
            'all' => 'All jobs',
        ],
        'columns' => ['Project', 'Status', 'Scheduled', 'Handover', 'Updated'],
        'fetch' => static function (PDO $db, string $filter): array {
            return admin_list_installations($db, $filter);
        },
        'transform' => static function (array $row): array {
            $name = $row['project_reference'] ?: ($row['customer_name'] ?? '');
            return [
                $name,
                installation_status_label($row['status'] ?? ''),
                format_admin_date($row['scheduled_date'] ?? ''),
                format_admin_date($row['handover_date'] ?? ''),
                format_admin_datetime($row['updated_at'] ?: ($row['created_at'] ?? '')),
            ];
        },
    ],
    'complaints' => [
        'title' => 'Complaints',
        'description' => 'Track open service issues and escalations from customers.',
        'defaultFilter' => 'open',
        'filters' => [
            'open' => 'Open (Intake/Triage/Work)',
            'all' => 'All complaints',
        ],
        'columns' => ['Reference', 'Status', 'Priority', 'Created', 'Updated'],
        'fetch' => static function (PDO $db, string $filter): array {
            return admin_list_complaints($db, $filter);
        },
        'transform' => static function (array $row): array {
            return [
                $row['reference'] ?? '',
                complaint_status_label($row['status'] ?? ''),
                ucfirst((string) ($row['priority'] ?? '')),
                format_admin_datetime($row['created_at'] ?? ''),
                format_admin_datetime($row['updated_at'] ?? ''),
            ];
        },
    ],
    'subsidy' => [
        'title' => 'Subsidy',
        'description' => 'Review the pipeline for PM Surya Ghar and other subsidy applications.',
        'defaultFilter' => 'pending',
        'filters' => [
            'pending' => 'Pending / Submitted',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'disbursed' => 'Disbursed',
            'all' => 'All applications',
        ],
        'columns' => ['Application', 'Status', 'Amount', 'Submitted', 'Updated'],
        'fetch' => static function (PDO $db, string $filter): array {
            return admin_list_subsidy($db, $filter);
        },
        'transform' => static function (array $row): array {
            $label = $row['application_number'] ?: ($row['customer_name'] ?? '');
            $amount = isset($row['amount']) ? number_format((float) $row['amount']) : '—';
            return [
                $label,
                subsidy_status_label($row['status'] ?? ''),
                $amount,
                format_admin_date($row['submitted_on'] ?? ''),
                format_admin_datetime($row['updated_at'] ?: ($row['created_at'] ?? '')),
            ];
        },
    ],
];

if (!isset($modules[$module])) {
    $module = 'employees';
}

$config = $modules[$module];
$filter = strtolower(trim((string) ($_GET['filter'] ?? $config['defaultFilter'])));
if (!isset($config['filters'][$filter])) {
    $filter = $config['defaultFilter'];
}

$rows = ($config['fetch'])($db, $filter);
$records = array_map($config['transform'], $rows);

function format_admin_datetime(?string $value): string
{
    if (!$value) {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($value);
    } catch (Throwable $exception) {
        return $value;
    }
    return $dt->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y · h:i A');
}

function format_admin_date(?string $value): string
{
    if (!$value) {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($value);
    } catch (Throwable $exception) {
        return $value;
    }
    return $dt->format('d M Y');
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($config['title'], ENT_QUOTES) ?> | Admin Lists</title>
  <meta name="description" content="Filtered admin records for <?= htmlspecialchars($config['title'], ENT_QUOTES) ?>." />
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
    <header class="admin-records__header">
      <div class="admin-records__title-group">
        <a href="admin-dashboard.php" class="admin-records__back"><i class="fa-solid fa-arrow-left"></i> Overview</a>
        <h1><?= htmlspecialchars($config['title'], ENT_QUOTES) ?></h1>
        <p><?= htmlspecialchars($config['description'], ENT_QUOTES) ?></p>
      </div>
      <div class="admin-records__meta">
        <span>Signed in as <?= htmlspecialchars($user['full_name'] ?? 'Administrator', ENT_QUOTES) ?></span>
        <a href="logout.php" class="btn btn-ghost">Log out</a>
      </div>
    </header>

    <form class="admin-records__filter" method="get">
      <input type="hidden" name="module" value="<?= htmlspecialchars($module, ENT_QUOTES) ?>" />
      <label>
        Filter
        <select name="filter" onchange="this.form.submit()">
          <?php foreach ($config['filters'] as $value => $label): ?>
          <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>" <?= $value === $filter ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>

    <section class="admin-records__table" aria-live="polite">
      <?php if (count($records) === 0): ?>
      <p class="admin-records__empty">No records match the selected filter.</p>
      <?php else: ?>
      <div class="admin-table-wrapper">
        <table class="admin-table">
          <thead>
            <tr>
              <?php foreach ($config['columns'] as $column): ?>
              <th scope="col"><?= htmlspecialchars($column, ENT_QUOTES) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $record): ?>
            <tr>
              <?php foreach ($record as $cell): ?>
              <td><?= htmlspecialchars((string) $cell, ENT_QUOTES) ?></td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>
  </main>

  <script src="admin-dashboard.js" defer></script>
</body>
</html>

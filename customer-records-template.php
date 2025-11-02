<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_admin();

$type = isset($_GET['type']) ? (string) $_GET['type'] : 'leads';
$columns = customer_records_template_columns($type);
$filename = 'customer-records-' . ($type === 'customers' ? 'customers' : 'leads') . '-template.csv';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
if ($output === false) {
    exit;
}

fputcsv($output, $columns);
fclose($output);

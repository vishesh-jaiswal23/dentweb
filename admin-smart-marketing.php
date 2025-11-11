<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/ai_gemini.php';
require_once __DIR__ . '/includes/smart_marketing.php';

require_admin();

$admin = current_user();
$csrfToken = $_SESSION['csrf_token'] ?? '';

$aiSettings = ai_settings_load();
$marketingSettings = smart_marketing_settings_load();
$aiHealth = smart_marketing_ai_health($aiSettings);
$integrationsHealth = smart_marketing_integrations_health($marketingSettings);
$brainRuns = smart_marketing_brain_runs_load();
$assets = smart_marketing_list_assets();
$auditLog = smart_marketing_audit_log_read();
$campaigns = smart_marketing_campaigns_load();
$automationLog = smart_marketing_automation_log_read();
$connectors = smart_marketing_channel_connectors($marketingSettings);
$sitePages = smart_marketing_site_pages();
$analytics = smart_marketing_analytics_load($marketingSettings);
$optimizationState = smart_marketing_optimization_load($marketingSettings);
$governanceState = smart_marketing_governance_load($marketingSettings);
$notificationsState = smart_marketing_notifications_load();
$campaignCatalog = [];
foreach (smart_marketing_campaign_catalog() as $key => $meta) {
    $campaignCatalog[] = ['id' => $key, 'label' => $meta['label']];
}

$defaultRegions = smart_marketing_region_defaults($marketingSettings);
$defaultCompliance = smart_marketing_compliance_defaults($marketingSettings);
$languageOptions = smart_marketing_language_options();
$goalOptions = smart_marketing_goal_options();
$productOptions = smart_marketing_product_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawBody = file_get_contents('php://input');
    $payload = [];
    if ($rawBody !== false && trim($rawBody) !== '') {
        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            $payload = [];
        }
    } else {
        $payload = $_POST;
    }

    $action = (string) ($payload['action'] ?? '');
    $token = (string) ($payload['csrfToken'] ?? ($payload['csrf_token'] ?? ''));

    if (!verify_csrf_token($token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Session expired. Refresh and try again.']);
        exit;
    }

    $response = ['ok' => false, 'error' => 'Unsupported action'];

    try {
        switch ($action) {
            case 'save-settings':
                $updates = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
                $merged = smart_marketing_collect_settings_payload($updates, $marketingSettings);
                $governanceSnapshot = $governanceState ?? smart_marketing_governance_load($marketingSettings);
                if (($governanceSnapshot['budgetLock']['enabled'] ?? false)) {
                    $lockedCap = (float) ($governanceSnapshot['budgetLock']['cap'] ?? ($marketingSettings['budget']['monthlyCap'] ?? 0));
                    if ($merged['budget']['monthlyCap'] > $lockedCap + 0.01) {
                        throw new RuntimeException(sprintf('Budget lock prevents increasing monthly cap beyond %s.', smart_marketing_format_currency($lockedCap, $merged['budget']['currency'] ?? 'INR')));
                    }
                }
                smart_marketing_settings_save($merged);
                smart_marketing_audit_log_append('settings.updated', ['keys' => array_keys($updates)], $admin);
                $marketingSettings = smart_marketing_settings_load();
                $governanceState = smart_marketing_governance_load($marketingSettings);
                $response = [
                    'ok' => true,
                    'settings' => $marketingSettings,
                    'aiHealth' => smart_marketing_ai_health($aiSettings),
                    'integrations' => smart_marketing_integrations_health($marketingSettings),
                    'audit' => smart_marketing_audit_log_read(),
                    'connectors' => smart_marketing_channel_connectors($marketingSettings),
                ];
                break;

            case 'generate-plan':
                $inputs = smart_marketing_collect_inputs_from_request($marketingSettings, $payload);
                $errors = smart_marketing_validate_brain_inputs($inputs, $marketingSettings);
                if (!empty($errors)) {
                    throw new RuntimeException(implode('\n', $errors));
                }

                $generation = smart_marketing_generate_brain_plan($inputs, $marketingSettings, $aiSettings);
                $runs = smart_marketing_brain_runs_load();
                $runId = smart_marketing_next_run_id($runs);
                $status = smart_marketing_autonomy_status($inputs['autonomyMode']);

                $record = smart_marketing_prepare_run_record($runId, $inputs, $generation, $status);
                $runs[] = $record;
                $runs = array_slice($runs, -20);
                smart_marketing_brain_runs_save($runs);

                smart_marketing_audit_log_append('brain.generated', ['run_id' => $runId, 'status' => $status], $admin);

                $autoLaunched = [];
                if ($status === 'live') {
                    try {
                        $autoTypes = array_keys(smart_marketing_campaign_catalog());
                        $autoResult = smart_marketing_launch_campaigns($runs, $runId, $autoTypes, $marketingSettings, ['landing' => ['mode' => 'auto']]);
                        $runs = smart_marketing_brain_runs_load();
                        $autoLaunched = $autoResult['launched'];
                        $campaigns = $autoResult['campaigns'];
                        $automationLog = smart_marketing_automation_log_read();
                        smart_marketing_audit_log_append('campaigns.auto_launched', ['run_id' => $runId, 'campaign_ids' => array_column($autoLaunched, 'id')], $admin);
                    } catch (Throwable $autoException) {
                        $campaigns = smart_marketing_campaigns_load();
                        $automationLog = smart_marketing_automation_log_read();
                        smart_marketing_audit_log_append('campaigns.auto_launch_failed', ['run_id' => $runId, 'error' => $autoException->getMessage()], $admin);
                    }
                } else {
                    $campaigns = smart_marketing_campaigns_load();
                    $automationLog = smart_marketing_automation_log_read();
                }

                $response = [
                    'ok' => true,
                    'run' => $record,
                    'runs' => array_reverse($runs),
                    'audit' => smart_marketing_audit_log_read(),
                    'campaigns' => $campaigns,
                    'automationLog' => $automationLog,
                    'autoLaunched' => $autoLaunched,
                ];
                break;

            case 'update-run-status':
                $runId = (int) ($payload['runId'] ?? 0);
                $status = strtolower((string) ($payload['status'] ?? ''));
                if (!in_array($status, ['live', 'pending', 'draft', 'halted'], true)) {
                    throw new RuntimeException('Unsupported status value.');
                }

                $runs = smart_marketing_brain_runs_load();
                if (!smart_marketing_update_run_status($runs, $runId, $status)) {
                    throw new RuntimeException('Plan not found.');
                }

                smart_marketing_brain_runs_save($runs);
                smart_marketing_audit_log_append('brain.status_changed', ['run_id' => $runId, 'status' => $status], $admin);

                $response = [
                    'ok' => true,
                    'runs' => array_reverse($runs),
                    'audit' => smart_marketing_audit_log_read(),
                ];
                break;

            case 'kill-switch':
                $runs = smart_marketing_brain_runs_load();
                smart_marketing_apply_kill_switch($runs);
                smart_marketing_brain_runs_save($runs);
                $marketingSettings['autonomy']['killSwitchEngaged'] = true;
                smart_marketing_settings_save($marketingSettings);
                smart_marketing_audit_log_append('brain.kill_switch', [], $admin);

                $response = [
                    'ok' => true,
                    'runs' => array_reverse($runs),
                    'settings' => $marketingSettings,
                    'audit' => smart_marketing_audit_log_read(),
                    'campaigns' => smart_marketing_campaigns_load(),
                    'automationLog' => smart_marketing_automation_log_read(),
                ];
                break;

            case 'creative-text':
                $category = trim((string) ($payload['category'] ?? 'marketing copy'));
                $brief = trim((string) ($payload['brief'] ?? ''));
                if ($brief === '') {
                    throw new RuntimeException('Provide a brief for creative generation.');
                }
                $asset = smart_marketing_generate_text_asset($aiSettings, $category, $brief, $marketingSettings);
                smart_marketing_audit_log_append('creative.text', ['category' => $category], $admin);
                $response = [
                    'ok' => true,
                    'asset' => $asset,
                    'assets' => smart_marketing_list_assets(),
                    'audit' => smart_marketing_audit_log_read(),
                ];
                break;

            case 'creative-image':
                $promptText = trim((string) ($payload['prompt'] ?? ''));
                $preset = trim((string) ($payload['preset'] ?? 'meta-square'));
                if ($promptText === '') {
                    throw new RuntimeException('Provide a prompt for the image.');
                }
                $asset = smart_marketing_generate_image_asset($aiSettings, $promptText, $preset, $marketingSettings);
                smart_marketing_audit_log_append('creative.image', ['preset' => $preset], $admin);
                $response = [
                    'ok' => true,
                    'asset' => $asset,
                    'assets' => smart_marketing_list_assets(),
                    'audit' => smart_marketing_audit_log_read(),
                ];
                break;

            case 'creative-tts':
                $script = trim((string) ($payload['script'] ?? ''));
                if ($script === '') {
                    throw new RuntimeException('Provide a voice-over script.');
                }
                $asset = smart_marketing_generate_tts_asset($aiSettings, $script, $marketingSettings);
                smart_marketing_audit_log_append('creative.tts', ['length' => strlen($script)], $admin);
                $response = [
                    'ok' => true,
                    'asset' => $asset,
                    'assets' => smart_marketing_list_assets(),
                    'audit' => smart_marketing_audit_log_read(),
                ];
                break;
            case 'connector-connect':
                $connectorKey = (string) ($payload['connector'] ?? '');
                $fields = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
                $entry = smart_marketing_connector_connect($marketingSettings, $connectorKey, $fields);
                smart_marketing_settings_save($marketingSettings);
                smart_marketing_audit_log_append('connector.connected', ['connector' => $connectorKey], $admin);
                $response = [
                    'ok' => true,
                    'connector' => $entry,
                    'settings' => $marketingSettings,
                    'integrations' => smart_marketing_integrations_health($marketingSettings),
                    'connectors' => smart_marketing_channel_connectors($marketingSettings),
                    'audit' => smart_marketing_audit_log_read(),
                ];
                break;

            case 'connector-test':
                $connectorKey = (string) ($payload['connector'] ?? '');
                $entry = smart_marketing_connector_test($marketingSettings, $connectorKey);
                smart_marketing_settings_save($marketingSettings);
                smart_marketing_audit_log_append('connector.tested', ['connector' => $connectorKey, 'result' => $entry['lastTestResult'] ?? 'unknown'], $admin);
                $response = [
                    'ok' => true,
                    'connector' => $entry,
                    'settings' => $marketingSettings,
                    'integrations' => smart_marketing_integrations_health($marketingSettings),
                    'connectors' => smart_marketing_channel_connectors($marketingSettings),
                    'audit' => smart_marketing_audit_log_read(),
                ];
                break;

            case 'campaign-launch':
                $runId = (int) ($payload['runId'] ?? 0);
                $types = array_values(array_filter((array) ($payload['campaignTypes'] ?? []), static fn($value) => is_string($value) && $value !== ''));
                $landingOptions = is_array($payload['landing'] ?? null) ? $payload['landing'] : [];
                if (empty($types)) {
                    throw new RuntimeException('Select at least one campaign type.');
                }
                $result = smart_marketing_launch_campaigns($brainRuns, $runId, $types, $marketingSettings, ['landing' => $landingOptions]);
                $brainRuns = smart_marketing_brain_runs_load();
                $campaigns = $result['campaigns'];
                $automationLog = smart_marketing_automation_log_read();
                smart_marketing_audit_log_append('campaigns.launched', ['run_id' => $runId, 'types' => $types, 'campaign_ids' => array_column($result['launched'], 'id')], $admin);
                $response = [
                    'ok' => true,
                    'launched' => $result['launched'],
                    'runs' => array_reverse($brainRuns),
                    'campaigns' => $campaigns,
                    'automationLog' => $automationLog,
                    'audit' => smart_marketing_audit_log_read(),
                ];
                break;

            case 'analytics-refresh':
                $refresh = smart_marketing_refresh_analytics($marketingSettings);
                $analytics = $refresh['analytics'];
                foreach ($refresh['alerts'] as $alert) {
                    $notificationsState = smart_marketing_notifications_push(
                        $notificationsState,
                        $alert['type'] ?? 'budget_alert',
                        (string) ($alert['message'] ?? ''),
                        (array) ($alert['channels'] ?? ['email'])
                    );
                }
                smart_marketing_notifications_save($notificationsState);
                smart_marketing_audit_log_append('analytics.refresh', ['alerts' => count($refresh['alerts'])], $admin);
                $response = [
                    'ok' => true,
                    'analytics' => $analytics,
                    'notifications' => $notificationsState,
                    'alerts' => $refresh['alerts'],
                ];
                break;

            case 'optimization-save':
                $incoming = is_array($payload['optimization'] ?? null) ? $payload['optimization'] : [];
                $current = smart_marketing_optimization_load($marketingSettings);
                $optimizationState = smart_marketing_optimization_merge($incoming, $current, $marketingSettings);
                smart_marketing_optimization_save($optimizationState);
                smart_marketing_audit_log_append('optimization.updated', ['rules' => array_keys($incoming['autoRules'] ?? [])], $admin);
                $response = [
                    'ok' => true,
                    'optimization' => $optimizationState,
                ];
                break;

            case 'optimization-auto-run':
                $campaigns = smart_marketing_campaigns_load();
                $result = smart_marketing_run_automations($campaigns, $marketingSettings, false);
                $automationLog = smart_marketing_automation_log_read();
                $optimizationState = $result['optimization'];
                $notificationsState = $result['notifications'];
                $governanceState = $result['governance'];
                smart_marketing_audit_log_append('optimization.auto_run', ['actions' => count($result['entries'])], $admin);
                $response = [
                    'ok' => true,
                    'automation' => $result['entries'],
                    'automationLog' => $automationLog,
                    'campaigns' => smart_marketing_campaigns_load(),
                    'optimization' => $optimizationState,
                    'notifications' => $notificationsState,
                    'governance' => $governanceState,
                ];
                break;

            case 'optimization-manual-action':
                $kind = (string) ($payload['kind'] ?? '');
                $details = is_array($payload['details'] ?? null) ? $payload['details'] : [];
                $notes = trim((string) ($payload['notes'] ?? ''));
                if (!in_array($kind, ['promote_creative', 'duplicate_campaign', 'schedule_test'], true)) {
                    throw new RuntimeException('Unsupported manual optimisation action.');
                }
                $optimizationState = smart_marketing_optimization_load($marketingSettings);
                $message = '';
                $timestamp = ai_timestamp();
                if ($kind === 'promote_creative') {
                    $message = sprintf('Promoted creative "%s" across %s channels.', $details['creative'] ?? 'asset', $details['channels'] ?? 'selected');
                } elseif ($kind === 'duplicate_campaign') {
                    $message = sprintf('Duplicated campaign %s into %s.', $details['source'] ?? 'campaign', $details['targets'] ?? 'new districts');
                } else {
                    $variantA = $details['variantA'] ?? 'Variant A';
                    $variantB = $details['variantB'] ?? 'Variant B';
                    $schedule = $details['schedule'] ?? 'next cycle';
                    $message = sprintf('Scheduled %s test: %s vs %s starting %s.', $details['testType'] ?? 'creative', $variantA, $variantB, $schedule);
                    $optimizationState['learning']['tests'][] = [
                        'id' => 'TEST-' . strtoupper(dechex(time())),
                        'createdAt' => $timestamp,
                        'type' => strtoupper((string) ($details['testType'] ?? 'Creative')),
                        'status' => 'scheduled',
                        'result' => sprintf('%s vs %s starting %s', $variantA, $variantB, $schedule),
                    ];
                    if (count($optimizationState['learning']['tests']) > 25) {
                        $optimizationState['learning']['tests'] = array_slice($optimizationState['learning']['tests'], -25);
                    }
                    $optimizationState['learning']['nextBestAction'] = 'Review experiment results after 7 days.';
                }
                $optimizationState['manualPlaybooks']['lastActionAt'] = $timestamp;
                $optimizationState['history'][] = [
                    'timestamp' => $timestamp,
                    'rule' => $kind,
                    'campaign_id' => $details['source'] ?? null,
                    'message' => $message . ($notes !== '' ? ' Notes: ' . $notes : ''),
                ];
                if (count($optimizationState['history']) > 100) {
                    $optimizationState['history'] = array_slice($optimizationState['history'], -100);
                }
                smart_marketing_optimization_save($optimizationState);
                $notificationsState = smart_marketing_notifications_push($notificationsState, 'manual_action', $message, ['email']);
                smart_marketing_notifications_save($notificationsState);
                $governanceState = smart_marketing_governance_load($marketingSettings);
                smart_marketing_governance_log($governanceState, 'optimization.manual_action', ['kind' => $kind, 'details' => $details], $admin);
                smart_marketing_governance_save($governanceState);
                smart_marketing_audit_log_append('optimization.manual', ['kind' => $kind], $admin);
                $response = [
                    'ok' => true,
                    'optimization' => $optimizationState,
                    'notifications' => $notificationsState,
                    'governance' => $governanceState,
                ];
                break;

            case 'governance-budget-lock':
                $enabled = (bool) ($payload['enabled'] ?? false);
                $cap = (float) ($payload['cap'] ?? ($marketingSettings['budget']['monthlyCap'] ?? 0));
                if ($enabled) {
                    $cap = min($cap, (float) ($marketingSettings['budget']['monthlyCap'] ?? $cap));
                }
                $governanceState = smart_marketing_governance_load($marketingSettings);
                $governanceState['budgetLock']['enabled'] = $enabled;
                $governanceState['budgetLock']['cap'] = $cap;
                $governanceState['budgetLock']['updatedAt'] = ai_timestamp();
                smart_marketing_governance_log($governanceState, 'governance.budget_lock', ['enabled' => $enabled, 'cap' => $cap], $admin);
                smart_marketing_governance_save($governanceState);
                smart_marketing_audit_log_append('governance.budget_lock', ['enabled' => $enabled], $admin);
                $response = [
                    'ok' => true,
                    'governance' => $governanceState,
                ];
                break;

            case 'governance-policy-save':
                $policyInput = is_array($payload['policy'] ?? null) ? $payload['policy'] : [];
                $governanceState = smart_marketing_governance_load($marketingSettings);
                $policy = $governanceState['policyChecklist'];
                $policy['pmSuryaClaims'] = (bool) ($policyInput['pmSuryaClaims'] ?? $policy['pmSuryaClaims']);
                $policy['ethicalMessaging'] = (bool) ($policyInput['ethicalMessaging'] ?? $policy['ethicalMessaging']);
                $policy['disclaimerPlaced'] = (bool) ($policyInput['disclaimerPlaced'] ?? $policy['disclaimerPlaced']);
                $policy['dataAccuracy'] = (bool) ($policyInput['dataAccuracy'] ?? $policy['dataAccuracy']);
                $policy['notes'] = trim((string) ($policyInput['notes'] ?? $policy['notes']));
                $policy['lastReviewed'] = ai_timestamp();
                $governanceState['policyChecklist'] = $policy;
                smart_marketing_governance_log($governanceState, 'governance.policy_review', $policy, $admin);
                smart_marketing_governance_save($governanceState);
                smart_marketing_audit_log_append('governance.policy_review', [], $admin);
                $response = [
                    'ok' => true,
                    'governance' => $governanceState,
                ];
                break;

            case 'governance-emergency-stop':
                $campaigns = smart_marketing_campaigns_load();
                foreach ($campaigns as &$campaign) {
                    if (($campaign['status'] ?? '') === 'launched') {
                        $campaign['status'] = 'paused';
                        $campaign['audit_trail'][] = [
                            'timestamp' => ai_timestamp(),
                            'action' => 'emergency_stop',
                            'context' => [],
                        ];
                    }
                }
                unset($campaign);
                smart_marketing_campaigns_save($campaigns);
                $governanceState = smart_marketing_governance_load($marketingSettings);
                $governanceState['emergencyStop'] = [
                    'active' => true,
                    'triggeredAt' => ai_timestamp(),
                    'triggeredBy' => $admin['full_name'] ?? 'Admin',
                ];
                smart_marketing_governance_log($governanceState, 'governance.emergency_stop', [], $admin);
                smart_marketing_governance_save($governanceState);
                $marketingSettings['autonomy']['killSwitchEngaged'] = true;
                smart_marketing_settings_save($marketingSettings);
                $marketingSettings = smart_marketing_settings_load();
                $notificationsState = smart_marketing_notifications_push($notificationsState, 'emergency_stop', 'Emergency stop activated across all channels.', ['email', 'whatsapp']);
                smart_marketing_notifications_save($notificationsState);
                smart_marketing_audit_log_append('governance.emergency_stop', [], $admin);
                $response = [
                    'ok' => true,
                    'campaigns' => $campaigns,
                    'settings' => $marketingSettings,
                    'governance' => $governanceState,
                    'notifications' => $notificationsState,
                    'automationLog' => smart_marketing_automation_log_read(),
                    'audit' => smart_marketing_audit_log_read(),
                ];
                break;

            case 'governance-data-request':
                $mode = strtolower((string) ($payload['mode'] ?? ''));
                if (!in_array($mode, ['export', 'erase'], true)) {
                    throw new RuntimeException('Unsupported data request mode.');
                }
                $governanceState = smart_marketing_governance_load($marketingSettings);
                $download = null;
                if ($mode === 'export') {
                    $export = smart_marketing_data_export($governanceState, $marketingSettings);
                    $download = basename($export['file']);
                } else {
                    smart_marketing_data_erase($governanceState);
                }
                smart_marketing_governance_save($governanceState);
                $message = $mode === 'export' ? 'Data export generated for compliance review.' : 'Data erasure request queued for processing.';
                $notificationsState = smart_marketing_notifications_push($notificationsState, 'data_' . $mode, $message, ['email']);
                smart_marketing_notifications_save($notificationsState);
                smart_marketing_audit_log_append('governance.data_' . $mode, [], $admin);
                $response = [
                    'ok' => true,
                    'governance' => $governanceState,
                    'notifications' => $notificationsState,
                ];
                if ($download) {
                    $response['download'] = $download;
                }
                break;

            case 'notifications-save':
                $incoming = is_array($payload['notifications'] ?? null) ? $payload['notifications'] : [];
                $notificationsState = smart_marketing_notifications_load();
                if (isset($incoming['dailyDigest']) && is_array($incoming['dailyDigest'])) {
                    $digest = $notificationsState['dailyDigest'];
                    $digest['enabled'] = (bool) ($incoming['dailyDigest']['enabled'] ?? $digest['enabled']);
                    $digest['time'] = trim((string) ($incoming['dailyDigest']['time'] ?? $digest['time']));
                    if (isset($incoming['dailyDigest']['channels']) && is_array($incoming['dailyDigest']['channels'])) {
                        $digest['channels']['email'] = trim((string) ($incoming['dailyDigest']['channels']['email'] ?? $digest['channels']['email']));
                        $digest['channels']['whatsapp'] = trim((string) ($incoming['dailyDigest']['channels']['whatsapp'] ?? $digest['channels']['whatsapp']));
                    }
                    $notificationsState['dailyDigest'] = $digest;
                }
                if (isset($incoming['instant']) && is_array($incoming['instant'])) {
                    $notificationsState['instant']['email'] = (bool) ($incoming['instant']['email'] ?? $notificationsState['instant']['email']);
                    $notificationsState['instant']['whatsapp'] = (bool) ($incoming['instant']['whatsapp'] ?? $notificationsState['instant']['whatsapp']);
                }
                smart_marketing_notifications_save($notificationsState);
                smart_marketing_audit_log_append('notifications.updated', [], $admin);
                $response = [
                    'ok' => true,
                    'notifications' => $notificationsState,
                ];
                break;

            case 'notifications-test':
                $notificationsState = smart_marketing_notifications_load();
                $notificationsState = smart_marketing_notifications_push($notificationsState, 'test', 'Test notification sent to configured channels.', ['email', 'whatsapp']);
                smart_marketing_notifications_save($notificationsState);
                smart_marketing_audit_log_append('notifications.test', [], $admin);
                $response = [
                    'ok' => true,
                    'notifications' => $notificationsState,
                ];
                break;

            case 'automation-run':
                $campaigns = smart_marketing_campaigns_load();
                $result = smart_marketing_run_automations($campaigns, $marketingSettings, true);
                $automationLog = smart_marketing_automation_log_read();
                $optimizationState = $result['optimization'];
                $notificationsState = $result['notifications'];
                $governanceState = $result['governance'];
                $response = [
                    'ok' => true,
                    'automation' => $result['entries'],
                    'automationLog' => $automationLog,
                    'campaigns' => smart_marketing_campaigns_load(),
                    'optimization' => $optimizationState,
                    'notifications' => $notificationsState,
                    'governance' => $governanceState,
                    'audit' => smart_marketing_audit_log_read(),
                ];
                break;
        }
    } catch (Throwable $exception) {
        $response = ['ok' => false, 'error' => $exception->getMessage()];
    }

    header('Content-Type: application/json');
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$pageState = [
    'settings' => $marketingSettings,
    'aiHealth' => $aiHealth,
    'integrations' => $integrationsHealth,
    'brainRuns' => array_reverse($brainRuns),
    'assets' => $assets,
    'audit' => $auditLog,
    'campaigns' => $campaigns,
    'automationLog' => $automationLog,
    'connectors' => $connectors,
    'sitePages' => $sitePages,
    'campaignCatalog' => $campaignCatalog,
    'analytics' => $analytics,
    'optimization' => $optimizationState,
    'governance' => $governanceState,
    'notifications' => $notificationsState,
    'csrfToken' => $csrfToken,
    'defaults' => [
        'regions' => $defaultRegions,
        'compliance' => $defaultCompliance,
        'languages' => $languageOptions,
        'goals' => $goalOptions,
        'products' => $productOptions,
    ],
];

$pageStateJson = json_encode($pageState, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Smart Marketing | Admin</title>
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
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
  <script>window.SmartMarketingState = <?= $pageStateJson ?>;</script>
</head>
<body class="admin-smart-marketing" data-theme="light">
  <main class="smart-marketing__shell">
    <header class="smart-marketing__header">
      <div>
        <p class="smart-marketing__subtitle">Admin smart automation suite</p>
        <h1 class="smart-marketing__title">Smart Marketing</h1>
        <p class="smart-marketing__meta">Signed in as <strong><?= htmlspecialchars($admin['full_name'] ?? 'Administrator', ENT_QUOTES) ?></strong></p>
      </div>
      <div class="smart-marketing__actions">
        <a href="admin-dashboard.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Overview</a>
        <a href="logout.php" class="btn btn-primary"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Log out</a>
      </div>
    </header>

    <section class="smart-marketing__health" aria-label="Connection health">
      <div class="smart-marketing__panel">
        <h2><i class="fa-solid fa-microchip" aria-hidden="true"></i> AI Health</h2>
        <p data-ai-health></p>
        <ul class="smart-marketing__models" data-ai-models></ul>
      </div>
      <div class="smart-marketing__panel">
        <h2><i class="fa-solid fa-plug" aria-hidden="true"></i> Integrations Health</h2>
        <ul class="smart-marketing__integrations" data-integrations-list></ul>
      </div>
      <div class="smart-marketing__panel smart-marketing__panel--audit">
        <h2><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> Audit Log</h2>
        <ul class="smart-marketing__audit" data-audit-log></ul>
      </div>
    </section>

    <section class="smart-marketing__grid" aria-label="Marketing workspace">
      <article class="smart-marketing__card" aria-labelledby="connectors-heading">
        <header class="smart-marketing__card-header">
          <h2 id="connectors-heading"><i class="fa-solid fa-plug-circle-bolt" aria-hidden="true"></i> Channel Connectors</h2>
          <p class="smart-marketing__status">Admin control panel</p>
        </header>
        <p class="smart-marketing__hint">Connect ad accounts and messaging profiles to enable automated launches and lead sync.</p>
        <div class="smart-marketing__connectors" data-connector-list></div>
      </article>

      <article class="smart-marketing__card" aria-labelledby="analytics-heading">
        <header class="smart-marketing__card-header">
          <h2 id="analytics-heading"><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Marketing Analytics</h2>
          <p class="smart-marketing__status" data-analytics-updated>Last sync —</p>
        </header>
        <p class="smart-marketing__hint">KPIs refresh directly from connected ad platforms. All numbers in Asia/Kolkata timezone.</p>
        <div class="smart-marketing__toolbar">
          <button type="button" class="btn btn-ghost" data-analytics-refresh><i class="fa-solid fa-rotate" aria-hidden="true"></i> Refresh from connectors</button>
        </div>
        <section class="smart-marketing__analytics" data-analytics-kpis aria-live="polite"></section>
        <section class="smart-marketing__analytics-cohorts" data-analytics-cohorts aria-live="polite"></section>
        <section class="smart-marketing__analytics-funnel" data-analytics-funnel aria-live="polite"></section>
        <section class="smart-marketing__analytics-creatives" data-analytics-creatives aria-live="polite"></section>
        <section class="smart-marketing__analytics-budget" data-analytics-budget aria-live="polite"></section>
        <div class="smart-marketing__alerts" data-analytics-alerts></div>
      </article>

      <article class="smart-marketing__card" aria-labelledby="optimization-heading">
        <header class="smart-marketing__card-header">
          <h2 id="optimization-heading"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Optimization Console</h2>
          <button type="button" class="btn btn-primary" data-optimization-save><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save guardrails</button>
        </header>
        <p class="smart-marketing__hint">Auto rules keep budgets aligned to CPL guardrails. Manual playbooks let you steer experiments.</p>
        <section class="smart-marketing__optimization" data-optimization-auto></section>
        <form class="smart-marketing__optimization-form" data-optimization-manual>
          <fieldset>
            <legend>Manual playbook</legend>
            <label>Action
              <select name="kind" required>
                <option value="promote_creative">Promote winning creative</option>
                <option value="duplicate_campaign">Duplicate campaign</option>
                <option value="schedule_test">Schedule A/B test</option>
              </select>
            </label>
            <label>Details<textarea name="notes" rows="2" placeholder="Context or targeting notes"></textarea></label>
            <div class="smart-marketing__optimization-details" data-optimization-manual-details></div>
            <button type="submit" class="btn btn-ghost"><i class="fa-solid fa-flag-checkered" aria-hidden="true"></i> Log manual action</button>
          </fieldset>
        </form>
        <section class="smart-marketing__optimization-history" data-optimization-history aria-live="polite"></section>
        <section class="smart-marketing__optimization-learning" data-optimization-learning aria-live="polite"></section>
      </article>

      <article class="smart-marketing__card" aria-labelledby="brain-heading">
        <header class="smart-marketing__card-header">
          <h2 id="brain-heading"><i class="fa-solid fa-brain" aria-hidden="true"></i> Marketing Brain</h2>
          <div class="smart-marketing__modes" data-autonomy-mode></div>
        </header>
        <form class="smart-marketing__form" data-brain-form>
          <fieldset>
            <legend>Business goals</legend>
            <div class="smart-marketing__chip-group" data-checkbox-group="goals"></div>
          </fieldset>
          <fieldset>
            <legend>Target regions</legend>
            <textarea name="regions" rows="2" placeholder="Jharkhand, Ranchi, Bokaro" data-regions-input></textarea>
            <p class="smart-marketing__hint">Comma separated list of states, districts, or cities.</p>
          </fieldset>
          <fieldset>
            <legend>Product lines</legend>
            <div class="smart-marketing__chip-group" data-checkbox-group="products"></div>
          </fieldset>
          <fieldset>
            <legend>Languages</legend>
            <div class="smart-marketing__chip-group" data-checkbox-group="languages"></div>
          </fieldset>
          <fieldset class="smart-marketing__budget-group">
            <legend>Budget & guardrails</legend>
            <label>Daily budget (<?= htmlspecialchars($marketingSettings['budget']['currency'] ?? 'INR', ENT_QUOTES) ?>)
              <input type="number" name="daily_budget" min="0" step="100" data-daily-budget />
            </label>
            <label>Monthly cap (<?= htmlspecialchars($marketingSettings['budget']['currency'] ?? 'INR', ENT_QUOTES) ?>)
              <input type="number" name="monthly_budget" min="0" step="100" data-monthly-budget />
            </label>
            <label>Minimum bid
              <input type="number" name="min_bid" min="0" step="1" data-min-bid />
            </label>
            <label>CPA guardrail
              <input type="number" name="cpa_guardrail" min="0" step="1" data-cpa-guardrail />
            </label>
            <label>Autonomy mode
              <select name="autonomy_mode" data-autonomy-select>
                <option value="auto">Auto (launch & optimise)</option>
                <option value="review">Review-before-launch</option>
                <option value="draft">Draft-only</option>
              </select>
            </label>
          </fieldset>
          <fieldset>
            <legend>Compliance</legend>
            <label><input type="checkbox" name="compliance_platform_policy" data-compliance="platform_policy" /> Platform policy checks</label>
            <label><input type="checkbox" name="compliance_brand_tone" data-compliance="brand_tone" /> Brand tone enforcement</label>
            <label><input type="checkbox" name="compliance_legal_disclaimers" data-compliance="legal_disclaimers" /> Legal disclaimers</label>
          </fieldset>
          <label class="smart-marketing__notes">Additional notes
            <textarea name="notes" rows="3" placeholder="Campaign notes, launch windows, remarketing lists" data-notes></textarea>
          </label>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-robot" aria-hidden="true"></i> Generate autonomous plan</button>
          <p class="smart-marketing__error" role="alert" hidden data-brain-error></p>
        </form>
        <section class="smart-marketing__runlog" aria-live="polite" data-brain-runs></section>
      </article>

      <article class="smart-marketing__card" aria-labelledby="builder-heading">
        <header class="smart-marketing__card-header">
          <h2 id="builder-heading"><i class="fa-solid fa-rocket" aria-hidden="true"></i> Campaign Builder</h2>
          <p class="smart-marketing__status">Brain → Launch pipeline</p>
        </header>
        <form class="smart-marketing__campaign-form" data-campaign-form>
          <label>Plan to launch
            <select data-campaign-run>
              <option value="">Select a generated plan</option>
            </select>
          </label>
          <fieldset>
            <legend>Campaign types</legend>
            <div class="smart-marketing__campaign-types" data-campaign-types></div>
          </fieldset>
          <fieldset>
            <legend>Landing destination</legend>
            <label class="smart-marketing__radio"><input type="radio" name="landing_mode" value="existing" data-landing-mode checked /> Use existing site page</label>
            <select data-landing-existing>
              <option value="/contact.html">/contact.html</option>
            </select>
            <label class="smart-marketing__radio"><input type="radio" name="landing_mode" value="auto" data-landing-mode /> Auto-generate Smart landing</label>
            <div class="smart-marketing__landing-fields" data-landing-auto hidden>
              <label>Headline<input type="text" data-landing-headline placeholder="Summer rooftop subsidy spotlight" /></label>
              <label>Offer<input type="text" data-landing-offer placeholder="Free site audit + subsidy paperwork" /></label>
              <label>Primary CTA<input type="text" data-landing-cta placeholder="Book consultation" /></label>
              <label>WhatsApp number<input type="text" data-landing-whatsapp placeholder="+91620XXXXXXX" /></label>
              <label>Call desk<input type="text" data-landing-call placeholder="620-XXXX-XXX" /></label>
            </div>
          </fieldset>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-circle-play" aria-hidden="true"></i> Build &amp; launch</button>
          <p class="smart-marketing__hint">Launched campaigns automatically sync leads to CRM with attribution tags.</p>
        </form>
        <section class="smart-marketing__campaigns" data-campaign-output aria-live="polite"></section>
      </article>

      <article class="smart-marketing__card" aria-labelledby="settings-heading">
        <header class="smart-marketing__card-header">
          <h2 id="settings-heading"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Smart Marketing Settings</h2>
          <p class="smart-marketing__status" data-settings-status aria-live="polite"></p>
        </header>
        <nav class="smart-marketing__tabs" data-settings-tabs>
          <button type="button" data-tab="business">Business Profile</button>
          <button type="button" data-tab="audiences">Audiences</button>
          <button type="button" data-tab="products">Products & Offers</button>
          <button type="button" data-tab="budget">Budget & Pacing</button>
          <button type="button" data-tab="autonomy">Autonomy</button>
          <button type="button" data-tab="legal">Legal & Compliance</button>
          <button type="button" data-tab="integrations">Integrations</button>
        </nav>
        <div class="smart-marketing__tab-panels" data-settings-panels>
          <section data-tab-panel="business">
            <label>Brand name<input type="text" data-setting="businessProfile.brandName" /></label>
            <label>Tagline<input type="text" data-setting="businessProfile.tagline" /></label>
            <label>About<textarea rows="3" data-setting="businessProfile.about"></textarea></label>
            <label>Primary contact<input type="text" data-setting="businessProfile.primaryContact" /></label>
            <label>Support email<input type="email" data-setting="businessProfile.supportEmail" /></label>
            <label>WhatsApp number<input type="text" data-setting="businessProfile.whatsappNumber" /></label>
            <label>Service regions<textarea rows="2" data-setting="businessProfile.serviceRegions" placeholder="Jharkhand, Ranchi"></textarea></label>
          </section>
          <section data-tab-panel="audiences">
            <label>Primary segments<textarea rows="3" data-setting="audiences.primarySegments" placeholder="Segment per line"></textarea></label>
            <label>Remarketing notes<textarea rows="3" data-setting="audiences.remarketingNotes"></textarea></label>
            <label>Exclusions<textarea rows="3" data-setting="audiences.exclusions"></textarea></label>
          </section>
          <section data-tab-panel="products">
            <label>Product portfolio<textarea rows="4" data-setting="products.portfolio" placeholder="One product per line"></textarea></label>
            <label>Offers<textarea rows="3" data-setting="products.offers"></textarea></label>
          </section>
          <section data-tab-panel="budget">
            <label>Daily cap<input type="number" step="100" data-setting="budget.dailyCap" /></label>
            <label>Monthly cap<input type="number" step="100" data-setting="budget.monthlyCap" /></label>
            <label>Minimum bid<input type="number" step="1" data-setting="budget.minBid" /></label>
            <label>Target CPL<input type="number" step="1" data-setting="budget.targetCpl" /></label>
            <label>Currency<input type="text" data-setting="budget.currency" /></label>
          </section>
          <section data-tab-panel="autonomy">
            <label>Default autonomy mode
              <select data-setting="autonomy.mode">
                <option value="auto">Auto</option>
                <option value="review">Review-before-launch</option>
                <option value="draft">Draft-only</option>
              </select>
            </label>
            <label>Review recipients<textarea rows="2" data-setting="autonomy.reviewRecipients" placeholder="Emails or names"></textarea></label>
            <label class="smart-marketing__toggle"><input type="checkbox" data-setting="autonomy.killSwitchEngaged" /> Emergency kill switch engaged</label>
            <button type="button" class="btn btn-danger" data-kill-switch><i class="fa-solid fa-stop-circle" aria-hidden="true"></i> Trigger kill switch</button>
          </section>
          <section data-tab-panel="legal">
            <label><input type="checkbox" data-setting="compliance.policyChecks" /> Platform policy checks</label>
            <label><input type="checkbox" data-setting="compliance.brandTone" /> Brand tone control</label>
            <label><input type="checkbox" data-setting="compliance.legalDisclaimers" /> Legal disclaimers required</label>
            <label>PM Surya Ghar disclaimer<textarea rows="3" data-setting="compliance.pmSuryaDisclaimer"></textarea></label>
            <label>Notes<textarea rows="3" data-setting="compliance.notes"></textarea></label>
          </section>
          <section data-tab-panel="integrations">
            <div class="smart-marketing__integration" data-integration="googleAds">
              <h3>Google Ads</h3>
              <label>Status
                <select data-setting="integrations.googleAds.status">
                  <option value="connected">Connected</option>
                  <option value="warning">Warning</option>
                  <option value="error">Error</option>
                  <option value="unknown">Unknown</option>
                </select>
              </label>
              <label>Account<input type="text" data-setting="integrations.googleAds.account" /></label>
            </div>
            <div class="smart-marketing__integration" data-integration="meta">
              <h3>Meta</h3>
              <label>Status
                <select data-setting="integrations.meta.status">
                  <option value="connected">Connected</option>
                  <option value="warning">Warning</option>
                  <option value="error">Error</option>
                  <option value="unknown">Unknown</option>
                </select>
              </label>
              <label>Account<input type="text" data-setting="integrations.meta.account" /></label>
            </div>
            <div class="smart-marketing__integration" data-integration="email">
              <h3>Email</h3>
              <label>Status
                <select data-setting="integrations.email.status">
                  <option value="connected">Connected</option>
                  <option value="warning">Warning</option>
                  <option value="error">Error</option>
                  <option value="unknown">Unknown</option>
                </select>
              </label>
              <label>Provider<input type="text" data-setting="integrations.email.provider" /></label>
            </div>
            <div class="smart-marketing__integration" data-integration="whatsapp">
              <h3>WhatsApp</h3>
              <label>Status
                <select data-setting="integrations.whatsapp.status">
                  <option value="connected">Connected</option>
                  <option value="warning">Warning</option>
                  <option value="error">Error</option>
                  <option value="unknown">Unknown</option>
                </select>
              </label>
              <label>Number<input type="text" data-setting="integrations.whatsapp.number" /></label>
            </div>
          </section>
        </div>
      </article>

      <article class="smart-marketing__card" aria-labelledby="automation-heading">
        <header class="smart-marketing__card-header">
          <h2 id="automation-heading"><i class="fa-solid fa-gears" aria-hidden="true"></i> Optimisation Automations</h2>
        </header>
        <p class="smart-marketing__hint">Weekly creative refresh, budget shifts, negative keywords, and compliance sweeps log here.</p>
        <button type="button" class="btn btn-ghost" data-run-automations><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> Run optimisation sweep</button>
        <section class="smart-marketing__automation" data-automation-log aria-live="polite"></section>
      </article>

      <article class="smart-marketing__card" aria-labelledby="governance-heading">
        <header class="smart-marketing__card-header">
          <h2 id="governance-heading"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Governance &amp; Safety</h2>
        </header>
        <section class="smart-marketing__governance" data-governance-controls>
          <div>
            <h3>Budget lock</h3>
            <label><input type="checkbox" data-governance-budget-lock /> Enforce monthly cap</label>
            <label>Monthly cap<input type="number" min="0" step="1000" data-governance-budget-cap /></label>
            <button type="button" class="btn btn-ghost" data-governance-save-budget><i class="fa-solid fa-vault" aria-hidden="true"></i> Update lock</button>
          </div>
          <div>
            <h3>Policy checklist</h3>
            <ul data-governance-policy></ul>
            <button type="button" class="btn btn-ghost" data-governance-save-policy><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Save checklist</button>
          </div>
          <div>
            <h3>Autonomy controls</h3>
            <button type="button" class="btn btn-danger" data-governance-emergency><i class="fa-solid fa-stop" aria-hidden="true"></i> Emergency stop</button>
            <p class="smart-marketing__hint smart-marketing__emergency-status" data-governance-emergency-status aria-live="polite"></p>
            <button type="button" class="btn btn-ghost" data-governance-export><i class="fa-solid fa-file-export" aria-hidden="true"></i> Export logs</button>
            <button type="button" class="btn btn-ghost" data-governance-erase><i class="fa-solid fa-trash" aria-hidden="true"></i> Queue erasure</button>
          </div>
        </section>
        <section class="smart-marketing__governance-log" data-governance-log aria-live="polite"></section>
      </article>

      <article class="smart-marketing__card" aria-labelledby="notifications-heading">
        <header class="smart-marketing__card-header">
          <h2 id="notifications-heading"><i class="fa-solid fa-bell" aria-hidden="true"></i> Notifications</h2>
        </header>
        <form class="smart-marketing__notifications" data-notifications-form>
          <fieldset>
            <legend>Daily digest</legend>
            <label><input type="checkbox" data-notifications-digest-enabled /> Send digest</label>
            <label>Send at<input type="time" data-notifications-digest-time /></label>
            <label>Email<input type="email" data-notifications-digest-email placeholder="ops@dakshayani.in" /></label>
            <label>WhatsApp<input type="text" data-notifications-digest-whatsapp placeholder="+91-" /></label>
          </fieldset>
          <fieldset>
            <legend>Instant alerts</legend>
            <label><input type="checkbox" data-notifications-instant-email /> Email alerts</label>
            <label><input type="checkbox" data-notifications-instant-whatsapp /> WhatsApp alerts</label>
          </fieldset>
          <div class="smart-marketing__toolbar">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save notifications</button>
            <button type="button" class="btn btn-ghost" data-notifications-test><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send test</button>
          </div>
        </form>
        <section class="smart-marketing__notifications-log" data-notifications-log aria-live="polite"></section>
      </article>

      <article class="smart-marketing__card" aria-labelledby="creative-heading">
        <header class="smart-marketing__card-header">
          <h2 id="creative-heading"><i class="fa-solid fa-palette" aria-hidden="true"></i> Creative Services</h2>
        </header>
        <div class="smart-marketing__creative" data-creative-suite>
          <section>
            <h3><i class="fa-solid fa-pen-nib" aria-hidden="true"></i> Text</h3>
            <label>Category
              <select data-creative-text-category>
                <option value="Ad headlines">Ad headlines</option>
                <option value="Primary text">Primary ad text</option>
                <option value="Descriptions">Descriptions</option>
                <option value="Captions">Social captions</option>
                <option value="Email body">Email body copy</option>
                <option value="SMS copy">SMS copy</option>
              </select>
            </label>
            <label>Brief<textarea rows="3" data-creative-text-brief placeholder="Key offer, CTA, tone"></textarea></label>
            <button type="button" class="btn btn-primary" data-generate-text><i class="fa-solid fa-magic" aria-hidden="true"></i> Generate text</button>
            <div class="smart-marketing__stream" data-text-stream></div>
          </section>
          <section>
            <h3><i class="fa-solid fa-image" aria-hidden="true"></i> Image</h3>
            <label>Prompt<textarea rows="3" data-creative-image-prompt placeholder="Describe the visual"></textarea></label>
            <label>Channel preset
              <select data-creative-image-preset>
                <option value="google-landscape">Google Ads landscape (1200x628)</option>
                <option value="meta-square">Meta square (1080x1080)</option>
                <option value="youtube-thumbnail">YouTube thumbnail (1280x720)</option>
              </select>
            </label>
            <button type="button" class="btn btn-primary" data-generate-image><i class="fa-solid fa-image" aria-hidden="true"></i> Generate image</button>
            <div class="smart-marketing__stream" data-image-stream></div>
          </section>
          <section>
            <h3><i class="fa-solid fa-microphone-lines" aria-hidden="true"></i> TTS</h3>
            <label>Script<textarea rows="3" data-creative-tts-script placeholder="30s voice-over script"></textarea></label>
            <button type="button" class="btn btn-primary" data-generate-tts><i class="fa-solid fa-headphones" aria-hidden="true"></i> Generate voice-over</button>
            <div class="smart-marketing__stream" data-tts-stream></div>
          </section>
        </div>
        <section class="smart-marketing__assets" aria-live="polite" data-assets-list></section>
      </article>
    </section>
  </main>

  <script src="admin-smart-marketing.js" defer></script>
</body>
</html>

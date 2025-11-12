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
$settingsSections = [];
foreach (array_keys(smart_marketing_settings_sections()) as $sectionKey) {
    $settingsSections[$sectionKey] = smart_marketing_settings_section_read($sectionKey);
}
$settingsSectionsMasked = smart_marketing_settings_mask_sections($settingsSections);
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
$settingsAuditTrail = smart_marketing_settings_audit_log();
$connectorCatalog = smart_marketing_connector_catalog();
$integrationsAudit = smart_marketing_integrations_audit_read();

$connectorFieldHelp = [
    'meta' => [
        'businessManagerId' => [
            'Business Manager ID → Go to Business Settings → Business Info → ID',
        ],
        'adAccountId' => [
            'Ad Account ID → Ads Manager → Account Dropdown → ID below name',
        ],
        'pageId' => [
            'Page ID → Facebook Page → About section → Page ID',
        ],
        'appId' => [
            'App ID → Meta Developers → Select your app → Dashboard',
        ],
        'appSecret' => [
            'App Secret → Meta Developers → Your App → Settings → Basic',
        ],
        'systemUserToken' => [
            'System User Token → Business Settings → Users → System Users → Generate Token',
        ],
        'pixelId' => [
            'Pixel ID → Events Manager → Data Sources → Pixels → ID column',
        ],
        'whatsappNumberId' => [
            'WhatsApp Number ID → WhatsApp Manager → Numbers → ID column',
        ],
    ],
    'googleAds' => [
        'managerId' => [
            'Customer ID → Top right of Ads Manager',
        ],
        'oauthClientId' => [
            'OAuth Client ID → Google Cloud Console → Credentials → OAuth client',
        ],
        'oauthClientSecret' => [
            'OAuth Client Secret → Google Cloud Console → Credentials → OAuth client',
        ],
        'refreshToken' => [
            'Refresh Token → Generated via OAuth consent flow for the Google Ads app',
        ],
        'developerToken' => [
            'Developer Token → Google Ads Manager → Tools & Settings → API Center',
        ],
        'conversionTrackingId' => [
            'Conversion Tracking ID → Tools & Settings → Measurement → Conversions',
        ],
        'linkedYoutubeChannelId' => [
            'YouTube Channel ID → YouTube Studio → Settings → Advanced Settings',
        ],
    ],
    'whatsapp' => [
        'wabaId' => [
            'WABA ID → WhatsApp Manager → Overview',
        ],
        'phoneNumberId' => [
            'Phone Number ID → WhatsApp Manager → Numbers → ID column',
        ],
        'accessToken' => [
            'Access Token → WhatsApp Cloud API → Configuration tab',
        ],
        'bspName' => [
            'BSP Name → Provided by your Business Solution Provider account manager',
        ],
        'bspKey' => [
            'BSP Key → Issued by your Business Solution Provider',
        ],
        'templateNamespace' => [
            'Template Namespace → WhatsApp Manager → Message Templates → Namespace',
        ],
        'adminSandboxNumber' => [
            'Admin Sandbox Number → WhatsApp Cloud API → Sandbox panel',
        ],
    ],
    'email' => [
        'provider' => [
            'Provider → Choose the delivery service configured for Smart Marketing',
        ],
        'apiKey' => [
            'API Key → Provider dashboard → API keys',
        ],
        'senderId' => [
            'Sender ID / From Email → Provider dashboard → Sender identities',
        ],
        'smtpHost' => [
            'SMTP Host → Provider dashboard → SMTP settings',
        ],
        'smtpPort' => [
            'SMTP Port → Provider dashboard → SMTP settings',
        ],
        'smtpUsername' => [
            'SMTP Username → Provider dashboard → SMTP settings',
        ],
        'smtpPassword' => [
            'SMTP Password → Provider dashboard → SMTP settings',
        ],
        'sandboxRecipient' => [
            'Sandbox Recipient → Test inbox or phone number for dry runs',
        ],
    ],
];
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
                $targetSection = (string) ($payload['section'] ?? '');
                if ($targetSection !== '') {
                    $updates = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
                    $result = smart_marketing_settings_save_section($targetSection, $updates, $admin, $aiSettings);
                    if (!$result['ok']) {
                        $response = [
                            'ok' => false,
                            'section' => $result['section'],
                            'messages' => $result['messages'],
                            'data' => smart_marketing_settings_mask_section($result['section'], $result['data']),
                            'audit' => smart_marketing_settings_audit_log(),
                        ];
                        break;
                    }

                    $marketingSettings = $result['settings'];
                    $settingsSections = $result['sections'];
                    $settingsSectionsMasked = smart_marketing_settings_mask_sections($settingsSections);
                    $response = [
                        'ok' => true,
                        'section' => $result['section'],
                        'messages' => $result['messages'],
                        'data' => smart_marketing_settings_mask_section($result['section'], $result['data']),
                        'settings' => smart_marketing_settings_redact($marketingSettings),
                        'sections' => $settingsSectionsMasked,
                        'aiHealth' => smart_marketing_ai_health($aiSettings),
                        'integrations' => smart_marketing_integrations_health($marketingSettings),
                        'connectors' => smart_marketing_channel_connectors($marketingSettings),
                        'audit' => $result['audit'],
                        'integrationsAudit' => smart_marketing_integrations_audit_read(),
                    ];
                    break;
                }
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
                $settingsSections = [];
                foreach (array_keys(smart_marketing_settings_sections()) as $sectionKey) {
                    $settingsSections[$sectionKey] = smart_marketing_settings_section_read($sectionKey);
                }
                $settingsSectionsMasked = smart_marketing_settings_mask_sections($settingsSections);
                $response = [
                    'ok' => true,
                    'settings' => smart_marketing_settings_redact($marketingSettings),
                    'sections' => $settingsSectionsMasked,
                    'aiHealth' => smart_marketing_ai_health($aiSettings),
                    'integrations' => smart_marketing_integrations_health($marketingSettings),
                    'audit' => smart_marketing_audit_log_read(),
                    'connectors' => smart_marketing_channel_connectors($marketingSettings),
                    'integrationsAudit' => smart_marketing_integrations_audit_read(),
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

            case 'revert-settings':
                $targetSection = (string) ($payload['section'] ?? '');
                if ($targetSection === '') {
                    throw new RuntimeException('Section is required.');
                }

                $result = smart_marketing_settings_revert_section($targetSection);
                $marketingSettings = $result['settings'];
                $settingsSections = $result['sections'];
                $settingsSectionsMasked = smart_marketing_settings_mask_sections($settingsSections);
                $response = [
                    'ok' => true,
                    'section' => $result['section'],
                    'data' => smart_marketing_settings_mask_section($result['section'], $result['data']),
                    'settings' => smart_marketing_settings_redact($marketingSettings),
                    'sections' => $settingsSectionsMasked,
                    'aiHealth' => smart_marketing_ai_health($aiSettings),
                    'integrations' => smart_marketing_integrations_health($marketingSettings),
                    'connectors' => smart_marketing_channel_connectors($marketingSettings),
                    'audit' => $result['audit'],
                    'integrationsAudit' => smart_marketing_integrations_audit_read(),
                ];
                break;

            case 'test-settings':
                $targetSection = (string) ($payload['section'] ?? '');
                if ($targetSection === '') {
                    throw new RuntimeException('Section is required.');
                }

                $updates = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
                $result = smart_marketing_settings_test_section($targetSection, $updates, $aiSettings);
                $response = [
                    'ok' => $result['ok'],
                    'section' => $result['section'],
                    'messages' => $result['messages'],
                    'data' => smart_marketing_settings_mask_section($result['section'], $result['data']),
                    'settings' => smart_marketing_settings_redact($result['settings']),
                    'sections' => smart_marketing_settings_mask_sections($result['sections']),
                    'aiHealth' => smart_marketing_ai_health($aiSettings),
                    'integrations' => smart_marketing_integrations_health($marketingSettings),
                    'connectors' => smart_marketing_channel_connectors($marketingSettings),
                    'integrationsAudit' => smart_marketing_integrations_audit_read(),
                ];
                break;

            case 'sync-business-profile':
                $profileSection = $settingsSections['business'] ?? smart_marketing_settings_section_defaults('business');
                $companyName = trim((string) ($admin['company'] ?? ($admin['company_name'] ?? ($admin['organisation'] ?? ($admin['organization'] ?? '')))));
                if ($companyName !== '') {
                    $profileSection['companyName'] = $companyName;
                }
                $profileSection['autoSync']['lastSyncedAt'] = ai_timestamp();
                $payloadProfile = $profileSection;
                $result = smart_marketing_settings_save_section('business', $payloadProfile, $admin, $aiSettings);
                if (!$result['ok']) {
                    $response = [
                        'ok' => false,
                        'section' => 'business',
                        'messages' => $result['messages'],
                        'data' => smart_marketing_settings_mask_section('business', $result['data']),
                    ];
                    break;
                }

                $marketingSettings = $result['settings'];
                $settingsSections = $result['sections'];
                $settingsSectionsMasked = smart_marketing_settings_mask_sections($settingsSections);
                $response = [
                    'ok' => true,
                    'section' => 'business',
                    'data' => smart_marketing_settings_mask_section('business', $result['data']),
                    'settings' => smart_marketing_settings_redact($marketingSettings),
                    'sections' => $settingsSectionsMasked,
                    'messages' => array_merge(['Synced from admin profile.'], $result['messages']),
                    'aiHealth' => smart_marketing_ai_health($aiSettings),
                    'integrations' => smart_marketing_integrations_health($marketingSettings),
                    'connectors' => smart_marketing_channel_connectors($marketingSettings),
                    'audit' => $result['audit'],
                    'integrationsAudit' => smart_marketing_integrations_audit_read(),
                ];
                break;

            case 'reload-settings':
                $settingsSections = [];
                foreach (array_keys(smart_marketing_settings_sections()) as $sectionKey) {
                    $settingsSections[$sectionKey] = smart_marketing_settings_section_read($sectionKey);
                }
                $marketingSettings = smart_marketing_settings_hydrate_legacy($settingsSections);
                $settingsSectionsMasked = smart_marketing_settings_mask_sections($settingsSections);
                $response = [
                    'ok' => true,
                    'settings' => smart_marketing_settings_redact($marketingSettings),
                    'sections' => $settingsSectionsMasked,
                    'aiHealth' => smart_marketing_ai_health($aiSettings),
                    'integrations' => smart_marketing_integrations_health($marketingSettings),
                    'connectors' => smart_marketing_channel_connectors($marketingSettings),
                    'audit' => smart_marketing_settings_audit_log(),
                    'integrationsAudit' => smart_marketing_integrations_audit_read(),
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
                $entry = smart_marketing_connector_connect($marketingSettings, $connectorKey, $fields, $admin);
                smart_marketing_settings_save($marketingSettings);
                smart_marketing_audit_log_append('connector.connected', ['connector' => $connectorKey], $admin);
                smart_marketing_integrations_audit_append($connectorKey, 'save', $admin, $entry, $entry['message'] ?? null);
                $marketingSettings = smart_marketing_settings_load();
                $response = [
                    'ok' => true,
                    'connector' => smart_marketing_settings_mask_section('integrations', ['channels' => [$connectorKey => $entry]])['channels'][$connectorKey],
                    'settings' => smart_marketing_settings_redact($marketingSettings),
                    'integrations' => smart_marketing_integrations_health($marketingSettings),
                    'connectors' => smart_marketing_channel_connectors($marketingSettings),
                    'audit' => smart_marketing_audit_log_read(),
                    'integrationsAudit' => smart_marketing_integrations_audit_read(),
                ];
                break;

            case 'connector-test':
                $connectorKey = (string) ($payload['connector'] ?? '');
                $entry = smart_marketing_connector_test($marketingSettings, $connectorKey, $admin);
                smart_marketing_settings_save($marketingSettings);
                smart_marketing_audit_log_append('connector.tested', ['connector' => $connectorKey, 'result' => $entry['lastTestResult'] ?? 'unknown'], $admin);
                smart_marketing_integrations_audit_append($connectorKey, 'test', $admin, $entry, $entry['message'] ?? null);
                $marketingSettings = smart_marketing_settings_load();
                $response = [
                    'ok' => $entry['status'] === 'connected',
                    'connector' => smart_marketing_settings_mask_section('integrations', ['channels' => [$connectorKey => $entry]])['channels'][$connectorKey],
                    'settings' => smart_marketing_settings_redact($marketingSettings),
                    'integrations' => smart_marketing_integrations_health($marketingSettings),
                    'connectors' => smart_marketing_channel_connectors($marketingSettings),
                    'audit' => smart_marketing_audit_log_read(),
                    'integrationsAudit' => smart_marketing_integrations_audit_read(),
                ];
                break;

            case 'integration-save':
                $platform = (string) ($payload['platform'] ?? '');
                if ($platform === '') {
                    throw new RuntimeException('Integration platform is required.');
                }
                $credentials = is_array($payload['credentials'] ?? null) ? $payload['credentials'] : [];
                $entry = smart_marketing_connector_connect($marketingSettings, $platform, $credentials, $admin);
                smart_marketing_settings_save($marketingSettings);
                smart_marketing_integrations_audit_append($platform, 'save', $admin, $entry, $entry['message'] ?? null);
                smart_marketing_audit_log_append('connector.connected', ['connector' => $platform], $admin);
                $marketingSettings = smart_marketing_settings_load();
                $integrationsHealth = smart_marketing_integrations_health($marketingSettings);
                $connectors = smart_marketing_channel_connectors($marketingSettings);
                $settingsSections = [];
                foreach (array_keys(smart_marketing_settings_sections()) as $sectionKey) {
                    $settingsSections[$sectionKey] = smart_marketing_settings_section_read($sectionKey);
                }
                $settingsSectionsMasked = smart_marketing_settings_mask_sections($settingsSections);
                $response = [
                    'ok' => true,
                    'platform' => $platform,
                    'messages' => [$entry['message'] ?? 'Connected Successfully ✅'],
                    'integration' => smart_marketing_settings_mask_section('integrations', ['channels' => [$platform => $entry]])['channels'][$platform],
                    'settings' => smart_marketing_settings_redact($marketingSettings),
                    'sections' => $settingsSectionsMasked,
                    'integrations' => $integrationsHealth,
                    'connectors' => $connectors,
                    'audit' => smart_marketing_audit_log_read(),
                    'integrationsAudit' => smart_marketing_integrations_audit_read(),
                ];
                break;

            case 'integration-test':
                $platform = (string) ($payload['platform'] ?? '');
                if ($platform === '') {
                    throw new RuntimeException('Integration platform is required.');
                }
                $entry = smart_marketing_connector_test($marketingSettings, $platform, $admin);
                smart_marketing_settings_save($marketingSettings);
                smart_marketing_integrations_audit_append($platform, 'test', $admin, $entry, $entry['message'] ?? null);
                $marketingSettings = smart_marketing_settings_load();
                $integrationsHealth = smart_marketing_integrations_health($marketingSettings);
                $connectors = smart_marketing_channel_connectors($marketingSettings);
                $settingsSections = [];
                foreach (array_keys(smart_marketing_settings_sections()) as $sectionKey) {
                    $settingsSections[$sectionKey] = smart_marketing_settings_section_read($sectionKey);
                }
                $settingsSectionsMasked = smart_marketing_settings_mask_sections($settingsSections);
                $response = [
                    'ok' => $entry['status'] === 'connected',
                    'platform' => $platform,
                    'messages' => [$entry['message'] ?? ($entry['status'] === 'connected' ? 'Validation passed' : 'Validation failed')],
                    'integration' => smart_marketing_settings_mask_section('integrations', ['channels' => [$platform => $entry]])['channels'][$platform],
                    'settings' => smart_marketing_settings_redact($marketingSettings),
                    'sections' => $settingsSectionsMasked,
                    'integrations' => $integrationsHealth,
                    'connectors' => $connectors,
                    'audit' => smart_marketing_audit_log_read(),
                    'integrationsAudit' => smart_marketing_integrations_audit_read(),
                ];
                break;

            case 'integration-disable':
                $platform = (string) ($payload['platform'] ?? '');
                if ($platform === '') {
                    throw new RuntimeException('Integration platform is required.');
                }
                $entry = smart_marketing_connector_disable($marketingSettings, $platform, $admin);
                smart_marketing_settings_save($marketingSettings);
                smart_marketing_integrations_audit_append($platform, 'disable', $admin, $entry, 'Disabled by admin');
                $marketingSettings = smart_marketing_settings_load();
                $integrationsHealth = smart_marketing_integrations_health($marketingSettings);
                $connectors = smart_marketing_channel_connectors($marketingSettings);
                $settingsSections = [];
                foreach (array_keys(smart_marketing_settings_sections()) as $sectionKey) {
                    $settingsSections[$sectionKey] = smart_marketing_settings_section_read($sectionKey);
                }
                $settingsSectionsMasked = smart_marketing_settings_mask_sections($settingsSections);
                $response = [
                    'ok' => true,
                    'platform' => $platform,
                    'messages' => [$entry['message'] ?? 'Integration disabled'],
                    'integration' => smart_marketing_settings_mask_section('integrations', ['channels' => [$platform => $entry]])['channels'][$platform],
                    'settings' => smart_marketing_settings_redact($marketingSettings),
                    'sections' => $settingsSectionsMasked,
                    'integrations' => $integrationsHealth,
                    'connectors' => $connectors,
                    'audit' => smart_marketing_audit_log_read(),
                    'integrationsAudit' => smart_marketing_integrations_audit_read(),
                ];
                break;

            case 'integration-delete':
                $platform = (string) ($payload['platform'] ?? '');
                if ($platform === '') {
                    throw new RuntimeException('Integration platform is required.');
                }
                $entry = smart_marketing_connector_delete($marketingSettings, $platform, $admin);
                smart_marketing_settings_save($marketingSettings);
                smart_marketing_integrations_audit_append($platform, 'delete', $admin, $entry, 'Credentials removed');
                $marketingSettings = smart_marketing_settings_load();
                $integrationsHealth = smart_marketing_integrations_health($marketingSettings);
                $connectors = smart_marketing_channel_connectors($marketingSettings);
                $settingsSections = [];
                foreach (array_keys(smart_marketing_settings_sections()) as $sectionKey) {
                    $settingsSections[$sectionKey] = smart_marketing_settings_section_read($sectionKey);
                }
                $settingsSectionsMasked = smart_marketing_settings_mask_sections($settingsSections);
                $response = [
                    'ok' => true,
                    'platform' => $platform,
                    'messages' => ['Credentials deleted'],
                    'integration' => smart_marketing_settings_mask_section('integrations', ['channels' => [$platform => $entry]])['channels'][$platform],
                    'settings' => smart_marketing_settings_redact($marketingSettings),
                    'sections' => $settingsSectionsMasked,
                    'integrations' => $integrationsHealth,
                    'connectors' => $connectors,
                    'audit' => smart_marketing_audit_log_read(),
                    'integrationsAudit' => smart_marketing_integrations_audit_read(),
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
    'settings' => smart_marketing_settings_redact($marketingSettings),
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
    'settingsSections' => $settingsSectionsMasked,
    'settingsAudit' => $settingsAuditTrail,
    'connectorCatalog' => $connectorCatalog,
    'integrationsAudit' => $integrationsAudit,
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
    <div class="smart-marketing__integration-bar" data-integration-bar aria-live="polite"></div>
    <header class="smart-marketing__header">
      <div>
        <p class="smart-marketing__subtitle">Admin smart automation suite</p>
        <h1 class="smart-marketing__title">Smart Marketing</h1>
        <p class="smart-marketing__meta">Signed in as <strong><?= htmlspecialchars($admin['full_name'] ?? 'Administrator', ENT_QUOTES) ?></strong></p>
      </div>
      <div class="smart-marketing__actions">
        <button type="button" class="btn btn-secondary" data-start-guide-open>
          <i class="fa-solid fa-compass" aria-hidden="true"></i> Start Guide
        </button>
        <a href="admin-dashboard.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Overview</a>
        <a href="logout.php" class="btn btn-primary"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Log out</a>
      </div>
    </header>

    <div class="smart-marketing__banner" data-integrations-banner role="status" aria-live="polite" hidden>
      <div class="smart-marketing__banner-content">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <p data-integrations-banner-message></p>
      </div>
      <button type="button" class="btn btn-ghost" data-integrations-banner-action>
        <i class="fa-solid fa-plug-circle-exclamation" aria-hidden="true"></i> Review integrations
      </button>
    </div>

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
          <h2 id="analytics-heading" tabindex="-1"><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Marketing Analytics</h2>
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
          <h2 id="brain-heading" tabindex="-1"><i class="fa-solid fa-brain" aria-hidden="true"></i> Marketing Brain</h2>
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
        <div class="smart-settings" data-settings-root>
          <article class="smart-settings__section" data-settings-section="business">
            <header class="smart-settings__section-header">
              <button type="button" class="smart-settings__toggle" data-settings-toggle="business" aria-expanded="true">
                <span class="smart-settings__title">Business Profile</span>
                <span class="smart-settings__chips" data-settings-summary="business"></span>
              </button>
              <p class="smart-settings__updated" data-settings-updated="business"></p>
            </header>
            <div class="smart-settings__body" data-settings-body="business">
              <div class="smart-settings__alert" data-settings-alert="business" role="alert" hidden></div>
              <form class="smart-settings__form" data-settings-form="business">
                <div class="smart-settings__grid">
                  <label>Company name
                    <input type="text" data-setting-field="companyName" placeholder="Dakshayani Enterprises" />
                  </label>
                  <label>Brand tone
                    <select data-setting-field="brandTone">
                      <option value="friendly">Friendly</option>
                      <option value="professional">Professional</option>
                      <option value="government-aligned">Government-aligned</option>
                      <option value="aggressive">Aggressive Sales</option>
                    </select>
                  </label>
                  <label>Time zone
                    <select data-setting-field="timeZone">
                      <option value="Asia/Kolkata">Asia/Kolkata (IST)</option>
                      <option value="Asia/Calcutta">Asia/Calcutta (legacy)</option>
                    </select>
                  </label>
                </div>
                <fieldset class="smart-settings__fieldset">
                  <legend>Default languages</legend>
                  <label class="smart-settings__checkbox"><input type="checkbox" value="English" data-setting-field="defaultLanguages" data-setting-type="array" /> English</label>
                  <label class="smart-settings__checkbox"><input type="checkbox" value="Hindi" data-setting-field="defaultLanguages" data-setting-type="array" /> Hindi</label>
                  <label class="smart-settings__checkbox"><input type="checkbox" value="Hinglish" data-setting-field="defaultLanguages" data-setting-type="array" /> Hinglish</label>
                </fieldset>
                <label>Base locations
                  <textarea rows="2" data-setting-field="baseLocations" data-setting-type="tags" placeholder="Jharkhand, Ranchi, Bokaro"></textarea>
                </label>
                <label>Brand summary (optional)
                  <textarea rows="3" data-setting-field="summary" placeholder="Brand positioning, elevator pitch"></textarea>
                </label>
                <div class="smart-settings__actions">
                  <button type="button" class="btn btn-primary" data-settings-save="business"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save</button>
                  <button type="button" class="btn btn-ghost" data-settings-revert="business"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Revert</button>
                  <button type="button" class="btn btn-ghost" data-settings-test="business"><i class="fa-solid fa-stethoscope" aria-hidden="true"></i> Test</button>
                  <button type="button" class="btn btn-ghost" data-settings-sync="business"><i class="fa-solid fa-cloud-arrow-down" aria-hidden="true"></i> Auto-sync profile</button>
                </div>
              </form>
              <div class="smart-settings__history" data-settings-history="business"></div>
            </div>
          </article>

          <article class="smart-settings__section" data-settings-section="goals">
            <header class="smart-settings__section-header">
              <button type="button" class="smart-settings__toggle" data-settings-toggle="goals" aria-expanded="false">
                <span class="smart-settings__title">Goals &amp; Strategy</span>
                <span class="smart-settings__chips" data-settings-summary="goals"></span>
              </button>
              <p class="smart-settings__updated" data-settings-updated="goals"></p>
            </header>
            <div class="smart-settings__body" data-settings-body="goals" hidden>
              <div class="smart-settings__alert" data-settings-alert="goals" role="alert" hidden></div>
              <form class="smart-settings__form" data-settings-form="goals">
                <label>Goal type
                  <select data-setting-field="goalType">
                    <option value="Leads">Leads</option>
                    <option value="Awareness">Awareness</option>
                    <option value="Remarketing">Remarketing</option>
                    <option value="Retention">Retention</option>
                    <option value="AMC Renewal">AMC Renewal</option>
                    <option value="Offer Blast">Offer Blast</option>
                  </select>
                </label>
                <fieldset class="smart-settings__fieldset">
                  <legend>Target products</legend>
                  <label class="smart-settings__checkbox"><input type="checkbox" value="Rooftop 1 kW" data-setting-field="targetProducts" data-setting-type="array" /> Rooftop 1 kW</label>
                  <label class="smart-settings__checkbox"><input type="checkbox" value="Rooftop 3 kW" data-setting-field="targetProducts" data-setting-type="array" /> Rooftop 3 kW</label>
                  <label class="smart-settings__checkbox"><input type="checkbox" value="Rooftop 5 kW" data-setting-field="targetProducts" data-setting-type="array" /> Rooftop 5 kW</label>
                  <label class="smart-settings__checkbox"><input type="checkbox" value="Rooftop 10 kW" data-setting-field="targetProducts" data-setting-type="array" /> Rooftop 10 kW</label>
                  <label class="smart-settings__checkbox"><input type="checkbox" value="C&I 20-50 kW" data-setting-field="targetProducts" data-setting-type="array" /> C&amp;I 20–50 kW</label>
                  <label class="smart-settings__checkbox"><input type="checkbox" value="Off-grid" data-setting-field="targetProducts" data-setting-type="array" /> Off-grid</label>
                  <label class="smart-settings__checkbox"><input type="checkbox" value="Hybrid" data-setting-field="targetProducts" data-setting-type="array" /> Hybrid</label>
                  <label class="smart-settings__checkbox"><input type="checkbox" value="PM Surya Ghar" data-setting-field="targetProducts" data-setting-type="array" /> PM Surya Ghar</label>
                  <label class="smart-settings__checkbox"><input type="checkbox" value="Non-Scheme" data-setting-field="targetProducts" data-setting-type="array" /> Non-Scheme</label>
                </fieldset>
                <fieldset class="smart-settings__fieldset">
                  <legend>Core focus</legend>
                  <label class="smart-settings__radio"><input type="radio" name="goals_focus" value="Residential" data-setting-field="coreFocus" /> Residential</label>
                  <label class="smart-settings__radio"><input type="radio" name="goals_focus" value="Institutional" data-setting-field="coreFocus" /> Institutional</label>
                  <label class="smart-settings__radio"><input type="radio" name="goals_focus" value="Industrial" data-setting-field="coreFocus" /> Industrial</label>
                </fieldset>
                <label>Offer messaging
                  <textarea rows="3" data-setting-field="offerMessaging" placeholder="Ad headline seed, offer promise"></textarea>
                </label>
                <div class="smart-settings__grid smart-settings__grid--compact">
                  <label>Start date
                    <input type="date" data-setting-field="campaignDuration.start" />
                  </label>
                  <label>End date
                    <input type="date" data-setting-field="campaignDuration.end" />
                  </label>
                </div>
                <fieldset class="smart-settings__fieldset">
                  <legend>Autonomy mode</legend>
                  <label class="smart-settings__radio"><input type="radio" name="goals_autonomy" value="auto" data-setting-field="autonomyMode" /> Auto — full AI control</label>
                  <label class="smart-settings__radio"><input type="radio" name="goals_autonomy" value="review" data-setting-field="autonomyMode" /> Review-before-launch</label>
                  <label class="smart-settings__radio"><input type="radio" name="goals_autonomy" value="draft" data-setting-field="autonomyMode" /> Draft-only</label>
                </fieldset>
                <div class="smart-settings__actions">
                  <button type="button" class="btn btn-primary" data-settings-save="goals"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save</button>
                  <button type="button" class="btn btn-ghost" data-settings-revert="goals"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Revert</button>
                  <button type="button" class="btn btn-ghost" data-settings-test="goals"><i class="fa-solid fa-stethoscope" aria-hidden="true"></i> Test</button>
                </div>
              </form>
              <div class="smart-settings__history" data-settings-history="goals"></div>
            </div>
          </article>

          <article class="smart-settings__section" data-settings-section="budget">
            <header class="smart-settings__section-header">
              <button type="button" class="smart-settings__toggle" data-settings-toggle="budget" aria-expanded="false">
                <span class="smart-settings__title">Budget &amp; Pacing</span>
                <span class="smart-settings__chips" data-settings-summary="budget"></span>
              </button>
              <p class="smart-settings__updated" data-settings-updated="budget"></p>
            </header>
            <div class="smart-settings__body" data-settings-body="budget" hidden>
              <div class="smart-settings__alert" data-settings-alert="budget" role="alert" hidden></div>
              <form class="smart-settings__form" data-settings-form="budget">
                <div class="smart-settings__grid smart-settings__grid--compact">
                  <label>Daily budget (₹)
                    <input type="number" min="0" step="100" data-setting-field="dailyBudget" data-setting-type="number" />
                  </label>
                  <label>Monthly cap (₹)
                    <input type="number" min="0" step="100" data-setting-field="monthlyCap" data-setting-type="number" />
                  </label>
                  <label>Currency
                    <select data-setting-field="currency">
                      <option value="INR">INR</option>
                      <option value="USD">USD</option>
                    </select>
                  </label>
                </div>
                <fieldset class="smart-settings__fieldset">
                  <legend>Platform split (%)</legend>
                  <div class="smart-settings__grid smart-settings__grid--compact">
                    <label>Meta
                      <input type="number" min="0" max="100" step="1" data-setting-field="platformSplit.meta" data-setting-type="number" />
                    </label>
                    <label>Google
                      <input type="number" min="0" max="100" step="1" data-setting-field="platformSplit.google" data-setting-type="number" />
                    </label>
                    <label>YouTube
                      <input type="number" min="0" max="100" step="1" data-setting-field="platformSplit.youtube" data-setting-type="number" />
                    </label>
                    <label>WhatsApp
                      <input type="number" min="0" max="100" step="1" data-setting-field="platformSplit.whatsapp" data-setting-type="number" />
                    </label>
                    <label>Email/SMS
                      <input type="number" min="0" max="100" step="1" data-setting-field="platformSplit.emailSms" data-setting-type="number" />
                    </label>
                  </div>
                </fieldset>
                <label>Bid strategy
                  <select data-setting-field="bidStrategy">
                    <option value="cpc">CPC</option>
                    <option value="cpl">CPL</option>
                    <option value="max conversions">Max Conversions</option>
                  </select>
                </label>
                <label class="smart-settings__toggle">
                  <input type="checkbox" data-setting-field="autoScaling" data-setting-type="boolean" /> Auto-scaling (allow AI to redistribute spend daily)
                </label>
                <div class="smart-settings__meter">
                  <p>Today's spend <strong data-settings-budget-spend>—</strong></p>
                  <p>Remaining cap <strong data-settings-budget-remaining>—</strong></p>
                </div>
                <div class="smart-settings__inline-actions">
                  <button type="button" class="btn btn-danger" data-governance-emergency><i class="fa-solid fa-stop" aria-hidden="true"></i> Emergency stop</button>
                  <p class="smart-settings__hint">Emergency stop halts all live campaigns instantly.</p>
                </div>
                <div class="smart-settings__actions">
                  <button type="button" class="btn btn-primary" data-settings-save="budget"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save</button>
                  <button type="button" class="btn btn-ghost" data-settings-revert="budget"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Revert</button>
                  <button type="button" class="btn btn-ghost" data-settings-test="budget"><i class="fa-solid fa-stethoscope" aria-hidden="true"></i> Test</button>
                </div>
              </form>
              <div class="smart-settings__history" data-settings-history="budget"></div>
            </div>
          </article>

          <article class="smart-settings__section" data-settings-section="audience">
            <header class="smart-settings__section-header">
              <button type="button" class="smart-settings__toggle" data-settings-toggle="audience" aria-expanded="false">
                <span class="smart-settings__title">Audience &amp; Targeting</span>
                <span class="smart-settings__chips" data-settings-summary="audience"></span>
              </button>
              <p class="smart-settings__updated" data-settings-updated="audience"></p>
            </header>
            <div class="smart-settings__body" data-settings-body="audience" hidden>
              <div class="smart-settings__alert" data-settings-alert="audience" role="alert" hidden></div>
              <form class="smart-settings__form" data-settings-form="audience">
                <label>Primary locations
                  <textarea rows="3" data-setting-field="locations" data-setting-type="tags" placeholder="Jharkhand, Ranchi, Bokaro"></textarea>
                </label>
                <div class="smart-settings__grid smart-settings__grid--compact">
                  <label>Minimum age
                    <input type="number" min="18" max="75" data-setting-field="ageRange.min" data-setting-type="number" />
                  </label>
                  <label>Maximum age
                    <input type="number" min="18" max="80" data-setting-field="ageRange.max" data-setting-type="number" />
                  </label>
                </div>
                <label>Interest tags
                  <textarea rows="3" data-setting-field="interestTags" data-setting-type="tags" placeholder="homeowners, solar, renewable energy"></textarea>
                </label>
                <label>Custom exclusions
                  <textarea rows="3" data-setting-field="exclusions" placeholder="existing customers, vendors"></textarea>
                </label>
                <fieldset class="smart-settings__fieldset">
                  <legend>Language split (%)</legend>
                  <div class="smart-settings__grid smart-settings__grid--compact">
                    <label>English
                      <input type="number" min="0" max="100" step="1" data-setting-field="languageSplit" data-setting-type="language" data-language="English" />
                    </label>
                    <label>Hindi
                      <input type="number" min="0" max="100" step="1" data-setting-field="languageSplit" data-setting-type="language" data-language="Hindi" />
                    </label>
                    <label>Hinglish
                      <input type="number" min="0" max="100" step="1" data-setting-field="languageSplit" data-setting-type="language" data-language="Hinglish" />
                    </label>
                  </div>
                </fieldset>
                <fieldset class="smart-settings__fieldset">
                  <legend>Device priorities (%)</legend>
                  <div class="smart-settings__grid smart-settings__grid--compact">
                    <label>Mobile
                      <input type="number" min="0" max="100" step="1" data-setting-field="devicePriorities.mobile" data-setting-type="number" />
                    </label>
                    <label>Desktop
                      <input type="number" min="0" max="100" step="1" data-setting-field="devicePriorities.desktop" data-setting-type="number" />
                    </label>
                  </div>
                </fieldset>
                <label>Time-of-day scheduling
                  <textarea rows="3" data-setting-field="schedule" data-setting-type="schedule" placeholder="Mon 09:00-18:00&#10;Sat 10:00-14:00"></textarea>
                  <span class="smart-settings__hint">Use one slot per line (day HH:MM-HH:MM). Leave blank for always-on.</span>
                </label>
                <div class="smart-settings__actions">
                  <button type="button" class="btn btn-primary" data-settings-save="audience"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save</button>
                  <button type="button" class="btn btn-ghost" data-settings-revert="audience"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Revert</button>
                  <button type="button" class="btn btn-ghost" data-settings-test="audience"><i class="fa-solid fa-stethoscope" aria-hidden="true"></i> Test</button>
                </div>
              </form>
              <div class="smart-settings__history" data-settings-history="audience"></div>
            </div>
          </article>

          <article class="smart-settings__section" data-settings-section="compliance">
            <header class="smart-settings__section-header">
              <button type="button" class="smart-settings__toggle" data-settings-toggle="compliance" aria-expanded="false">
                <span class="smart-settings__title">Compliance &amp; Policy</span>
                <span class="smart-settings__chips" data-settings-summary="compliance"></span>
              </button>
              <p class="smart-settings__updated" data-settings-updated="compliance"></p>
            </header>
            <div class="smart-settings__body" data-settings-body="compliance" hidden>
              <div class="smart-settings__alert" data-settings-alert="compliance" role="alert" hidden></div>
              <form class="smart-settings__form" data-settings-form="compliance">
                <label class="smart-settings__toggle">
                  <input type="checkbox" data-setting-field="autoDisclaimer" data-setting-type="boolean" /> Auto-enable subsidy disclaimer on ads
                </label>
                <label>Disclaimer text
                  <textarea rows="3" data-setting-field="disclaimerText" placeholder="Subsidy subject to MNRE / DISCOM approval."></textarea>
                </label>
                <label class="smart-settings__toggle">
                  <input type="checkbox" data-setting-field="policyChecks" data-setting-type="boolean" /> Auto-check against Meta &amp; Google policies
                </label>
                <div class="smart-settings__actions">
                  <button type="button" class="btn btn-primary" data-settings-save="compliance"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save</button>
                  <button type="button" class="btn btn-ghost" data-settings-revert="compliance"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Revert</button>
                  <button type="button" class="btn btn-ghost" data-settings-test="compliance"><i class="fa-solid fa-stethoscope" aria-hidden="true"></i> Test</button>
                </div>
              </form>
              <div class="smart-settings__history" data-settings-history="compliance"></div>
              <section class="smart-settings__flagged" data-compliance-flagged hidden>
                <h4>Flagged creatives</h4>
                <ul data-compliance-flagged-list></ul>
              </section>
            </div>
          </article>

          <article class="smart-settings__section" data-settings-section="integrations">
            <header class="smart-settings__section-header">
              <button type="button" class="smart-settings__toggle" data-settings-toggle="integrations" aria-expanded="false">
                <span class="smart-settings__title">Integrations &amp; API Health</span>
                <span class="smart-settings__chips" data-settings-summary="integrations"></span>
              </button>
              <p class="smart-settings__updated" data-settings-updated="integrations"></p>
            </header>
            <div class="smart-settings__body" data-settings-body="integrations" hidden>
              <div class="smart-settings__alert" data-settings-alert="integrations" role="alert" hidden></div>
              <div class="smart-settings__gemini" data-settings-gemini></div>
              <form class="smart-settings__form" data-settings-form="integrations">
                <div class="smart-settings__integrations-table">
                  <div class="smart-settings__integrations-header">
                    <span>Platform</span>
                    <span>Status</span>
                    <span>Configuration</span>
                  </div>
<?php foreach ($connectorCatalog as $connectorKey => $connectorMeta):
    $fieldsMeta = $connectorMeta['fields'] ?? [];
?>
                  <div class="smart-settings__integration-row" data-integration-row="<?= htmlspecialchars($connectorKey, ENT_QUOTES) ?>">
                    <div class="smart-settings__integration-summary">
                      <strong><?= htmlspecialchars($connectorMeta['label'] ?? smart_marketing_integration_label($connectorKey), ENT_QUOTES) ?></strong>
                      <p><?= htmlspecialchars($connectorMeta['description'] ?? '', ENT_QUOTES) ?></p>
                      <p class="smart-settings__meta">
                        <span class="smart-status" data-integration-status="<?= htmlspecialchars($connectorKey, ENT_QUOTES) ?>">Unknown</span>
                      </p>
                      <p class="smart-settings__meta">
                        Last validated <span data-integration-validated="<?= htmlspecialchars($connectorKey, ENT_QUOTES) ?>">—</span>
                        · by <span data-integration-validated-by="<?= htmlspecialchars($connectorKey, ENT_QUOTES) ?>">—</span>
                      </p>
                      <p class="smart-settings__meta" data-integration-message="<?= htmlspecialchars($connectorKey, ENT_QUOTES) ?>"></p>
                    </div>
                    <div class="smart-settings__integration-config">
<?php foreach ($fieldsMeta as $fieldMeta):
    $fieldKey = (string) ($fieldMeta['key'] ?? '');
    if ($fieldKey === '') {
        continue;
    }
    $label = $fieldMeta['label'] ?? ucwords(str_replace(['_', 'Id'], [' ', ' ID'], $fieldKey));
    $isSecret = !empty($fieldMeta['secret']);
    $fieldType = $fieldMeta['type'] ?? ($isSecret ? 'secret' : 'text');
    $helpList = $connectorFieldHelp[$connectorKey][$fieldKey] ?? ['Check the platform admin console for this credential.'];
?>
                      <div class="smart-settings__integration-input">
                        <label class="smart-settings__integration-field<?= $isSecret ? ' smart-settings__integration-field--secret' : '' ?>">
                          <span><?= htmlspecialchars($label, ENT_QUOTES) ?><?= !empty($fieldMeta['required']) ? ' <span class="smart-settings__required" aria-hidden="true">*</span>' : '' ?></span>
<?php if ($fieldType === 'select'): ?>
                          <select data-setting-field="channels.<?= htmlspecialchars($connectorKey, ENT_QUOTES) ?>.<?= htmlspecialchars($fieldKey, ENT_QUOTES) ?>">
                            <option value="">Select…</option>
<?php foreach (($fieldMeta['options'] ?? []) as $option): ?>
                            <option value="<?= htmlspecialchars((string) $option, ENT_QUOTES) ?>"><?= htmlspecialchars(ucwords(str_replace(['_', '-'], ' ', (string) $option)), ENT_QUOTES) ?></option>
<?php endforeach; ?>
                          </select>
<?php else: ?>
                          <input
                            type="<?= $isSecret ? 'password' : 'text' ?>"
                            data-setting-field="channels.<?= htmlspecialchars($connectorKey, ENT_QUOTES) ?>.<?= htmlspecialchars($fieldKey, ENT_QUOTES) ?>"
                            data-setting-type="<?= $isSecret ? 'secret' : 'text' ?>"
                            data-secret-has-value="0"
                            autocomplete="<?= $isSecret ? 'new-password' : 'off' ?>"
                            placeholder="<?= htmlspecialchars($fieldMeta['placeholder'] ?? '', ENT_QUOTES) ?>"
                          />
<?php endif; ?>
                        </label>
                        <details class="smart-help" data-credential-help>
                          <summary>Where to find this?</summary>
                          <ul>
<?php foreach ($helpList as $helpLine): ?>
                            <li><?= htmlspecialchars($helpLine, ENT_QUOTES) ?></li>
<?php endforeach; ?>
                          </ul>
                        </details>
                      </div>
<?php endforeach; ?>
                      <div class="smart-settings__inline-actions">
                        <button type="button" class="btn btn-primary" data-integration-save="<?= htmlspecialchars($connectorKey, ENT_QUOTES) ?>"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save</button>
                        <button type="button" class="btn btn-ghost" data-integration-test="<?= htmlspecialchars($connectorKey, ENT_QUOTES) ?>"><i class="fa-solid fa-stethoscope" aria-hidden="true"></i> Test</button>
                        <button type="button" class="btn btn-ghost" data-integration-disable="<?= htmlspecialchars($connectorKey, ENT_QUOTES) ?>"><i class="fa-solid fa-power-off" aria-hidden="true"></i> Disable</button>
                        <button type="button" class="btn btn-danger" data-integration-delete="<?= htmlspecialchars($connectorKey, ENT_QUOTES) ?>"><i class="fa-solid fa-trash" aria-hidden="true"></i> Delete</button>
                      </div>
                    </div>
                  </div>
<?php endforeach; ?>
                </div>
              </form>
              <div class="smart-settings__history" data-settings-history="integrations"></div>
            </div>
          </article>

          <footer class="smart-settings__footer">
            <button type="button" class="btn btn-ghost" data-settings-reload><i class="fa-solid fa-rotate" aria-hidden="true"></i> Reload settings</button>
          </footer>
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

    <aside class="smart-guide" data-start-guide hidden>
      <div class="smart-guide__backdrop" data-start-guide-close></div>
      <div class="smart-guide__dialog" role="dialog" aria-modal="true" aria-labelledby="start-guide-heading">
        <header class="smart-guide__header">
          <div>
            <p class="smart-guide__eyebrow">Quick onboarding</p>
            <h2 id="start-guide-heading">Smart Marketing Start Guide</h2>
          </div>
          <button type="button" class="btn btn-ghost" data-start-guide-close aria-label="Close start guide">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </header>

        <nav class="smart-guide__tabs" role="tablist">
          <button type="button" role="tab" aria-controls="start-guide-step-1" aria-selected="true" data-start-guide-tab="1">1. Connect Platforms</button>
          <button type="button" role="tab" aria-controls="start-guide-step-2" aria-selected="false" data-start-guide-tab="2">2. Tell the AI Your Goal</button>
          <button type="button" role="tab" aria-controls="start-guide-step-3" aria-selected="false" data-start-guide-tab="3">3. Launch &amp; Monitor</button>
        </nav>

        <section class="smart-guide__panel" id="start-guide-step-1" role="tabpanel" data-start-guide-panel="1">
          <p>Connect the channels where you want the AI to plan and launch campaigns. Each card has a shortcut back to its credentials.</p>
          <div class="smart-guide__platforms">
            <article class="smart-guide__platform" data-tooltip="Facebook &amp; Instagram Ads" tabindex="0">
              <header>
                <h3>Meta</h3>
                <span class="smart-guide__chip">Awareness &amp; leads</span>
              </header>
              <p>Facebook/Instagram Ads for awareness &amp; lead capture.</p>
              <ul>
                <li>Sync Business Manager, Page, Pixel, and System User token.</li>
                <li>Enable WhatsApp number if you want instant replies.</li>
              </ul>
              <button type="button" class="btn btn-secondary" data-guide-target="meta">Go to Credentials</button>
            </article>
            <article class="smart-guide__platform" data-tooltip="Search, Display, YouTube" tabindex="0">
              <header>
                <h3>Google Ads</h3>
                <span class="smart-guide__chip">Full funnel</span>
              </header>
              <p>Search, Display, and YouTube campaigns under one budget.</p>
              <ul>
                <li>Link MCC, OAuth client, refresh token, and developer token.</li>
                <li>Add conversion tracking so AI can optimise CPL.</li>
              </ul>
              <button type="button" class="btn btn-secondary" data-guide-target="googleAds">Go to Credentials</button>
            </article>
            <article class="smart-guide__platform" data-tooltip="Conversations that convert" tabindex="0">
              <header>
                <h3>WhatsApp</h3>
                <span class="smart-guide__chip">Follow-ups</span>
              </header>
              <p>Auto replies, lead follow-ups, and remarketing templates.</p>
              <ul>
                <li>Map your WABA, phone number ID, and access token.</li>
                <li>Optional: add BSP details for template analytics.</li>
              </ul>
              <button type="button" class="btn btn-secondary" data-guide-target="whatsapp">Go to Credentials</button>
            </article>
            <article class="smart-guide__platform" data-tooltip="Drip, nurture, and alerts" tabindex="0">
              <header>
                <h3>Email / SMS</h3>
                <span class="smart-guide__chip">Nurture</span>
              </header>
              <p>Email/SMS journeys for reminders and long-term nurturing.</p>
              <ul>
                <li>Choose the sending provider and confirm sender identity.</li>
                <li>Set a sandbox recipient for safe test launches.</li>
              </ul>
              <button type="button" class="btn btn-secondary" data-guide-target="email">Go to Credentials</button>
            </article>
          </div>
        </section>

        <section class="smart-guide__panel" id="start-guide-step-2" role="tabpanel" data-start-guide-panel="2" hidden>
          <p>The AI plans the entire campaign mix once it understands your objective. Be as specific as possible.</p>
          <div class="smart-guide__callout">
            <h3>Example brief</h3>
            <p><strong>“I want to increase 3–5 kW residential solar leads in Ranchi under PM Surya Ghar with ₹20 000 budget.”</strong></p>
            <p>The AI will interpret your goal, confirm targeting and budgets, and prepare a launch-ready plan with creatives, bids, and pacing.</p>
          </div>
          <div class="smart-guide__autonomy">
            <h3>Autonomy modes</h3>
            <ul>
              <li><strong>Draft-only</strong> – Generates plans you can copy into platforms manually.</li>
              <li><strong>Review</strong> – Requires your approval before launch.</li>
              <li><strong>Auto</strong> – Launches instantly once validations pass.</li>
            </ul>
          </div>
        </section>

        <section class="smart-guide__panel" id="start-guide-step-3" role="tabpanel" data-start-guide-panel="3" hidden>
          <p>Once goals are set, Smart Marketing executes the same repeatable workflow every time.</p>
          <ol class="smart-guide__workflow">
            <li>Generate Plan – Brain drafts campaigns and budget split.</li>
            <li>Approve (or Auto-launch) – Review and launch instantly.</li>
            <li>View Analytics – Monitor CPL, CTR, and conversions.</li>
            <li>Pause/Resume anytime – Use the kill switch or plan controls.</li>
            <li>Check Leads in CRM – Leads sync straight to the CRM view.</li>
          </ol>
          <div class="smart-guide__cta-grid">
            <button type="button" class="btn btn-secondary" data-guide-scroll="brain">Open AI Command Box</button>
            <button type="button" class="btn btn-secondary" data-guide-scroll="analytics">View Analytics Dashboard</button>
            <button type="button" class="btn btn-secondary" data-guide-action="pause">Pause All Campaigns</button>
          </div>
        </section>

        <details class="smart-guide__glossary">
          <summary>Glossary – What the AI refers to</summary>
          <ul>
            <li><strong>Campaign</strong>: The top-level objective, budget, and geo.</li>
            <li><strong>Ad Set</strong>: Targeting bundle with placements and bids.</li>
            <li><strong>Creative</strong>: The actual ad copy, image, or video.</li>
            <li><strong>CTR</strong>: Click-through rate, clicks divided by impressions.</li>
            <li><strong>CPL</strong>: Cost per lead, spend divided by qualified leads.</li>
            <li><strong>Attribution</strong>: How conversions are credited to channels.</li>
            <li><strong>Optimization</strong>: Continuous bid/budget adjustments.</li>
          </ul>
        </details>

        <footer class="smart-guide__footer">
          <button type="button" class="btn btn-ghost" data-guide-prev disabled>Back</button>
          <div class="smart-guide__step-indicator" data-start-guide-indicator>Step 1 of 3</div>
          <button type="button" class="btn btn-primary" data-guide-next>Next</button>
        </footer>
      </div>
    </aside>
  </main>

  <script src="admin-smart-marketing.js" defer></script>
</body>
</html>

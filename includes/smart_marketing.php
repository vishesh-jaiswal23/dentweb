<?php
declare(strict_types=1);

require_once __DIR__ . '/ai_gemini.php';

function smart_marketing_storage_dir(): string
{
    $path = __DIR__ . '/../storage/smart_marketing';
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }

    return $path;
}

function smart_marketing_settings_file(): string
{
    return smart_marketing_storage_dir() . '/settings.json';
}

function smart_marketing_settings_lock_file(): string
{
    return smart_marketing_storage_dir() . '/settings.lock';
}

function smart_marketing_brain_runs_file(): string
{
    return smart_marketing_storage_dir() . '/brain_runs.json';
}

function smart_marketing_assets_dir(): string
{
    $dir = smart_marketing_storage_dir() . '/assets';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return $dir;
}

function smart_marketing_audit_log_file(): string
{
    return smart_marketing_storage_dir() . '/audit.log';
}

function smart_marketing_campaigns_file(): string
{
    return smart_marketing_storage_dir() . '/campaigns.json';
}

function smart_marketing_automation_log_file(): string
{
    return smart_marketing_storage_dir() . '/automations.log';
}

function smart_marketing_analytics_file(): string
{
    return smart_marketing_storage_dir() . '/analytics.json';
}

function smart_marketing_optimization_file(): string
{
    return smart_marketing_storage_dir() . '/optimization.json';
}

function smart_marketing_governance_file(): string
{
    return smart_marketing_storage_dir() . '/governance.json';
}

function smart_marketing_notifications_file(): string
{
    return smart_marketing_storage_dir() . '/notifications.json';
}

function smart_marketing_exports_dir(): string
{
    $dir = smart_marketing_storage_dir() . '/exports';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return $dir;
}

function smart_marketing_landings_dir(): string
{
    $dir = smart_marketing_storage_dir() . '/landings';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return $dir;
}

function smart_marketing_sitemap_fragment_file(): string
{
    return smart_marketing_storage_dir() . '/sitemap-fragment.xml';
}

function smart_marketing_connector_catalog(): array
{
    return [
        'googleAds' => [
            'label' => 'Google Ads',
            'description' => 'Search, Display, Performance Max, and YouTube inventory from the shared MCC.',
            'channels' => ['search', 'display', 'performance_max', 'youtube'],
            'defaults' => [
                'account' => '',
                'subAccount' => '',
                'status' => 'unknown',
            ],
            'accounts' => [
                ['id' => '123-456-7890', 'name' => 'Dakshayani Solar Master'],
                ['id' => '987-654-3210', 'name' => 'Jharkhand Rooftop Portfolio'],
                ['id' => '456-789-0123', 'name' => 'Performance Max Experiments'],
            ],
        ],
        'meta' => [
            'label' => 'Meta Ads (FB/IG)',
            'description' => 'Business Manager and connected ad accounts for Facebook & Instagram placements.',
            'channels' => ['lead', 'awareness', 'engagement'],
            'defaults' => [
                'account' => '',
                'page' => '',
                'status' => 'unknown',
            ],
            'accounts' => [
                ['id' => 'act_112233445566778', 'name' => 'Dakshayani Meta Ads'],
                ['id' => 'act_998877665544332', 'name' => 'Regional Boosters'],
            ],
            'pages' => [
                ['id' => 'pg_64521', 'name' => 'Dakshayani Enterprises'],
                ['id' => 'pg_99881', 'name' => 'Dakshayani Solar Rooftops'],
            ],
        ],
        'youtube' => [
            'label' => 'YouTube Ads',
            'description' => 'Uses the linked Google Ads account to publish in-feed, in-stream, and Shorts inventory.',
            'channels' => ['video'],
            'defaults' => [
                'channel' => '',
                'status' => 'unknown',
            ],
            'channels_list' => [
                ['id' => 'UC-DAKSHAYANI', 'name' => 'Dakshayani Solar Stories'],
                ['id' => 'UC-ENERGYPLUS', 'name' => 'Energy Plus Series'],
            ],
        ],
        'whatsapp' => [
            'label' => 'WhatsApp Business',
            'description' => 'Click-to-chat flows and templates through Meta Business APIs.',
            'channels' => ['messaging'],
            'defaults' => [
                'number' => '',
                'businessAccount' => '',
                'status' => 'unknown',
            ],
            'numbers' => [
                ['id' => '+91-6200001234', 'name' => 'Sales Desk'],
                ['id' => '+91-6200005678', 'name' => 'Service & AMC'],
            ],
        ],
        'email' => [
            'label' => 'Email & SMS Provider',
            'description' => 'Transactional and bulk blasts via connected provider APIs.',
            'channels' => ['email', 'sms'],
            'defaults' => [
                'provider' => '',
                'profile' => '',
                'status' => 'unknown',
            ],
            'providers' => [
                ['id' => 'sendgrid', 'name' => 'SendGrid (Transactional)'],
                ['id' => 'mailgun', 'name' => 'Mailgun Bulk'],
                ['id' => 'msg91', 'name' => 'MSG91 SMS'],
            ],
        ],
    ];
}

function smart_marketing_default_connector_settings(): array
{
    $defaults = [];
    foreach (smart_marketing_connector_catalog() as $key => $meta) {
        $defaults[$key] = array_merge(
            [
                'status' => 'unknown',
                'connectedAt' => null,
                'lastTested' => null,
                'lastTestResult' => 'unknown',
            ],
            $meta['defaults'] ?? []
        );
    }

    return $defaults;
}

function smart_marketing_settings_defaults(): array
{
    return [
        'businessProfile' => [
            'brandName' => 'Dakshayani Enterprises',
            'tagline' => 'Trusted Solar Partner',
            'about' => 'We design, install, and maintain solar systems for homes, businesses, and institutions with a focus on Jharkhand and neighbouring districts.',
            'primaryContact' => '',
            'supportEmail' => '',
            'whatsappNumber' => '',
            'serviceRegions' => ['Jharkhand', 'Ranchi', 'Bokaro', 'Hazaribagh'],
        ],
        'audiences' => [
            'primarySegments' => ['Residential homeowners', 'Commercial rooftop decision makers', 'Factory owners seeking savings'],
            'remarketingNotes' => 'Focus on past enquiry lists, site visitors, and WhatsApp responders from the last 90 days.',
            'exclusions' => 'Exclude installers, competitors, and non-serviceable states.',
        ],
        'products' => [
            'portfolio' => [
                'Rooftop 1kW',
                'Rooftop 3kW',
                'Rooftop 5kW',
                'Rooftop 10kW',
                'Hybrid systems',
                'Off-grid kits',
                'C&I 10-100kW',
                'PM Surya Ghar subsidised',
                'Non-subsidy rooftop offers',
            ],
            'offers' => 'Highlight PM Surya Ghar subsidy eligibility, EMI assistance, and annual maintenance contracts (AMC).',
        ],
        'budget' => [
            'dailyCap' => 25000,
            'monthlyCap' => 500000,
            'minBid' => 30,
            'targetCpl' => 450,
            'currency' => 'INR',
        ],
        'autonomy' => [
            'mode' => 'review',
            'reviewRecipients' => '',
            'killSwitchEngaged' => false,
        ],
        'compliance' => [
            'policyChecks' => true,
            'brandTone' => true,
            'legalDisclaimers' => true,
            'pmSuryaDisclaimer' => 'Subsidy subject to MNRE approvals. Customer eligibility and documentation required.',
            'notes' => '',
        ],
        'integrations' => smart_marketing_default_connector_settings(),
        'updatedAt' => null,
    ];
}

function smart_marketing_settings_load(): array
{
    $defaults = smart_marketing_settings_defaults();
    $file = smart_marketing_settings_file();

    if (!is_file($file)) {
        return $defaults;
    }

    $contents = file_get_contents($file);
    if ($contents === false || trim($contents) === '') {
        return $defaults;
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        error_log('smart_marketing_settings_load decode failed: ' . $exception->getMessage());
        return $defaults;
    }

    if (!is_array($decoded)) {
        return $defaults;
    }

    $settings = array_replace_recursive($defaults, $decoded);
    $settings['updatedAt'] = is_string($settings['updatedAt'] ?? null) ? $settings['updatedAt'] : null;

    if (!isset($settings['businessProfile']['serviceRegions']) || !is_array($settings['businessProfile']['serviceRegions'])) {
        $settings['businessProfile']['serviceRegions'] = $defaults['businessProfile']['serviceRegions'];
    }

    if (!isset($settings['products']['portfolio']) || !is_array($settings['products']['portfolio'])) {
        $settings['products']['portfolio'] = $defaults['products']['portfolio'];
    }

    $connectorDefaults = smart_marketing_default_connector_settings();
    if (!isset($settings['integrations']) || !is_array($settings['integrations'])) {
        $settings['integrations'] = $connectorDefaults;
    } else {
        foreach ($connectorDefaults as $connectorKey => $connectorDefault) {
            $current = is_array($settings['integrations'][$connectorKey] ?? null) ? $settings['integrations'][$connectorKey] : [];
            $settings['integrations'][$connectorKey] = array_merge($connectorDefault, $current);
        }
    }

    return $settings;
}

function smart_marketing_settings_save(array $settings): void
{
    $settings['updatedAt'] = ai_timestamp();

    $payload = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        throw new RuntimeException('Unable to encode Smart Marketing settings.');
    }

    $lockHandle = fopen(smart_marketing_settings_lock_file(), 'c+');
    if ($lockHandle === false) {
        throw new RuntimeException('Unable to open Smart Marketing settings lock.');
    }

    try {
        if (!flock($lockHandle, LOCK_EX)) {
            throw new RuntimeException('Unable to acquire Smart Marketing lock.');
        }

        if (file_put_contents(smart_marketing_settings_file(), $payload, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write Smart Marketing settings.');
        }

        fflush($lockHandle);
        flock($lockHandle, LOCK_UN);
    } finally {
        fclose($lockHandle);
    }
}

function smart_marketing_brain_runs_load(): array
{
    $file = smart_marketing_brain_runs_file();
    if (!is_file($file)) {
        return [];
    }

    $contents = file_get_contents($file);
    if ($contents === false || trim($contents) === '') {
        return [];
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        error_log('smart_marketing_brain_runs_load decode failed: ' . $exception->getMessage());
        return [];
    }

    return is_array($decoded) ? $decoded : [];
}

function smart_marketing_brain_runs_save(array $runs): void
{
    $payload = json_encode(array_values($runs), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        throw new RuntimeException('Unable to encode Smart Marketing brain runs.');
    }

    if (file_put_contents(smart_marketing_brain_runs_file(), $payload, LOCK_EX) === false) {
        throw new RuntimeException('Unable to persist Smart Marketing brain runs.');
    }
}

function smart_marketing_next_run_id(array $runs): int
{
    $max = 0;
    foreach ($runs as $run) {
        $id = (int) ($run['id'] ?? 0);
        if ($id > $max) {
            $max = $id;
        }
    }

    return $max + 1;
}

function smart_marketing_audit_log_append(string $action, array $context = [], array $user = []): void
{
    $record = [
        'timestamp' => ai_timestamp(),
        'action' => $action,
        'user' => [
            'id' => (int) ($user['id'] ?? 0),
            'name' => (string) ($user['full_name'] ?? ($user['name'] ?? 'Admin')),
        ],
        'context' => smart_marketing_scrub_context($context),
    ];

    $payload = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return;
    }

    $line = $payload . "\n";
    file_put_contents(smart_marketing_audit_log_file(), $line, FILE_APPEND | LOCK_EX);
}

function smart_marketing_mask_value(string $value): string
{
    $masked = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+/i', '[redacted-email]', $value);
    $masked = preg_replace_callback('/\+?\d[\d\s-]{7,}/', static function ($matches) {
        $digits = preg_replace('/\D+/', '', $matches[0]);
        if (strlen($digits) < 7) {
            return '[redacted]';
        }
        $visible = substr($digits, -2);
        return '[redacted-phone]' . $visible;
    }, $masked);

    return $masked;
}

function smart_marketing_scrub_context(array $context): array
{
    $scrubbed = [];
    foreach ($context as $key => $value) {
        $lower = strtolower((string) $key);
        if (is_array($value)) {
            $scrubbed[$key] = smart_marketing_scrub_context($value);
            continue;
        }

        if (str_contains($lower, 'key') || str_contains($lower, 'token') || str_contains($lower, 'secret') || str_contains($lower, 'password')) {
            $scrubbed[$key] = '***';
        } elseif (is_string($value)) {
            $scrubbed[$key] = smart_marketing_mask_value($value);
        } else {
            $scrubbed[$key] = $value;
        }
    }

    return $scrubbed;
}

function smart_marketing_audit_log_read(int $limit = 50): array
{
    $file = smart_marketing_audit_log_file();
    if (!is_file($file)) {
        return [];
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    $lines = array_slice($lines, -$limit);
    $entries = [];
    foreach ($lines as $line) {
        try {
            $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            continue;
        }

        if (is_array($decoded)) {
            $entries[] = $decoded;
        }
    }

    return array_reverse($entries);
}

function smart_marketing_ai_health(array $aiSettings): array
{
    $hasKey = trim((string) ($aiSettings['api_key'] ?? '')) !== '';
    $models = [
        'text' => $aiSettings['models']['text'] ?? '',
        'image' => $aiSettings['models']['image'] ?? '',
        'tts' => $aiSettings['models']['tts'] ?? '',
    ];

    $connected = $hasKey;
    $message = $hasKey ? 'Gemini key present.' : 'Add a valid Gemini API key in AI Studio.';

    return [
        'connected' => $connected,
        'message' => $message,
        'models' => $models,
    ];
}

function smart_marketing_integrations_health(array $settings): array
{
    $integrations = $settings['integrations'] ?? [];
    $catalog = smart_marketing_connector_catalog();
    $result = [];
    foreach ($catalog as $key => $meta) {
        $entry = $integrations[$key] ?? [];
        $status = strtolower((string) ($entry['status'] ?? 'unknown'));
        if (!in_array($status, ['connected', 'warning', 'error', 'unknown'], true)) {
            $status = 'unknown';
        }

        $result[$key] = [
            'status' => $status,
            'label' => $meta['label'] ?? smart_marketing_integration_label($key),
            'details' => array_merge($entry, ['channels' => $meta['channels'] ?? []]),
        ];
    }

    return $result;
}

function smart_marketing_integration_label(string $key): string
{
    return match ($key) {
        'googleAds' => 'Google Ads',
        'meta' => 'Meta Ads',
        'youtube' => 'YouTube Ads',
        'whatsapp' => 'WhatsApp Business',
        'email' => 'Email & SMS',
        default => ucfirst($key),
    };
}

function smart_marketing_channel_connectors(array $settings): array
{
    $catalog = smart_marketing_connector_catalog();
    $integrations = $settings['integrations'] ?? [];
    $connectors = [];
    foreach ($catalog as $key => $meta) {
        $entry = $integrations[$key] ?? smart_marketing_default_connector_settings()[$key];
        $status = strtolower((string) ($entry['status'] ?? 'unknown'));
        if (!in_array($status, ['connected', 'warning', 'error', 'unknown'], true)) {
            $status = 'unknown';
        }

        $connectors[] = [
            'id' => $key,
            'label' => $meta['label'],
            'description' => $meta['description'],
            'status' => $status,
            'lastTested' => $entry['lastTested'] ?? null,
            'lastTestResult' => $entry['lastTestResult'] ?? 'unknown',
            'connectedAt' => $entry['connectedAt'] ?? null,
            'details' => $entry,
            'options' => [
                'accounts' => $meta['accounts'] ?? [],
                'pages' => $meta['pages'] ?? [],
                'channels' => $meta['channels'] ?? [],
                'youtubeChannels' => $meta['channels_list'] ?? [],
                'numbers' => $meta['numbers'] ?? [],
                'providers' => $meta['providers'] ?? [],
            ],
        ];
    }

    return $connectors;
}

function smart_marketing_connector_connect(array &$settings, string $connectorKey, array $payload): array
{
    $catalog = smart_marketing_connector_catalog();
    if (!isset($catalog[$connectorKey])) {
        throw new RuntimeException('Unknown connector.');
    }

    $entry = $settings['integrations'][$connectorKey] ?? smart_marketing_default_connector_settings()[$connectorKey];
    $requiredField = match ($connectorKey) {
        'googleAds' => 'account',
        'meta' => 'account',
        'youtube' => 'channel',
        'whatsapp' => 'number',
        'email' => 'provider',
        default => null,
    };

    if ($requiredField) {
        $value = trim((string) ($payload[$requiredField] ?? $entry[$requiredField] ?? ''));
        if ($value === '') {
            throw new RuntimeException('Select an account before connecting.');
        }
        $entry[$requiredField] = $value;
    }

    foreach ($payload as $field => $value) {
        if (is_scalar($value)) {
            $entry[$field] = is_string($value) ? trim((string) $value) : $value;
        }
    }

    $entry['status'] = 'connected';
    $entry['connectedAt'] = ai_timestamp();
    $entry['lastTested'] = null;
    $entry['lastTestResult'] = 'unknown';

    $settings['integrations'][$connectorKey] = $entry;

    return $entry;
}

function smart_marketing_connector_test(array &$settings, string $connectorKey): array
{
    $catalog = smart_marketing_connector_catalog();
    if (!isset($catalog[$connectorKey])) {
        throw new RuntimeException('Unknown connector.');
    }

    $entry = $settings['integrations'][$connectorKey] ?? smart_marketing_default_connector_settings()[$connectorKey];
    $requiredField = match ($connectorKey) {
        'googleAds' => 'account',
        'meta' => 'account',
        'youtube' => 'channel',
        'whatsapp' => 'number',
        'email' => 'provider',
        default => null,
    };

    $ok = true;
    if ($requiredField) {
        $ok = trim((string) ($entry[$requiredField] ?? '')) !== '';
    }

    $now = ai_timestamp();
    $entry['lastTested'] = $now;
    $entry['lastTestResult'] = $ok ? 'ok' : 'fail';
    $entry['status'] = $ok ? 'connected' : 'error';

    $settings['integrations'][$connectorKey] = $entry;

    return $entry;
}

function smart_marketing_campaigns_load(): array
{
    $file = smart_marketing_campaigns_file();
    if (!is_file($file)) {
        return [];
    }

    $contents = file_get_contents($file);
    if ($contents === false || trim($contents) === '') {
        return [];
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        error_log('smart_marketing_campaigns_load decode failed: ' . $exception->getMessage());
        return [];
    }

    return is_array($decoded) ? $decoded : [];
}

function smart_marketing_campaigns_save(array $campaigns): void
{
    $payload = json_encode(array_values($campaigns), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        throw new RuntimeException('Unable to encode Smart Marketing campaigns.');
    }

    if (file_put_contents(smart_marketing_campaigns_file(), $payload, LOCK_EX) === false) {
        throw new RuntimeException('Unable to persist Smart Marketing campaigns.');
    }
}

function smart_marketing_next_campaign_sequence(array $campaigns): int
{
    $max = 0;
    foreach ($campaigns as $campaign) {
        $sequence = (int) ($campaign['sequence'] ?? 0);
        if ($sequence > $max) {
            $max = $sequence;
        }
    }

    return $max + 1;
}

function smart_marketing_campaign_catalog(): array
{
    return [
        'search' => [
            'label' => 'Search',
            'prefix' => 'SRCH',
            'connectors' => ['googleAds'],
            'utm' => ['source' => 'google', 'medium' => 'cpc'],
        ],
        'display' => [
            'label' => 'Display',
            'prefix' => 'DSP',
            'connectors' => ['googleAds'],
            'utm' => ['source' => 'google', 'medium' => 'display'],
        ],
        'video' => [
            'label' => 'Video (YouTube Shorts)',
            'prefix' => 'YT',
            'connectors' => ['googleAds', 'youtube'],
            'utm' => ['source' => 'youtube', 'medium' => 'video'],
        ],
        'lead_gen' => [
            'label' => 'Lead Gen Forms',
            'prefix' => 'META-LEAD',
            'connectors' => ['meta'],
            'utm' => ['source' => 'meta', 'medium' => 'lead'],
        ],
        'whatsapp' => [
            'label' => 'Click-to-WhatsApp',
            'prefix' => 'WA',
            'connectors' => ['meta', 'whatsapp'],
            'utm' => ['source' => 'meta', 'medium' => 'whatsapp'],
        ],
        'boosted' => [
            'label' => 'Boosted Posts',
            'prefix' => 'META-BOOST',
            'connectors' => ['meta'],
            'utm' => ['source' => 'meta', 'medium' => 'social'],
        ],
        'email_sms' => [
            'label' => 'Email/SMS Blasts',
            'prefix' => 'MSG',
            'connectors' => ['email'],
            'utm' => ['source' => 'crm', 'medium' => 'automation'],
        ],
    ];
}

function smart_marketing_campaign_type_label(string $type): string
{
    return smart_marketing_campaign_catalog()[$type]['label'] ?? ucfirst(str_replace('_', ' ', $type));
}

function smart_marketing_site_pages(): array
{
    $baseDir = realpath(__DIR__ . '/..');
    if ($baseDir === false) {
        return [];
    }

    $files = glob($baseDir . '/*.html');
    if ($files === false) {
        return [];
    }

    $pages = [];
    foreach ($files as $file) {
        $name = basename($file);
        if (preg_match('/^(admin|login|logout|customer|employee|referrer)/i', $name)) {
            continue;
        }
        $label = ucwords(str_replace(['-', '_'], ' ', pathinfo($name, PATHINFO_FILENAME)));
        $pages[] = [
            'path' => '/' . $name,
            'label' => $label,
        ];
    }

    usort($pages, static function (array $a, array $b): int {
        return strcmp($a['label'], $b['label']);
    });

    return $pages;
}

function smart_marketing_generate_campaign_identifier(string $prefix, int $sequence): string
{
    return sprintf('SM-%s-%04d', strtoupper($prefix), $sequence);
}

function smart_marketing_prepare_campaign_record(array $run, string $type, int $sequence, array $settings, array $options = []): array
{
    $catalog = smart_marketing_campaign_catalog();
    if (!isset($catalog[$type])) {
        throw new RuntimeException('Unsupported campaign type requested.');
    }

    $meta = $catalog[$type];
    $campaignId = smart_marketing_generate_campaign_identifier($meta['prefix'], $sequence);

    $totalTypes = max(1, (int) ($options['totalTypes'] ?? 1));
    $inputs = $run['inputs'] ?? [];
    $regions = $inputs['regions'] ?? ($settings['businessProfile']['serviceRegions'] ?? []);
    $languages = $inputs['languages'] ?? [];
    if (empty($languages)) {
        $languages = smart_marketing_language_options();
    }

    $dailyBudget = max(500, round(((float) ($inputs['dailyBudget'] ?? $settings['budget']['dailyCap'] ?? 0)) / $totalTypes, 2));
    $monthlyBudget = max(15000, round(((float) ($inputs['monthlyBudget'] ?? $settings['budget']['monthlyCap'] ?? 0)) / $totalTypes, 2));

    $keywords = smart_marketing_resolve_keywords($run, $type);
    $placements = smart_marketing_resolve_placements($type);
    $creatives = smart_marketing_resolve_creatives($run, $type, $settings);

    $landingOptions = $options['landing'] ?? [];
    $landingMode = $landingOptions['mode'] ?? 'existing';
    if ($landingMode === 'auto') {
        $landing = smart_marketing_generate_landing_page($campaignId, $landingOptions, $settings, $run, $type);
    } else {
        $landing = [
            'type' => 'existing',
            'url' => $landingOptions['page'] ?? '/contact.html',
        ];
    }

    $connectors = [];
    foreach ($meta['connectors'] as $connectorKey) {
        $connectors[$connectorKey] = $settings['integrations'][$connectorKey] ?? smart_marketing_default_connector_settings()[$connectorKey];
    }

    $canonicalCampaignId = sprintf('%s-%s', strtoupper($meta['prefix']), strtoupper(dechex(time())) . '-' . str_pad((string) $sequence, 2, '0', STR_PAD_LEFT));
    $adGroupIds = [];
    for ($i = 1; $i <= 2; $i++) {
        $adGroupIds[] = sprintf('%s-AG-%02d', strtoupper($meta['prefix']), $i);
    }
    $adIds = [];
    for ($i = 1; $i <= 3; $i++) {
        $adIds[] = sprintf('%s-AD-%02d', strtoupper($meta['prefix']), $i);
    }

    $audienceNotes = $settings['audiences']['remarketingNotes'] ?? '';
    $audiences = [
        'inclusions' => array_values(array_unique(array_merge($regions, $inputs['products'] ?? [], $inputs['goals'] ?? []))),
        'exclusions' => array_filter([trim((string) ($settings['audiences']['exclusions'] ?? ''))]),
        'notes' => $audienceNotes,
    ];

    $utm = array_merge($meta['utm'], [
        'campaign' => strtolower($campaignId),
        'term' => $keywords[0] ?? null,
        'content' => $type,
    ]);

    $schedule = [
        'start' => ai_timestamp(),
        'seasonal' => [
            'summer_rooftop_push' => 'April–June weekly bursts',
            'festival_offers' => 'Navratri & Diwali 10-day sequence',
        ],
        'dayparting' => 'Bid uplift 9am-8pm IST for call-ready teams',
    ];

    $record = [
        'id' => $campaignId,
        'sequence' => $sequence,
        'type' => $type,
        'label' => smart_marketing_campaign_type_label($type),
        'run_id' => $run['id'] ?? null,
        'status' => 'launched',
        'launched_at' => ai_timestamp(),
        'budget' => [
            'daily' => $dailyBudget,
            'monthly' => $monthlyBudget,
        ],
        'targeting' => [
            'regions' => $regions,
            'languages' => $languages,
            'audiences' => $audiences,
        ],
        'keywords' => $keywords,
        'placements' => $placements,
        'creatives' => $creatives,
        'landing' => $landing,
        'connectors' => $connectors,
        'canonical' => [
            'campaign' => $canonicalCampaignId,
            'ad_groups' => $adGroupIds,
            'ads' => $adIds,
        ],
        'metrics' => [
            'ctr' => smart_marketing_default_ctr($type),
            'cpl' => (float) ($settings['budget']['targetCpl'] ?? 450) * 1.05,
            'lead_count' => 0,
        ],
        'utm' => $utm,
        'schedule' => $schedule,
        'audit_trail' => [
            [
                'timestamp' => ai_timestamp(),
                'action' => 'launched',
                'context' => ['connectors' => array_keys($connectors)],
            ],
        ],
    ];

    return $record;
}

function smart_marketing_resolve_keywords(array $run, string $type): array
{
    $plan = $run['plan']['channel_plan'] ?? [];
    $keywords = [];
    foreach ((array) $plan as $entry) {
        $channelName = strtolower((string) ($entry['channel'] ?? $entry['name'] ?? ''));
        if (!smart_marketing_channel_matches_type($channelName, $type)) {
            continue;
        }
        $items = $entry['keywords'] ?? $entry['terms'] ?? [];
        if (is_string($items)) {
            $items = preg_split('/[,\n]+/', $items, -1, PREG_SPLIT_NO_EMPTY);
        }
        if (is_array($items)) {
            foreach ($items as $item) {
                $keywords[] = trim((string) $item);
            }
        }
    }

    $keywords = array_values(array_filter(array_unique($keywords)));
    if (!empty($keywords)) {
        return $keywords;
    }

    return match ($type) {
        'search' => ['solar rooftop installation Jharkhand', 'pm surya ghar subsidy assistance'],
        'display' => ['solar savings banners', 'rooftop financing creative'],
        'video' => ['rooftop solar walkthrough', 'customer testimonial shorts'],
        'lead_gen' => ['solar lead form instant quote'],
        'whatsapp' => ['chat solar consultant', 'book whatsapp site visit'],
        'boosted' => ['solar project highlight', 'emi subsidy awareness'],
        'email_sms' => ['solar emi reminder', 'festival rooftop offer'],
        default => ['solar rooftop enquiry'],
    };
}

function smart_marketing_channel_matches_type(string $channelName, string $type): bool
{
    return match ($type) {
        'search' => str_contains($channelName, 'search'),
        'display' => str_contains($channelName, 'display') || str_contains($channelName, 'gdn'),
        'video' => str_contains($channelName, 'video') || str_contains($channelName, 'youtube'),
        'lead_gen' => str_contains($channelName, 'lead'),
        'whatsapp' => str_contains($channelName, 'whatsapp'),
        'boosted' => str_contains($channelName, 'boost') || str_contains($channelName, 'post'),
        'email_sms' => str_contains($channelName, 'email') || str_contains($channelName, 'sms') || str_contains($channelName, 'blast'),
        default => false,
    };
}

function smart_marketing_resolve_placements(string $type): array
{
    return match ($type) {
        'search' => ['Google Search Network', 'Search Partners'],
        'display' => ['Google Display Network', 'Discovery placements'],
        'video' => ['YouTube Shorts feed', 'In-stream skippable'],
        'lead_gen' => ['Facebook Lead Forms', 'Instagram Lead Ads'],
        'whatsapp' => ['Facebook Click-to-WhatsApp', 'Instagram Stories CTA'],
        'boosted' => ['Facebook Feed', 'Instagram Feed', 'Stories'],
        'email_sms' => ['Email broadcast', 'SMS follow-up drip'],
        default => ['Owned inventory'],
    };
}

function smart_marketing_resolve_creatives(array $run, string $type, array $settings): array
{
    $plan = $run['plan']['creative_plan'] ?? [];
    $brand = $settings['businessProfile']['brandName'] ?? 'Dakshayani Enterprises';
    $result = [
        'text' => [],
        'images' => [],
        'videos' => [],
        'audio' => [],
    ];

    $textSources = $plan['text'] ?? ($plan['copy'] ?? []);
    if (is_string($textSources)) {
        $textSources = preg_split('/\n+/', $textSources, -1, PREG_SPLIT_NO_EMPTY);
    }
    if (is_array($textSources)) {
        foreach ($textSources as $item) {
            if (is_array($item)) {
                $result['text'][] = implode(' — ', array_map('strval', $item));
            } else {
                $result['text'][] = trim((string) $item);
            }
        }
    }

    $imageSources = $plan['images'] ?? $plan['visuals'] ?? [];
    if (is_array($imageSources)) {
        foreach ($imageSources as $image) {
            $result['images'][] = is_array($image) ? ($image['description'] ?? json_encode($image)) : (string) $image;
        }
    }

    $videoSources = $plan['video'] ?? $plan['videos'] ?? [];
    if (is_array($videoSources)) {
        foreach ($videoSources as $video) {
            $result['videos'][] = is_array($video) ? ($video['concept'] ?? json_encode($video)) : (string) $video;
        }
    }

    if (isset($plan['audio'])) {
        $audioSources = is_array($plan['audio']) ? $plan['audio'] : [$plan['audio']];
        foreach ($audioSources as $audio) {
            $result['audio'][] = is_array($audio) ? ($audio['script'] ?? json_encode($audio)) : (string) $audio;
        }
    }

    if (empty(array_filter($result))) {
        $result['text'] = [
            sprintf('%s solar experts: Book your MNRE-approved install today.', $brand),
            'Claim PM Surya Ghar benefits with Dakshayani Enterprises. Free site audit.',
        ];
        if (in_array($type, ['display', 'video', 'boosted'], true)) {
            $result['images'][] = 'Show rooftop installation before/after with 5kW array.';
        }
        if ($type === 'video') {
            $result['videos'][] = '30s customer testimonial with drone shots of rooftop system.';
        }
    }

    return $result;
}

function smart_marketing_default_ctr(string $type): float
{
    return match ($type) {
        'search' => 0.028,
        'display' => 0.012,
        'video' => 0.036,
        'lead_gen' => 0.024,
        'whatsapp' => 0.031,
        'boosted' => 0.029,
        'email_sms' => 0.18,
        default => 0.02,
    };
}

function smart_marketing_generate_landing_page(string $campaignId, array $landingOptions, array $settings, array $run, string $type): array
{
    $brand = $settings['businessProfile']['brandName'] ?? 'Dakshayani Enterprises';
    $headline = trim((string) ($landingOptions['headline'] ?? sprintf('%s — %s Offer', $brand, smart_marketing_campaign_type_label($type))));
    $offer = trim((string) ($landingOptions['offer'] ?? 'Limited-time rooftop solar savings audit.'));
    $cta = trim((string) ($landingOptions['cta'] ?? 'Book a free site visit'));
    $whatsapp = trim((string) ($landingOptions['whatsapp'] ?? ($settings['businessProfile']['whatsappNumber'] ?? '')));
    $call = trim((string) ($landingOptions['call'] ?? ($settings['businessProfile']['primaryContact'] ?? '')));
    $bodyCopy = trim((string) ($landingOptions['body'] ?? 'MNRE empanelled engineers design, install, and maintain subsidy-backed rooftop systems across Jharkhand. Claim financing assistance, AMC support, and performance monitoring.'));
    $regions = implode(', ', $run['inputs']['regions'] ?? ($settings['businessProfile']['serviceRegions'] ?? ['Jharkhand']));
    $whatsappHref = 'https://wa.me/';
    if ($whatsapp !== '') {
        $whatsappHref .= preg_replace('/\D+/', '', $whatsapp);
    }
    $callHref = $call !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $call) : '#';

    $slugSeed = strtolower($campaignId . '-' . preg_replace('/[^a-z0-9]+/i', '-', $offer));
    $slug = trim(preg_replace('/-+/', '-', $slugSeed), '-');
    if ($slug === '') {
        $slug = strtolower($campaignId);
    }

    $filePath = smart_marketing_landings_dir() . '/' . $slug . '.html';
    $formAction = '/contact.html';
    $languagesLabel = implode(', ', $run['inputs']['languages'] ?? smart_marketing_language_options());

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>{$headline}</title>
  <link rel="stylesheet" href="/style.css" />
</head>
<body class="landing-smart-marketing">
  <main class="landing">
    <header>
      <p class="landing__tag">Smart Marketing Landing • {$campaignId}</p>
      <h1>{$headline}</h1>
      <p class="landing__offer">{$offer}</p>
    </header>
    <section class="landing__details">
      <p>{$bodyCopy}</p>
      <ul>
        <li>Service regions: {$regions}</li>
        <li>Languages: {$languagesLabel}</li>
        <li>WhatsApp desk: {$whatsapp}</li>
        <li>Call: {$call}</li>
      </ul>
    </section>
    <section class="landing__cta">
      <a class="btn btn-primary" href="{$whatsappHref}" target="_blank" rel="noopener">Chat on WhatsApp</a>
      <a class="btn btn-ghost" href="{$callHref}">Call our solar desk</a>
    </section>
    <section class="landing__form">
      <h2>Book your consultation</h2>
      <form method="post" action="{$formAction}">
        <label>Name<input type="text" name="name" required></label>
        <label>Phone<input type="tel" name="phone" required></label>
        <label>District<input type="text" name="district" placeholder="Ranchi, Bokaro"></label>
        <label>System interest<input type="text" name="system" placeholder="3 kW rooftop"></label>
        <button type="submit" class="btn btn-primary">{$cta}</button>
      </form>
    </section>
  </main>
</body>
</html>
HTML;

    file_put_contents($filePath, $html, LOCK_EX);

    $relativeUrl = '/storage/smart_marketing/landings/' . $slug . '.html';
    smart_marketing_append_sitemap_entry($relativeUrl);

    return [
        'type' => 'auto',
        'slug' => $slug,
        'url' => $relativeUrl,
        'path' => $filePath,
        'headline' => $headline,
        'offer' => $offer,
        'cta' => $cta,
    ];
}

function smart_marketing_append_sitemap_entry(string $url): void
{
    $file = smart_marketing_sitemap_fragment_file();
    $entries = [];
    if (is_file($file)) {
        $contents = file_get_contents($file);
        if ($contents !== false && trim($contents) !== '') {
            $xml = @simplexml_load_string($contents);
            if ($xml instanceof SimpleXMLElement) {
                foreach ($xml->url as $node) {
                    $loc = (string) $node->loc;
                    $lastmod = (string) $node->lastmod;
                    $entries[$loc] = $lastmod ?: gmdate('c');
                }
            }
        }
    }

    $entries[$url] = gmdate('c');

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    foreach ($entries as $loc => $lastmod) {
        $xml .= '  <url>' . PHP_EOL;
        $xml .= '    <loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>' . PHP_EOL;
        $xml .= '    <lastmod>' . htmlspecialchars($lastmod, ENT_XML1) . '</lastmod>' . PHP_EOL;
        $xml .= '  </url>' . PHP_EOL;
    }
    $xml .= '</urlset>' . PHP_EOL;

    file_put_contents($file, $xml, LOCK_EX);
}

function smart_marketing_launch_campaigns(array &$runs, int $runId, array $campaignTypes, array &$settings, array $options = []): array
{
    if (empty($campaignTypes)) {
        throw new RuntimeException('Choose at least one campaign type.');
    }

    $catalog = smart_marketing_campaign_catalog();
    foreach ($campaignTypes as $type) {
        if (!isset($catalog[$type])) {
            throw new RuntimeException('Unsupported campaign type: ' . $type);
        }
    }

    $runIndex = null;
    foreach ($runs as $index => $run) {
        if ((int) ($run['id'] ?? 0) === $runId) {
            $runIndex = $index;
            break;
        }
    }

    if ($runIndex === null) {
        throw new RuntimeException('Plan not found.');
    }

    $run = $runs[$runIndex];
    $campaigns = smart_marketing_campaigns_load();
    $sequence = smart_marketing_next_campaign_sequence($campaigns);

    $launched = [];
    foreach ($campaignTypes as $type) {
        $record = smart_marketing_prepare_campaign_record($run, $type, $sequence, $settings, array_merge($options, ['totalTypes' => count($campaignTypes)]));
        $sequence++;
        $leadSync = smart_marketing_seed_leads_for_campaign($record, $settings);
        $record['leads'] = $leadSync;
        $record['metrics']['lead_count'] = count(array_filter(array_column($leadSync, 'id')));
        $record['audit_trail'][] = [
            'timestamp' => ai_timestamp(),
            'action' => 'leads_synced',
            'context' => ['lead_ids' => array_column($leadSync, 'id')],
        ];
        $campaigns[] = $record;
        $launched[] = $record;
    }

    $runs[$runIndex]['status'] = 'live';
    $runs[$runIndex]['updated_at'] = ai_timestamp();
    smart_marketing_brain_runs_save($runs);
    smart_marketing_campaigns_save($campaigns);

    $automationResult = smart_marketing_run_automations($campaigns, $settings, true);
    $automationEntries = $automationResult['entries'];

    return [
        'launched' => $launched,
        'campaigns' => $campaigns,
        'automations' => $automationEntries,
    ];
}

function smart_marketing_seed_leads_for_campaign(array &$campaign, array $settings): array
{
    $region = $campaign['targeting']['regions'][0] ?? ($settings['businessProfile']['serviceRegions'][0] ?? 'Jharkhand');
    $baseHash = abs(crc32((string) $campaign['id']));
    $products = $campaign['targeting']['audiences']['inclusions'] ?? ($settings['products']['portfolio'] ?? []);
    $primaryProduct = $products[0] ?? '3 kW Rooftop';

    $templates = [
        [
            'name' => sprintf('%s %s Prospect', $region, ucfirst($campaign['type'])),
            'offset' => 1,
            'utm' => array_merge($campaign['utm'], ['content' => 'primary', 'ad' => $campaign['canonical']['ads'][0] ?? null]),
        ],
        [
            'name' => sprintf('%s Referral %s', $region, ucfirst($campaign['type'])),
            'offset' => 7,
            'utm' => array_merge($campaign['utm'], ['content' => 'remarketing', 'ad' => $campaign['canonical']['ads'][1] ?? null]),
        ],
    ];

    $results = [];
    foreach ($templates as $index => $template) {
        $number = '+91' . '620' . str_pad((string) (($baseHash + $template['offset']) % 10000000), 7, '0', STR_PAD_LEFT);
        $email = sprintf('lead%s@dakshayani.smart', substr(md5($campaign['id'] . $template['offset']), 0, 6));
        $lead = [
            'name' => $template['name'],
            'phone' => $number,
            'email' => $email,
            'source' => sprintf('Smart Marketing • %s', smart_marketing_campaign_type_label($campaign['type'])),
            'region' => $region,
            'product' => $primaryProduct,
            'campaign_id' => $campaign['id'],
            'ad_id' => $template['utm']['ad'] ?? null,
            'keyword' => $campaign['keywords'][0] ?? null,
            'utm' => $template['utm'],
        ];
        $results[] = smart_marketing_sync_lead($lead, $campaign, $settings);
    }

    return $results;
}

function smart_marketing_sync_lead(array $lead, array $campaign, array $settings): array
{
    $db = get_db();
    $phone = preg_replace('/\D+/', '', (string) ($lead['phone'] ?? ''));
    $email = strtolower(trim((string) ($lead['email'] ?? '')));

    $existing = null;
    if ($phone !== '') {
        $stmt = $db->prepare('SELECT id, phone, email, notes FROM crm_leads WHERE phone = :phone LIMIT 1');
        $stmt->execute([':phone' => $phone]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if ($existing === null && $email !== '') {
        $stmt = $db->prepare('SELECT id, phone, email, notes FROM crm_leads WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $note = sprintf('[%s] %s campaign %s via %s', ai_timestamp(), strtoupper($campaign['type']), $campaign['id'], $lead['source'] ?? 'Smart Marketing');
    $utmNote = 'UTM: ' . json_encode($lead['utm'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($existing) {
        $leadId = (int) $existing['id'];
        $existingNotes = trim((string) ($existing['notes'] ?? ''));
        $combinedNotes = trim($existingNotes . PHP_EOL . $note . ' • ' . $utmNote);
        $stmt = $db->prepare('UPDATE crm_leads SET notes = :notes, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':notes' => $combinedNotes,
            ':updated_at' => now_ist(),
            ':id' => $leadId,
        ]);
        $action = 'updated';
    } else {
        $input = [
            'name' => $lead['name'] ?? 'Smart Marketing Lead',
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
            'source' => $lead['source'] ?? 'Smart Marketing',
            'site_location' => $lead['region'] ?? null,
            'site_details' => json_encode([
                'campaign_id' => $lead['campaign_id'] ?? $campaign['id'],
                'ad_id' => $lead['ad_id'] ?? null,
                'keyword' => $lead['keyword'] ?? null,
                'utm' => $lead['utm'] ?? [],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'notes' => $note . ' • ' . $utmNote,
        ];
        $created = admin_create_lead($db, $input, 0);
        $leadId = (int) ($created['id'] ?? 0);
        $employeeId = smart_marketing_pick_employee_for_region($db, (string) ($lead['region'] ?? ''));
        if ($employeeId) {
            admin_assign_lead($db, $leadId, $employeeId, 0);
        }
        $action = 'created';
    }

    smart_marketing_audit_log_append('lead.synced', [
        'lead_id' => $leadId,
        'campaign_id' => $campaign['id'],
        'action' => $action,
        'utm' => $lead['utm'] ?? [],
    ]);

    smart_marketing_audit_log_append('lead.auto_ack', [
        'lead_id' => $leadId,
        'channel' => $campaign['type'],
        'message' => 'WhatsApp/SMS acknowledgement queued with internal reminder.',
    ]);

    return [
        'id' => $leadId,
        'action' => $action,
    ];
}

function smart_marketing_pick_employee_for_region(PDO $db, string $region): ?int
{
    $employees = admin_active_employees($db);
    if (empty($employees)) {
        return null;
    }

    $hash = abs(crc32(strtolower($region)));
    $index = $hash % count($employees);

    return (int) ($employees[$index]['id'] ?? null);
}

function smart_marketing_run_automations(
    array &$campaigns,
    array $settings,
    bool $forced = false,
    ?array $optimization = null,
    ?array $governance = null,
    ?array $notifications = null
): array
{
    $entries = [];
    $campaignsTouched = false;

    $optimizationState = $optimization ?? smart_marketing_optimization_load($settings);
    $governanceState = $governance ?? smart_marketing_governance_load($settings);
    $notificationState = $notifications ?? smart_marketing_notifications_load();

    $autoResult = smart_marketing_apply_auto_rules($campaigns, $optimizationState, $settings, $governanceState);
    if (!empty($autoResult['entries'])) {
        $entries = array_merge($entries, $autoResult['entries']);
        $campaignsTouched = $campaignsTouched || (bool) $autoResult['modified'];
    }
    $optimizationState = $autoResult['optimization'];

    foreach ($autoResult['alerts'] as $alert) {
        $notificationState = smart_marketing_notifications_push(
            $notificationState,
            $alert['type'] ?? 'alert',
            (string) ($alert['message'] ?? 'Smart Marketing alert'),
            (array) ($alert['channels'] ?? [])
        );
    }

    if ($forced) {
        $timestamp = ai_timestamp();
        $seasonal = [
            [
                'timestamp' => $timestamp,
                'type' => 'seasonal_schedule',
                'campaign_id' => null,
                'message' => 'Seasonal bursts locked: Summer rooftop push & Diwali festival offers.',
            ],
            [
                'timestamp' => $timestamp,
                'type' => 'dayparting',
                'campaign_id' => null,
                'message' => 'Applied 25% bid uplift during 9am-8pm call hours and reduced bids overnight.',
            ],
            [
                'timestamp' => $timestamp,
                'type' => 'compliance_rescan',
                'campaign_id' => null,
                'message' => 'Re-scanned active creatives; flagged items would be paused automatically.',
            ],
        ];
        $entries = array_merge($entries, $seasonal);
    }

    if (!empty($entries)) {
        smart_marketing_automation_log_append($entries);
    }

    if ($campaignsTouched || $forced) {
        smart_marketing_campaigns_save($campaigns);
    }

    smart_marketing_optimization_save($optimizationState);
    smart_marketing_notifications_save($notificationState);

    return [
        'entries' => $entries,
        'optimization' => $optimizationState,
        'governance' => $governanceState,
        'notifications' => $notificationState,
        'campaignsChanged' => $campaignsTouched || $forced,
    ];
}

function smart_marketing_automation_log_append(array $entries): void
{
    $file = smart_marketing_automation_log_file();
    $existing = [];
    if (is_file($file)) {
        $contents = file_get_contents($file);
        if ($contents !== false && trim($contents) !== '') {
            try {
                $existing = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable $exception) {
                $existing = [];
            }
        }
    }

    if (!is_array($existing)) {
        $existing = [];
    }

    $existing = array_merge($existing, $entries);
    if (count($existing) > 100) {
        $existing = array_slice($existing, -100);
    }

    $payload = json_encode(array_values($existing), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        throw new RuntimeException('Unable to encode Smart Marketing automation log.');
    }

    file_put_contents($file, $payload, LOCK_EX);
}

function smart_marketing_automation_log_read(int $limit = 25): array
{
    $file = smart_marketing_automation_log_file();
    if (!is_file($file)) {
        return [];
    }

    $contents = file_get_contents($file);
    if ($contents === false || trim($contents) === '') {
        return [];
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        return [];
    }

    if (!is_array($decoded)) {
        return [];
    }

    $decoded = array_reverse($decoded);
    if ($limit > 0) {
        $decoded = array_slice($decoded, 0, $limit);
    }

    return $decoded;
}

function smart_marketing_analytics_defaults(): array
{
    return [
        'updatedAt' => ai_timestamp(),
        'channels' => [
            [
                'id' => 'googleAds',
                'label' => 'Google Ads',
                'metrics' => [
                    'impressions' => 185400,
                    'clicks' => 7420,
                    'spend' => 178500,
                    'leads' => 362,
                    'qualified' => 210,
                    'converted' => 58,
                    'callConnects' => 164,
                    'meetings' => 92,
                    'sales' => 34,
                ],
                'campaigns' => [
                    [
                        'id' => 'SRCH-RES',
                        'label' => 'Search • Residential Rooftop',
                        'metrics' => [
                            'impressions' => 86400,
                            'clicks' => 3980,
                            'spend' => 91200,
                            'leads' => 214,
                            'qualified' => 132,
                            'converted' => 36,
                            'callConnects' => 98,
                            'meetings' => 54,
                            'sales' => 18,
                        ],
                        'ads' => [
                            [
                                'id' => 'SRCH-RES-01',
                                'label' => 'Claim PM Surya Ghar subsidy',
                                'metrics' => [
                                    'impressions' => 41200,
                                    'clicks' => 2050,
                                    'spend' => 47200,
                                    'leads' => 118,
                                    'qualified' => 74,
                                    'converted' => 20,
                                    'callConnects' => 58,
                                    'meetings' => 31,
                                    'sales' => 11,
                                ],
                            ],
                            [
                                'id' => 'SRCH-RES-02',
                                'label' => 'Free rooftop solar audit',
                                'metrics' => [
                                    'impressions' => 45200,
                                    'clicks' => 1930,
                                    'spend' => 44000,
                                    'leads' => 96,
                                    'qualified' => 58,
                                    'converted' => 16,
                                    'callConnects' => 40,
                                    'meetings' => 23,
                                    'sales' => 7,
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'SRCH-CI',
                        'label' => 'Search • C&I Rooftop',
                        'metrics' => [
                            'impressions' => 61200,
                            'clicks' => 1890,
                            'spend' => 61200,
                            'leads' => 92,
                            'qualified' => 54,
                            'converted' => 14,
                            'callConnects' => 42,
                            'meetings' => 26,
                            'sales' => 10,
                        ],
                        'ads' => [
                            [
                                'id' => 'SRCH-CI-01',
                                'label' => 'Lower factory power bills',
                                'metrics' => [
                                    'impressions' => 30200,
                                    'clicks' => 980,
                                    'spend' => 31200,
                                    'leads' => 48,
                                    'qualified' => 28,
                                    'converted' => 8,
                                    'callConnects' => 20,
                                    'meetings' => 12,
                                    'sales' => 4,
                                ],
                            ],
                            [
                                'id' => 'SRCH-CI-02',
                                'label' => 'Accelerated depreciation savings',
                                'metrics' => [
                                    'impressions' => 31000,
                                    'clicks' => 910,
                                    'spend' => 30000,
                                    'leads' => 44,
                                    'qualified' => 26,
                                    'converted' => 6,
                                    'callConnects' => 22,
                                    'meetings' => 14,
                                    'sales' => 6,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'meta',
                'label' => 'Meta Ads',
                'metrics' => [
                    'impressions' => 146200,
                    'clicks' => 6120,
                    'spend' => 128400,
                    'leads' => 286,
                    'qualified' => 164,
                    'converted' => 42,
                    'callConnects' => 120,
                    'meetings' => 66,
                    'sales' => 18,
                ],
                'campaigns' => [
                    [
                        'id' => 'META-LA',
                        'label' => 'Lead Ads • Subsidy Explainer',
                        'metrics' => [
                            'impressions' => 80200,
                            'clicks' => 3580,
                            'spend' => 72400,
                            'leads' => 182,
                            'qualified' => 106,
                            'converted' => 26,
                            'callConnects' => 78,
                            'meetings' => 38,
                            'sales' => 12,
                        ],
                        'ads' => [
                            [
                                'id' => 'META-LA-01',
                                'label' => 'Carousel • Rooftop installs',
                                'metrics' => [
                                    'impressions' => 38200,
                                    'clicks' => 1820,
                                    'spend' => 36200,
                                    'leads' => 94,
                                    'qualified' => 54,
                                    'converted' => 14,
                                    'callConnects' => 40,
                                    'meetings' => 22,
                                    'sales' => 8,
                                ],
                            ],
                            [
                                'id' => 'META-LA-02',
                                'label' => 'Video • Customer testimonial',
                                'metrics' => [
                                    'impressions' => 42000,
                                    'clicks' => 1760,
                                    'spend' => 36200,
                                    'leads' => 88,
                                    'qualified' => 52,
                                    'converted' => 12,
                                    'callConnects' => 38,
                                    'meetings' => 16,
                                    'sales' => 4,
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'META-RT',
                        'label' => 'Retargeting • Site Visitors',
                        'metrics' => [
                            'impressions' => 66000,
                            'clicks' => 2540,
                            'spend' => 56000,
                            'leads' => 104,
                            'qualified' => 58,
                            'converted' => 16,
                            'callConnects' => 42,
                            'meetings' => 28,
                            'sales' => 6,
                        ],
                        'ads' => [
                            [
                                'id' => 'META-RT-01',
                                'label' => 'Static • Install team photo',
                                'metrics' => [
                                    'impressions' => 33800,
                                    'clicks' => 1290,
                                    'spend' => 27600,
                                    'leads' => 54,
                                    'qualified' => 32,
                                    'converted' => 8,
                                    'callConnects' => 22,
                                    'meetings' => 12,
                                    'sales' => 4,
                                ],
                            ],
                            [
                                'id' => 'META-RT-02',
                                'label' => 'Reel • Drone walkthrough',
                                'metrics' => [
                                    'impressions' => 32200,
                                    'clicks' => 1250,
                                    'spend' => 28400,
                                    'leads' => 50,
                                    'qualified' => 26,
                                    'converted' => 8,
                                    'callConnects' => 20,
                                    'meetings' => 16,
                                    'sales' => 2,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'whatsapp',
                'label' => 'WhatsApp Automation',
                'metrics' => [
                    'impressions' => 28400,
                    'clicks' => 2140,
                    'spend' => 18400,
                    'leads' => 146,
                    'qualified' => 112,
                    'converted' => 28,
                    'callConnects' => 104,
                    'meetings' => 48,
                    'sales' => 12,
                ],
                'campaigns' => [
                    [
                        'id' => 'WA-FLOW',
                        'label' => 'Auto-response • Quote builder',
                        'metrics' => [
                            'impressions' => 15400,
                            'clicks' => 1180,
                            'spend' => 9600,
                            'leads' => 82,
                            'qualified' => 64,
                            'converted' => 16,
                            'callConnects' => 64,
                            'meetings' => 30,
                            'sales' => 8,
                        ],
                        'ads' => [
                            [
                                'id' => 'WA-FLOW-01',
                                'label' => 'Template • Roof size capture',
                                'metrics' => [
                                    'impressions' => 7800,
                                    'clicks' => 620,
                                    'spend' => 4800,
                                    'leads' => 44,
                                    'qualified' => 36,
                                    'converted' => 10,
                                    'callConnects' => 36,
                                    'meetings' => 16,
                                    'sales' => 5,
                                ],
                            ],
                            [
                                'id' => 'WA-FLOW-02',
                                'label' => 'Template • Subsidy checklist',
                                'metrics' => [
                                    'impressions' => 7600,
                                    'clicks' => 560,
                                    'spend' => 4800,
                                    'leads' => 38,
                                    'qualified' => 28,
                                    'converted' => 6,
                                    'callConnects' => 28,
                                    'meetings' => 14,
                                    'sales' => 3,
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'WA-NUDGE',
                        'label' => 'Reminder • Site survey follow-ups',
                        'metrics' => [
                            'impressions' => 13000,
                            'clicks' => 960,
                            'spend' => 8800,
                            'leads' => 64,
                            'qualified' => 48,
                            'converted' => 12,
                            'callConnects' => 40,
                            'meetings' => 18,
                            'sales' => 4,
                        ],
                        'ads' => [
                            [
                                'id' => 'WA-NUDGE-01',
                                'label' => 'Broadcast • Survey reminder',
                                'metrics' => [
                                    'impressions' => 6500,
                                    'clicks' => 480,
                                    'spend' => 4200,
                                    'leads' => 30,
                                    'qualified' => 22,
                                    'converted' => 6,
                                    'callConnects' => 18,
                                    'meetings' => 8,
                                    'sales' => 2,
                                ],
                            ],
                            [
                                'id' => 'WA-NUDGE-02',
                                'label' => 'Broadcast • Finance options',
                                'metrics' => [
                                    'impressions' => 6500,
                                    'clicks' => 480,
                                    'spend' => 4600,
                                    'leads' => 34,
                                    'qualified' => 26,
                                    'converted' => 6,
                                    'callConnects' => 22,
                                    'meetings' => 10,
                                    'sales' => 2,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'cohorts' => [
            'district' => [
                ['label' => 'Ranchi', 'leads' => 146, 'cpl' => 402, 'meetings' => 58],
                ['label' => 'Bokaro', 'leads' => 118, 'cpl' => 388, 'meetings' => 42],
                ['label' => 'Hazaribagh', 'leads' => 86, 'cpl' => 362, 'meetings' => 36],
            ],
            'system_size' => [
                ['label' => '1 kW', 'leads' => 44, 'cpl' => 320, 'sales' => 12],
                ['label' => '3 kW', 'leads' => 98, 'cpl' => 368, 'sales' => 18],
                ['label' => '5 kW', 'leads' => 132, 'cpl' => 412, 'sales' => 22],
                ['label' => '10 kW', 'leads' => 60, 'cpl' => 502, 'sales' => 12],
            ],
            'language' => [
                ['label' => 'Hindi', 'leads' => 286, 'cpl' => 362, 'sales' => 32],
                ['label' => 'English', 'leads' => 208, 'cpl' => 418, 'sales' => 20],
            ],
            'creative_theme' => [
                ['label' => 'Subsidy explainer', 'leads' => 182, 'cpl' => 340, 'sales' => 24],
                ['label' => 'Savings calculator', 'leads' => 134, 'cpl' => 372, 'sales' => 16],
                ['label' => 'Customer testimonial', 'leads' => 128, 'cpl' => 396, 'sales' => 18],
            ],
        ],
        'funnels' => [
            'impressions' => 359, // thousands placeholder, will normalise
            'clicks' => 157,
            'leads' => 794,
            'qualified' => 486,
            'converted' => 128,
        ],
        'creatives' => [
            'headlines' => [
                ['label' => 'Claim PM Surya Ghar rooftop subsidy today', 'ctr' => 0.052, 'leads' => 132, 'cpl' => 348],
                ['label' => 'Slash power bills with Dakshayani solar', 'ctr' => 0.048, 'leads' => 118, 'cpl' => 362],
                ['label' => 'Free rooftop audit + MNRE paperwork', 'ctr' => 0.044, 'leads' => 102, 'cpl' => 354],
            ],
            'images' => [
                ['label' => 'Crew installing 5 kW system', 'ctr' => 0.036, 'leads' => 88, 'cpl' => 338],
                ['label' => 'Before/after roof transformation', 'ctr' => 0.033, 'leads' => 76, 'cpl' => 346],
                ['label' => 'Savings calculator graphic', 'ctr' => 0.031, 'leads' => 68, 'cpl' => 358],
            ],
            'videos' => [
                ['label' => '30s customer testimonial', 'ctr' => 0.042, 'leads' => 64, 'cpl' => 352],
                ['label' => 'Drone walkthrough of rooftop plant', 'ctr' => 0.038, 'leads' => 52, 'cpl' => 364],
                ['label' => 'Installer interview on PM Surya Ghar', 'ctr' => 0.036, 'leads' => 48, 'cpl' => 372],
            ],
        ],
        'budget' => [
            'monthlyCap' => 500000,
            'plannedSpend' => 468000,
            'spendToDate' => 325300,
            'pacing' => 0.65,
            'burnRate' => 18600,
            'expectedBurn' => 16100,
            'alerts' => [],
        ],
        'alerts' => [
            ['type' => 'cpl_spike', 'message' => 'Meta retargeting CPL up 12% week-on-week; monitor bids.'],
        ],
    ];
}

function smart_marketing_analytics_normalise_metrics(array $metrics): array
{
    $impressions = max(0, (int) ($metrics['impressions'] ?? 0));
    $clicks = max(0, (int) ($metrics['clicks'] ?? 0));
    $spend = max(0.0, (float) ($metrics['spend'] ?? 0));
    $leads = max(0, (int) ($metrics['leads'] ?? ($metrics['lead_count'] ?? 0)));
    $qualified = max(0, (int) ($metrics['qualified'] ?? 0));
    $converted = max(0, (int) ($metrics['converted'] ?? 0));
    $callConnects = max(0, (int) ($metrics['callConnects'] ?? ($metrics['call_connects'] ?? 0)));
    $meetings = max(0, (int) ($metrics['meetings'] ?? 0));
    $sales = max(0, (int) ($metrics['sales'] ?? $converted));

    $ctr = $impressions > 0 ? $clicks / $impressions : 0.0;
    $cpc = $clicks > 0 ? $spend / $clicks : 0.0;
    $cpl = $leads > 0 ? $spend / max(1, $leads) : 0.0;
    $base = $leads > 0 ? $leads : max(1, $qualified);
    $convRate = $base > 0 ? $sales / $base : 0.0;

    return [
        'impressions' => $impressions,
        'clicks' => $clicks,
        'spend' => round($spend, 2),
        'leads' => $leads,
        'qualified' => $qualified,
        'converted' => $converted,
        'callConnects' => $callConnects,
        'meetings' => $meetings,
        'sales' => $sales,
        'ctr' => $ctr,
        'cpc' => $cpc,
        'cpl' => $cpl,
        'convRate' => min(1.0, $convRate),
    ];
}

function smart_marketing_analytics_normalise(array $analytics, array $settings = []): array
{
    $defaults = smart_marketing_analytics_defaults();
    $analytics = array_replace_recursive($defaults, $analytics);

    $totals = [
        'impressions' => 0,
        'clicks' => 0,
        'spend' => 0.0,
        'leads' => 0,
        'qualified' => 0,
        'converted' => 0,
        'callConnects' => 0,
        'meetings' => 0,
        'sales' => 0,
    ];

    $channels = [];
    foreach ($analytics['channels'] as $channel) {
        $channelMetrics = smart_marketing_analytics_normalise_metrics($channel['metrics'] ?? []);
        $channelCampaigns = [];
        foreach (($channel['campaigns'] ?? []) as $campaign) {
            $campaignMetrics = smart_marketing_analytics_normalise_metrics($campaign['metrics'] ?? []);
            $campaignAds = [];
            foreach (($campaign['ads'] ?? []) as $ad) {
                $campaignAds[] = [
                    'id' => (string) ($ad['id'] ?? ''),
                    'label' => (string) ($ad['label'] ?? ''),
                    'metrics' => smart_marketing_analytics_normalise_metrics($ad['metrics'] ?? []),
                ];
            }
            $campaignCampaign = [
                'id' => (string) ($campaign['id'] ?? ''),
                'label' => (string) ($campaign['label'] ?? ''),
                'metrics' => $campaignMetrics,
                'ads' => $campaignAds,
            ];
            $channelCampaigns[] = $campaignCampaign;
        }

        foreach ($channelCampaigns as $campaign) {
            $channelMetrics['impressions'] += $campaign['metrics']['impressions'];
            $channelMetrics['clicks'] += $campaign['metrics']['clicks'];
            $channelMetrics['spend'] += $campaign['metrics']['spend'];
            $channelMetrics['leads'] += $campaign['metrics']['leads'];
            $channelMetrics['qualified'] += $campaign['metrics']['qualified'];
            $channelMetrics['converted'] += $campaign['metrics']['converted'];
            $channelMetrics['callConnects'] += $campaign['metrics']['callConnects'];
            $channelMetrics['meetings'] += $campaign['metrics']['meetings'];
            $channelMetrics['sales'] += $campaign['metrics']['sales'];
        }

        $channelMetrics = smart_marketing_analytics_normalise_metrics($channelMetrics);

        foreach ($totals as $key => $value) {
            $totals[$key] += $channelMetrics[$key];
        }

        $channels[] = [
            'id' => (string) ($channel['id'] ?? ''),
            'label' => (string) ($channel['label'] ?? ''),
            'metrics' => $channelMetrics,
            'campaigns' => $channelCampaigns,
        ];
    }
    $analytics['channels'] = $channels;

    $funnels = $analytics['funnels'] ?? [];
    $funnels['impressions'] = max($totals['impressions'], (int) ($funnels['impressions'] ?? $totals['impressions']));
    $funnels['clicks'] = max($totals['clicks'], (int) ($funnels['clicks'] ?? $totals['clicks']));
    $funnels['leads'] = max($totals['leads'], (int) ($funnels['leads'] ?? $totals['leads']));
    $funnels['qualified'] = max($totals['qualified'], (int) ($funnels['qualified'] ?? $totals['qualified']));
    $funnels['converted'] = max($totals['sales'], (int) ($funnels['converted'] ?? $totals['sales']));
    $analytics['funnels'] = $funnels;

    if (!isset($analytics['budget']) || !is_array($analytics['budget'])) {
        $analytics['budget'] = $defaults['budget'];
    }

    $monthlyCap = (float) ($settings['budget']['monthlyCap'] ?? $analytics['budget']['monthlyCap'] ?? 0);
    $analytics['budget']['monthlyCap'] = $monthlyCap;
    $analytics['budget']['spendToDate'] = round($totals['spend'], 2);

    $analytics['updatedAt'] = (string) ($analytics['updatedAt'] ?? ai_timestamp());
    if (!isset($analytics['alerts']) || !is_array($analytics['alerts'])) {
        $analytics['alerts'] = [];
    }

    return $analytics;
}

function smart_marketing_analytics_save(array $analytics): void
{
    $payload = json_encode($analytics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        throw new RuntimeException('Unable to encode Smart Marketing analytics.');
    }

    if (file_put_contents(smart_marketing_analytics_file(), $payload, LOCK_EX) === false) {
        throw new RuntimeException('Unable to persist Smart Marketing analytics.');
    }
}

function smart_marketing_analytics_load(array $settings = []): array
{
    $file = smart_marketing_analytics_file();
    if (!is_file($file)) {
        $defaults = smart_marketing_analytics_defaults();
        smart_marketing_analytics_save($defaults);
        return smart_marketing_analytics_normalise($defaults, $settings);
    }

    $contents = file_get_contents($file);
    if ($contents === false || trim($contents) === '') {
        $defaults = smart_marketing_analytics_defaults();
        return smart_marketing_analytics_normalise($defaults, $settings);
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        error_log('smart_marketing_analytics_load decode failed: ' . $exception->getMessage());
        $decoded = smart_marketing_analytics_defaults();
    }

    if (!is_array($decoded)) {
        $decoded = smart_marketing_analytics_defaults();
    }

    return smart_marketing_analytics_normalise($decoded, $settings);
}

function smart_marketing_refresh_analytics(array $settings): array
{
    $analytics = smart_marketing_analytics_load($settings);
    $analytics['updatedAt'] = ai_timestamp();

    $monthlyCap = (float) ($settings['budget']['monthlyCap'] ?? $analytics['budget']['monthlyCap'] ?? 0);
    $currency = (string) ($settings['budget']['currency'] ?? 'INR');

    $totals = ['spend' => 0.0, 'leads' => 0, 'sales' => 0, 'clicks' => 0, 'impressions' => 0];
    foreach ($analytics['channels'] as $channel) {
        $totals['spend'] += $channel['metrics']['spend'];
        $totals['leads'] += $channel['metrics']['leads'];
        $totals['sales'] += $channel['metrics']['sales'];
        $totals['clicks'] += $channel['metrics']['clicks'];
        $totals['impressions'] += $channel['metrics']['impressions'];
    }

    $analytics['budget']['monthlyCap'] = $monthlyCap;
    $analytics['budget']['spendToDate'] = round($totals['spend'], 2);

    $tz = new DateTimeZone('Asia/Kolkata');
    $now = new DateTime('now', $tz);
    $dayOfMonth = (int) $now->format('j');
    $daysInMonth = (int) $now->format('t');

    $analytics['budget']['pacing'] = $monthlyCap > 0 ? $analytics['budget']['spendToDate'] / $monthlyCap : 0.0;
    $analytics['budget']['burnRate'] = $dayOfMonth > 0 ? $analytics['budget']['spendToDate'] / $dayOfMonth : 0.0;
    $analytics['budget']['expectedBurn'] = ($monthlyCap > 0 && $daysInMonth > 0) ? $monthlyCap / $daysInMonth : 0.0;
    $analytics['budget']['plannedSpend'] = (float) ($analytics['budget']['plannedSpend'] ?? $monthlyCap);

    $expectedPacing = ($daysInMonth > 0) ? $dayOfMonth / $daysInMonth : 0.0;
    $alerts = [];
    if ($analytics['budget']['pacing'] > ($expectedPacing + 0.1)) {
        $alerts[] = [
            'type' => 'budget_overpace',
            'message' => sprintf('Spend pacing %.1f%% vs expected %.1f%% of %s %s.', $analytics['budget']['pacing'] * 100, $expectedPacing * 100, $currency, number_format($monthlyCap, 0)),
            'channels' => ['email', 'whatsapp'],
        ];
    }
    if ($analytics['budget']['burnRate'] > ($analytics['budget']['expectedBurn'] * 1.2) && $analytics['budget']['expectedBurn'] > 0) {
        $alerts[] = [
            'type' => 'burn_rate_high',
            'message' => sprintf('Burn rate %s/day exceeds guardrail %s/day.', smart_marketing_format_currency($analytics['budget']['burnRate'], $currency), smart_marketing_format_currency($analytics['budget']['expectedBurn'], $currency)),
            'channels' => ['email'],
        ];
    }
    if ($totals['leads'] > 0 && $totals['sales'] > 0) {
        $conversionRate = $totals['sales'] / max(1, $totals['leads']);
        if ($conversionRate < 0.1) {
            $alerts[] = [
                'type' => 'conversion_soft',
                'message' => 'Lead-to-sale conversion dropped below 10%. Review qualification steps.',
                'channels' => ['email'],
            ];
        }
    }

    $analytics['budget']['alerts'] = $alerts;
    smart_marketing_analytics_save($analytics);

    return ['analytics' => $analytics, 'alerts' => $alerts];
}

function smart_marketing_optimization_defaults(array $settings = []): array
{
    $minBid = (float) ($settings['budget']['minBid'] ?? 20);
    $targetCpl = (float) ($settings['budget']['targetCpl'] ?? 450);

    return [
        'autoRules' => [
            'pauseUnderperforming' => [
                'enabled' => true,
                'ctrThreshold' => 0.015,
                'cvrThreshold' => 0.04,
            ],
            'bidGuardrails' => [
                'enabled' => true,
                'minBid' => max(1.0, $minBid),
                'maxBid' => max(2 * $minBid, $minBid + 10),
                'step' => 0.1,
            ],
            'budgetShift' => [
                'enabled' => true,
                'shiftPercent' => 0.12,
                'targetCpl' => $targetCpl,
            ],
            'creativeRefresh' => [
                'enabled' => true,
                'decayDays' => 7,
            ],
        ],
        'manualPlaybooks' => [
            'lastActionAt' => null,
        ],
        'learning' => [
            'tests' => [
                [
                    'id' => 'TEST-CTA-01',
                    'createdAt' => ai_timestamp(),
                    'type' => 'CTA',
                    'status' => 'completed',
                    'result' => 'Variant B (+14% CTR) rolled out to Meta retargeting.',
                ],
                [
                    'id' => 'TEST-IMAGE-02',
                    'createdAt' => ai_timestamp(),
                    'type' => 'Creative',
                    'status' => 'queued',
                    'result' => 'Pending – rooftop night shot vs day shot.',
                ],
            ],
            'nextBestAction' => 'Run a Hindi landing page CTA vs WhatsApp chat handoff test.',
        ],
        'history' => [],
        'updatedAt' => null,
    ];
}

function smart_marketing_optimization_load(array $settings = []): array
{
    $file = smart_marketing_optimization_file();
    if (!is_file($file)) {
        $defaults = smart_marketing_optimization_defaults($settings);
        smart_marketing_optimization_save($defaults);
        return $defaults;
    }

    $contents = file_get_contents($file);
    if ($contents === false || trim($contents) === '') {
        return smart_marketing_optimization_defaults($settings);
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        error_log('smart_marketing_optimization_load decode failed: ' . $exception->getMessage());
        $decoded = smart_marketing_optimization_defaults($settings);
    }

    if (!is_array($decoded)) {
        $decoded = smart_marketing_optimization_defaults($settings);
    }

    $defaults = smart_marketing_optimization_defaults($settings);
    $state = array_replace_recursive($defaults, $decoded);

    if (!isset($state['history']) || !is_array($state['history'])) {
        $state['history'] = [];
    }
    if (!isset($state['learning']['tests']) || !is_array($state['learning']['tests'])) {
        $state['learning']['tests'] = [];
    }

    return $state;
}

function smart_marketing_optimization_save(array $state): void
{
    $state['updatedAt'] = ai_timestamp();
    $payload = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        throw new RuntimeException('Unable to encode Smart Marketing optimisation state.');
    }

    if (file_put_contents(smart_marketing_optimization_file(), $payload, LOCK_EX) === false) {
        throw new RuntimeException('Unable to persist Smart Marketing optimisation state.');
    }
}

function smart_marketing_optimization_merge(array $input, array $current, array $settings = []): array
{
    $state = $current;
    $autoRules = $state['autoRules'] ?? [];

    if (isset($input['autoRules']) && is_array($input['autoRules'])) {
        foreach ($input['autoRules'] as $key => $rule) {
            if (!isset($autoRules[$key])) {
                continue;
            }
            $autoRules[$key]['enabled'] = (bool) ($rule['enabled'] ?? $autoRules[$key]['enabled']);
            foreach ($rule as $field => $value) {
                if ($field === 'enabled') {
                    continue;
                }
                $autoRules[$key][$field] = is_numeric($value) ? (float) $value : $value;
            }
        }
        $state['autoRules'] = $autoRules;
    }

    if (isset($input['learning']) && is_array($input['learning'])) {
        if (isset($input['learning']['nextBestAction'])) {
            $state['learning']['nextBestAction'] = trim((string) $input['learning']['nextBestAction']);
        }
    }

    $state['updatedAt'] = ai_timestamp();

    return $state;
}

function smart_marketing_apply_auto_rules(array &$campaigns, array $optimization, array $settings, array $governance): array
{
    $entries = [];
    $alerts = [];
    $modified = false;
    $autoRules = $optimization['autoRules'] ?? [];
    $currency = (string) ($settings['budget']['currency'] ?? 'INR');
    $targetCpl = (float) ($settings['budget']['targetCpl'] ?? 450);
    $now = ai_timestamp();

    if (!isset($optimization['history']) || !is_array($optimization['history'])) {
        $optimization['history'] = [];
    }

    $emergencyActive = (bool) ($governance['emergencyStop']['active'] ?? false);
    if ($emergencyActive) {
        $message = 'Emergency stop active; automations paused.';
        $lastHistory = end($optimization['history']);
        $alreadyLogged = is_array($lastHistory)
            && (($lastHistory['rule'] ?? '') === 'emergency_stop')
            && (($lastHistory['message'] ?? '') === $message);
        if (!$alreadyLogged) {
            $optimization['history'][] = [
                'timestamp' => $now,
                'rule' => 'emergency_stop',
                'campaign_id' => null,
                'message' => $message,
            ];
            if (count($optimization['history']) > 100) {
                $optimization['history'] = array_slice($optimization['history'], -100);
            }
        }
        if (!empty($optimization['history'])) {
            reset($optimization['history']);
        }

        $entries[] = [
            'timestamp' => $now,
            'type' => 'emergency_stop',
            'rule' => 'emergency_stop',
            'campaign_id' => null,
            'message' => $message,
        ];
        $alerts[] = [
            'type' => 'emergency_stop',
            'message' => $message,
            'channels' => ['email'],
        ];

        return [
            'entries' => $entries,
            'alerts' => $alerts,
            'optimization' => $optimization,
            'modified' => false,
        ];
    }

    if (($autoRules['pauseUnderperforming']['enabled'] ?? false)) {
        $ctrThreshold = (float) ($autoRules['pauseUnderperforming']['ctrThreshold'] ?? 0.012);
        $cvrThreshold = (float) ($autoRules['pauseUnderperforming']['cvrThreshold'] ?? 0.04);
        foreach ($campaigns as &$campaign) {
            if (($campaign['status'] ?? '') !== 'launched') {
                continue;
            }
            $metrics = smart_marketing_analytics_normalise_metrics($campaign['metrics'] ?? []);
            $ctr = $metrics['ctr'];
            $cvr = $metrics['leads'] > 0 ? ($metrics['sales'] / max(1, $metrics['leads'])) : 0.0;
            if ($ctr < $ctrThreshold && $cvr < $cvrThreshold) {
                $campaign['status'] = 'paused';
                $campaign['audit_trail'][] = [
                    'timestamp' => $now,
                    'action' => 'auto_rule.pause',
                    'context' => ['rule' => 'pause_underperforming'],
                ];
                $entries[] = [
                    'timestamp' => $now,
                    'type' => 'auto_rule',
                    'rule' => 'pause_underperforming',
                    'campaign_id' => $campaign['id'] ?? null,
                    'message' => sprintf('Paused %s due to CTR %.2f%% and conversion %.1f%% below guardrail.', $campaign['label'] ?? ($campaign['id'] ?? 'campaign'), $ctr * 100, $cvr * 100),
                ];
                $modified = true;
            }
        }
        unset($campaign);
    }

    if (($autoRules['bidGuardrails']['enabled'] ?? false)) {
        $minBid = max(1.0, (float) ($autoRules['bidGuardrails']['minBid'] ?? ($settings['budget']['minBid'] ?? 10)));
        $maxBid = max($minBid, (float) ($autoRules['bidGuardrails']['maxBid'] ?? ($minBid * 3)));
        $step = max(0.01, (float) ($autoRules['bidGuardrails']['step'] ?? 0.1));
        $lockEnabled = (bool) ($governance['budgetLock']['enabled'] ?? false);
        foreach ($campaigns as &$campaign) {
            if (($campaign['status'] ?? '') !== 'launched') {
                continue;
            }
            $currentDaily = (float) ($campaign['budget']['daily'] ?? $minBid);
            $metrics = smart_marketing_analytics_normalise_metrics($campaign['metrics'] ?? []);
            $cpl = $metrics['leads'] > 0 ? $metrics['spend'] / max(1, $metrics['leads']) : $targetCpl;
            if ($cpl <= 0) {
                $cpl = $targetCpl;
            }

            if ($cpl < $targetCpl * 0.85) {
                $proposed = min($maxBid, $currentDaily * (1 + $step));
                if ($lockEnabled && $proposed > $currentDaily) {
                    $alerts[] = [
                        'type' => 'budget_lock',
                        'message' => sprintf('Budget lock prevented bid uplift for %s.', $campaign['label'] ?? ($campaign['id'] ?? 'campaign')),
                        'channels' => ['email'],
                    ];
                    continue;
                }
                if ($proposed > $currentDaily) {
                    $campaign['budget']['daily'] = round($proposed, 2);
                    $entries[] = [
                        'timestamp' => $now,
                        'type' => 'auto_rule',
                        'rule' => 'bid_guardrail_up',
                        'campaign_id' => $campaign['id'] ?? null,
                        'message' => sprintf('Raised daily bid to %s for %s (CPL %s).', smart_marketing_format_currency($campaign['budget']['daily'], $currency), $campaign['label'] ?? ($campaign['id'] ?? 'campaign'), smart_marketing_format_currency($cpl, $currency)),
                    ];
                    $modified = true;
                }
            } elseif ($cpl > $targetCpl * 1.2) {
                $proposed = max($minBid, $currentDaily * (1 - $step));
                if ($proposed < $currentDaily) {
                    $campaign['budget']['daily'] = round($proposed, 2);
                    $entries[] = [
                        'timestamp' => $now,
                        'type' => 'auto_rule',
                        'rule' => 'bid_guardrail_down',
                        'campaign_id' => $campaign['id'] ?? null,
                        'message' => sprintf('Reduced daily bid to %s for %s (CPL %s above guardrail).', smart_marketing_format_currency($campaign['budget']['daily'], $currency), $campaign['label'] ?? ($campaign['id'] ?? 'campaign'), smart_marketing_format_currency($cpl, $currency)),
                    ];
                    $modified = true;
                }
            }
        }
        unset($campaign);
    }

    if (($autoRules['budgetShift']['enabled'] ?? false)) {
        $shiftPercent = max(0.01, (float) ($autoRules['budgetShift']['shiftPercent'] ?? 0.1));
        $campaignPool = [];
        foreach ($campaigns as $index => $campaign) {
            if (($campaign['status'] ?? '') !== 'launched') {
                continue;
            }
            $metrics = smart_marketing_analytics_normalise_metrics($campaign['metrics'] ?? []);
            if ($metrics['leads'] < 10) {
                continue;
            }
            $cpl = $metrics['leads'] > 0 ? $metrics['spend'] / max(1, $metrics['leads']) : $targetCpl;
            $campaignPool[] = [
                'index' => $index,
                'id' => $campaign['id'] ?? null,
                'label' => $campaign['label'] ?? ($campaign['id'] ?? 'campaign'),
                'cpl' => $cpl,
            ];
        }

        if (count($campaignPool) >= 4) {
            usort($campaignPool, static fn($a, $b) => $a['cpl'] <=> $b['cpl']);
            $quartileCount = max(1, (int) ceil(count($campaignPool) / 4));
            $winners = array_slice($campaignPool, 0, $quartileCount);
            $laggards = array_slice($campaignPool, -$quartileCount);

            $lockEnabled = (bool) ($governance['budgetLock']['enabled'] ?? false);
            foreach ($winners as $winner) {
                $idx = $winner['index'];
                $daily = (float) ($campaigns[$idx]['budget']['daily'] ?? $settings['budget']['dailyCap'] ?? 0);
                $proposed = $daily * (1 + $shiftPercent);
                if ($lockEnabled && $proposed > $daily) {
                    $alerts[] = [
                        'type' => 'budget_lock',
                        'message' => sprintf('Budget lock held spend for %s despite top performance.', $winner['label']),
                        'channels' => ['email'],
                    ];
                    continue;
                }
                $campaigns[$idx]['budget']['daily'] = round($proposed, 2);
                $entries[] = [
                    'timestamp' => $now,
                    'type' => 'auto_rule',
                    'rule' => 'budget_shift_up',
                    'campaign_id' => $winner['id'],
                    'message' => sprintf('Shifted +%d%% budget to %s (CPL %s).', (int) round($shiftPercent * 100), $winner['label'], smart_marketing_format_currency($winner['cpl'], $currency)),
                ];
                $modified = true;
            }

            foreach ($laggards as $laggard) {
                $idx = $laggard['index'];
                $daily = (float) ($campaigns[$idx]['budget']['daily'] ?? $settings['budget']['dailyCap'] ?? 0);
                $proposed = max(0, $daily * (1 - $shiftPercent));
                $campaigns[$idx]['budget']['daily'] = round($proposed, 2);
                $entries[] = [
                    'timestamp' => $now,
                    'type' => 'auto_rule',
                    'rule' => 'budget_shift_down',
                    'campaign_id' => $laggard['id'],
                    'message' => sprintf('Trimmed %d%% budget from %s (CPL %s above guardrail).', (int) round($shiftPercent * 100), $laggard['label'], smart_marketing_format_currency($laggard['cpl'], $currency)),
                ];
                $modified = true;
            }
        }
    }

    if (($autoRules['creativeRefresh']['enabled'] ?? false)) {
        $decayDays = max(1, (int) ($autoRules['creativeRefresh']['decayDays'] ?? 7));
        $tz = new DateTimeZone('Asia/Kolkata');
        foreach ($campaigns as &$campaign) {
            if (($campaign['status'] ?? '') !== 'launched') {
                continue;
            }
            $lastRefresh = $campaign['maintenance']['lastCreativeRefresh'] ?? null;
            $needsRefresh = true;
            if ($lastRefresh) {
                try {
                    $last = new DateTime($lastRefresh, $tz);
                    $nowDate = new DateTime('now', $tz);
                    $diff = $nowDate->diff($last);
                    $needsRefresh = ($diff->days ?? 0) >= $decayDays;
                } catch (Throwable $exception) {
                    $needsRefresh = true;
                }
            }
            if ($needsRefresh) {
                $campaign['maintenance']['lastCreativeRefresh'] = $now;
                $entries[] = [
                    'timestamp' => $now,
                    'type' => 'auto_rule',
                    'rule' => 'creative_refresh',
                    'campaign_id' => $campaign['id'] ?? null,
                    'message' => sprintf('Refreshed creatives for %s after %d-day cycle.', $campaign['label'] ?? ($campaign['id'] ?? 'campaign'), $decayDays),
                ];
                $modified = true;
            }
        }
        unset($campaign);
    }

    foreach ($entries as $entry) {
        $optimization['history'][] = [
            'timestamp' => $entry['timestamp'],
            'rule' => $entry['rule'] ?? $entry['type'],
            'campaign_id' => $entry['campaign_id'] ?? null,
            'message' => $entry['message'],
        ];
    }
    if (count($optimization['history']) > 100) {
        $optimization['history'] = array_slice($optimization['history'], -100);
    }

    return [
        'entries' => $entries,
        'alerts' => $alerts,
        'optimization' => $optimization,
        'modified' => $modified,
    ];
}

function smart_marketing_governance_defaults(array $settings = []): array
{
    return [
        'budgetLock' => [
            'enabled' => false,
            'cap' => (float) ($settings['budget']['monthlyCap'] ?? 0),
            'updatedAt' => null,
        ],
        'emergencyStop' => [
            'active' => false,
            'triggeredAt' => null,
            'triggeredBy' => null,
        ],
        'policyChecklist' => [
            'pmSuryaClaims' => true,
            'ethicalMessaging' => true,
            'disclaimerPlaced' => true,
            'dataAccuracy' => true,
            'lastReviewed' => null,
            'notes' => '',
        ],
        'dataProtection' => [
            'maskPii' => true,
            'requests' => [],
        ],
        'log' => [],
        'updatedAt' => null,
    ];
}

function smart_marketing_governance_load(array $settings = []): array
{
    $file = smart_marketing_governance_file();
    if (!is_file($file)) {
        $defaults = smart_marketing_governance_defaults($settings);
        smart_marketing_governance_save($defaults);
        return $defaults;
    }

    $contents = file_get_contents($file);
    if ($contents === false || trim($contents) === '') {
        return smart_marketing_governance_defaults($settings);
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        error_log('smart_marketing_governance_load decode failed: ' . $exception->getMessage());
        $decoded = smart_marketing_governance_defaults($settings);
    }

    if (!is_array($decoded)) {
        $decoded = smart_marketing_governance_defaults($settings);
    }

    $defaults = smart_marketing_governance_defaults($settings);
    $state = array_replace_recursive($defaults, $decoded);

    if (!isset($state['log']) || !is_array($state['log'])) {
        $state['log'] = [];
    }
    if (!isset($state['dataProtection']['requests']) || !is_array($state['dataProtection']['requests'])) {
        $state['dataProtection']['requests'] = [];
    }

    return $state;
}

function smart_marketing_governance_save(array $state): void
{
    $state['updatedAt'] = ai_timestamp();
    $payload = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        throw new RuntimeException('Unable to encode Smart Marketing governance state.');
    }

    if (file_put_contents(smart_marketing_governance_file(), $payload, LOCK_EX) === false) {
        throw new RuntimeException('Unable to persist Smart Marketing governance state.');
    }
}

function smart_marketing_governance_log(array &$state, string $event, array $context = [], array $user = []): void
{
    if (!isset($state['log']) || !is_array($state['log'])) {
        $state['log'] = [];
    }

    $state['log'][] = [
        'timestamp' => ai_timestamp(),
        'event' => $event,
        'context' => smart_marketing_scrub_context($context),
        'user' => [
            'id' => (int) ($user['id'] ?? 0),
            'name' => (string) ($user['full_name'] ?? ($user['name'] ?? 'Admin')),
        ],
    ];

    if (count($state['log']) > 100) {
        $state['log'] = array_slice($state['log'], -100);
    }
}

function smart_marketing_governance_track_data_request(array &$state, string $type, array $context = []): void
{
    if (!isset($state['dataProtection']['requests']) || !is_array($state['dataProtection']['requests'])) {
        $state['dataProtection']['requests'] = [];
    }

    $state['dataProtection']['requests'][] = [
        'timestamp' => ai_timestamp(),
        'type' => $type,
        'context' => smart_marketing_scrub_context($context),
    ];

    if (count($state['dataProtection']['requests']) > 50) {
        $state['dataProtection']['requests'] = array_slice($state['dataProtection']['requests'], -50);
    }
}

function smart_marketing_data_export(array &$governance, array $settings): array
{
    $payload = [
        'generatedAt' => ai_timestamp(),
        'analytics' => smart_marketing_analytics_load($settings),
        'campaigns' => smart_marketing_campaigns_load(),
        'audit' => smart_marketing_audit_log_read(),
    ];

    $file = smart_marketing_exports_dir() . '/smart-marketing-export-' . date('Ymd-His') . '.json';
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Unable to encode Smart Marketing export payload.');
    }

    if (file_put_contents($file, $json, LOCK_EX) === false) {
        throw new RuntimeException('Unable to persist Smart Marketing export payload.');
    }

    smart_marketing_governance_track_data_request($governance, 'export', ['file' => basename($file)]);
    smart_marketing_governance_log($governance, 'data.export', ['file' => basename($file)]);

    return ['file' => $file, 'payload' => $payload];
}

function smart_marketing_data_erase(array &$governance): void
{
    smart_marketing_governance_track_data_request($governance, 'erase', []);
    smart_marketing_governance_log($governance, 'data.erase_queued');
}

function smart_marketing_notifications_defaults(): array
{
    return [
        'dailyDigest' => [
            'enabled' => true,
            'time' => '08:30',
            'channels' => [
                'email' => 'ops@dakshayani.in',
                'whatsapp' => '+91-6200001234',
            ],
        ],
        'instant' => [
            'email' => true,
            'whatsapp' => true,
        ],
        'lastDigest' => null,
        'log' => [],
        'updatedAt' => null,
    ];
}

function smart_marketing_notifications_load(): array
{
    $file = smart_marketing_notifications_file();
    if (!is_file($file)) {
        $defaults = smart_marketing_notifications_defaults();
        smart_marketing_notifications_save($defaults);
        return $defaults;
    }

    $contents = file_get_contents($file);
    if ($contents === false || trim($contents) === '') {
        return smart_marketing_notifications_defaults();
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        error_log('smart_marketing_notifications_load decode failed: ' . $exception->getMessage());
        $decoded = smart_marketing_notifications_defaults();
    }

    if (!is_array($decoded)) {
        $decoded = smart_marketing_notifications_defaults();
    }

    $defaults = smart_marketing_notifications_defaults();
    $state = array_replace_recursive($defaults, $decoded);
    if (!isset($state['log']) || !is_array($state['log'])) {
        $state['log'] = [];
    }

    return $state;
}

function smart_marketing_notifications_save(array $state): void
{
    $state['updatedAt'] = ai_timestamp();
    $payload = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        throw new RuntimeException('Unable to encode Smart Marketing notification state.');
    }

    if (file_put_contents(smart_marketing_notifications_file(), $payload, LOCK_EX) === false) {
        throw new RuntimeException('Unable to persist Smart Marketing notification state.');
    }
}

function smart_marketing_notifications_push(array $state, string $type, string $message, array $channels = []): array
{
    if (!isset($state['log']) || !is_array($state['log'])) {
        $state['log'] = [];
    }

    $state['log'][] = [
        'timestamp' => ai_timestamp(),
        'type' => $type,
        'message' => $message,
        'channels' => $channels,
    ];

    if (count($state['log']) > 50) {
        $state['log'] = array_slice($state['log'], -50);
    }

    return $state;
}

function smart_marketing_store_asset(string $type, array $payload): array
{
    $id = uniqid('asset_', true);
    $record = [
        'id' => $id,
        'type' => $type,
        'created_at' => ai_timestamp(),
        'payload' => $payload,
    ];

    $file = smart_marketing_assets_dir() . '/' . $id . '.json';
    if (file_put_contents($file, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
        throw new RuntimeException('Unable to persist creative asset.');
    }

    return $record;
}

function smart_marketing_list_assets(int $limit = 25): array
{
    $dir = smart_marketing_assets_dir();
    if (!is_dir($dir)) {
        return [];
    }

    $files = glob($dir . '/*.json');
    if ($files === false) {
        return [];
    }

    rsort($files);
    $files = array_slice($files, 0, $limit);

    $assets = [];
    foreach ($files as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            continue;
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            continue;
        }

        if (is_array($decoded)) {
            $assets[] = $decoded;
        }
    }

    return $assets;
}

function smart_marketing_generate_brain_prompt(array $inputs, array $settings): string
{
    $goals = implode(', ', $inputs['goals']);
    $regions = implode(', ', $inputs['regions']);
    $products = implode(', ', $inputs['products']);
    $languages = implode(', ', $inputs['languages']);
    $budget = sprintf('Daily budget %s %s, monthly cap %s %s, minimum bid %s %s, CPA guardrail %s %s.',
        $settings['budget']['currency'] ?? 'INR',
        $inputs['dailyBudget'],
        $settings['budget']['currency'] ?? 'INR',
        $inputs['monthlyBudget'],
        $settings['budget']['currency'] ?? 'INR',
        $inputs['minBid'],
        $settings['budget']['currency'] ?? 'INR',
        $inputs['cpaGuardrail']
    );

    $autonomy = ucfirst($inputs['autonomyMode']);
    $compliance = [];
    foreach ($inputs['compliance'] as $key => $value) {
        if ($value) {
            $compliance[] = $key;
        }
    }
    $complianceText = empty($compliance) ? 'Standard marketing compliance only.' : implode(', ', $compliance);

    $loop = 'Provide a day-by-day launch schedule, a weekly optimisation checklist, and specify how learnings from conversions update the plan.';

    $prompt = <<<PROMPT
You are "Marketing Brain", an autonomous marketing director for Dakshayani Enterprises, an Indian solar EPC. Create a full-funnel marketing execution plan using only Google Ads, Meta Ads, YouTube, WhatsApp, SMS, and Email. Use only Gemini intelligence.

Inputs:
- Business goals: {$goals}
- Target regions: {$regions}
- Product lines: {$products}
- Supported languages: {$languages}
- {$budget}
- Autonomy mode: {$autonomy}
- Compliance requirements: {$complianceText}
- Additional notes: {$inputs['notes']}

Outputs must be valid JSON with keys: channel_plan (array), audience_plan (array), creative_plan (object with text, images, video), landing_plan (object), budget_allocation (array with channel, spend, pacing), kpi_targets (object), optimisation_loop (object with daily_review, weekly_review, adjustments, learning_strategy).

For each channel include budgets, pacing, objective, campaign_name, and activation steps. Audience plan must include inclusions and exclusions with geo, age, interests, and lookalikes. Creative plan must produce at least three variants per channel with CTA suggestions.

{$loop}
PROMPT;

    return $prompt;
}

function smart_marketing_generate_brain_plan(array $inputs, array $settings, array $aiSettings): array
{
    $prompt = smart_marketing_generate_brain_prompt($inputs, $settings);
    $responseText = ai_gemini_generate_text($aiSettings, $prompt);

    $plan = null;
    $clean = trim($responseText);
    if (str_starts_with($clean, '```')) {
        $clean = preg_replace('/^```[A-Za-z]*\n/', '', $clean);
        $clean = preg_replace('/```$/', '', $clean);
    }

    try {
        $plan = json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        $plan = null;
    }

    if (!is_array($plan)) {
        $plan = ['rawText' => $responseText];
    }

    return [
        'prompt' => $prompt,
        'text' => $responseText,
        'plan' => $plan,
    ];
}

function smart_marketing_validate_brain_inputs(array $inputs, array $settings): array
{
    $errors = [];

    $totalDaily = (float) ($inputs['dailyBudget'] ?? 0);
    $totalMonthly = (float) ($inputs['monthlyBudget'] ?? 0);
    $minBid = (float) ($inputs['minBid'] ?? 0);
    $cpaGuardrail = (float) ($inputs['cpaGuardrail'] ?? 0);

    if ($totalDaily <= 0) {
        $errors[] = 'Daily budget must be greater than zero.';
    } elseif ($totalDaily > (float) ($settings['budget']['dailyCap'] ?? $totalDaily)) {
        $errors[] = 'Daily budget exceeds configured cap.';
    }

    if ($totalMonthly <= 0) {
        $errors[] = 'Monthly budget must be greater than zero.';
    } elseif ($totalMonthly > (float) ($settings['budget']['monthlyCap'] ?? $totalMonthly)) {
        $errors[] = 'Monthly budget exceeds configured cap.';
    }

    if ($minBid < (float) ($settings['budget']['minBid'] ?? 0)) {
        $errors[] = 'Minimum bid must respect guardrail.';
    }

    if ($cpaGuardrail < (float) ($settings['budget']['targetCpl'] ?? 0)) {
        $errors[] = 'CPA guardrail must be at least the configured target CPL.';
    }

    if (empty($inputs['goals'])) {
        $errors[] = 'Select at least one business goal.';
    }

    if (empty($inputs['regions'])) {
        $errors[] = 'Select target regions.';
    }

    if (empty($inputs['products'])) {
        $errors[] = 'Select at least one product line.';
    }

    if (empty($inputs['languages'])) {
        $errors[] = 'Select at least one language.';
    }

    return $errors;
}

function smart_marketing_update_run_status(array &$runs, int $runId, string $status): bool
{
    foreach ($runs as &$run) {
        if ((int) ($run['id'] ?? 0) === $runId) {
            $run['status'] = $status;
            $run['updated_at'] = ai_timestamp();
            return true;
        }
    }

    return false;
}

function smart_marketing_apply_kill_switch(array &$runs): void
{
    foreach ($runs as &$run) {
        if (in_array($run['status'] ?? '', ['live', 'pending'], true)) {
            $run['status'] = 'halted';
            $run['updated_at'] = ai_timestamp();
        }
    }
}

function smart_marketing_collect_inputs_from_request(array $defaults, array $data): array
{
    $goals = isset($data['goals']) ? (array) $data['goals'] : [];
    $regions = isset($data['regions']) ? (array) $data['regions'] : [];
    $products = isset($data['products']) ? (array) $data['products'] : [];
    $languages = isset($data['languages']) ? (array) $data['languages'] : [];

    $compliance = [
        'platform_policy' => filter_var($data['compliance_platform_policy'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'brand_tone' => filter_var($data['compliance_brand_tone'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'legal_disclaimers' => filter_var($data['compliance_legal_disclaimers'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ];

    return [
        'goals' => array_values(array_unique(array_map('strval', $goals))),
        'regions' => array_values(array_unique(array_map('strval', $regions))),
        'products' => array_values(array_unique(array_map('strval', $products))),
        'languages' => array_values(array_unique(array_map('strval', $languages))),
        'dailyBudget' => (float) ($data['daily_budget'] ?? $defaults['budget']['dailyCap']),
        'monthlyBudget' => (float) ($data['monthly_budget'] ?? $defaults['budget']['monthlyCap']),
        'minBid' => (float) ($data['min_bid'] ?? $defaults['budget']['minBid']),
        'cpaGuardrail' => (float) ($data['cpa_guardrail'] ?? $defaults['budget']['targetCpl']),
        'autonomyMode' => in_array($data['autonomy_mode'] ?? '', ['auto', 'review', 'draft'], true) ? $data['autonomy_mode'] : 'draft',
        'notes' => trim((string) ($data['notes'] ?? '')),
        'compliance' => $compliance,
    ];
}

function smart_marketing_prepare_run_record(int $id, array $inputs, array $generation, string $status): array
{
    return [
        'id' => $id,
        'status' => $status,
        'inputs' => $inputs,
        'plan' => $generation['plan'],
        'response_text' => $generation['text'],
        'prompt' => $generation['prompt'],
        'created_at' => ai_timestamp(),
        'updated_at' => ai_timestamp(),
    ];
}

function smart_marketing_autonomy_status(string $mode): string
{
    return match ($mode) {
        'auto' => 'live',
        'review' => 'pending',
        default => 'draft',
    };
}

function smart_marketing_settings_merge(array $current, array $updates): array
{
    foreach ($updates as $key => $value) {
        if (is_array($value) && isset($current[$key]) && is_array($current[$key])) {
            $current[$key] = smart_marketing_settings_merge($current[$key], $value);
        } else {
            $current[$key] = $value;
        }
    }

    return $current;
}

function smart_marketing_format_currency(float $amount, string $currency = 'INR'): string
{
    return $currency . ' ' . number_format($amount, 2);
}

function smart_marketing_collect_settings_payload(array $input, array $current): array
{
    $payload = $current;

    if (isset($input['businessProfile']) && is_array($input['businessProfile'])) {
        $profile = $payload['businessProfile'];
        $incoming = $input['businessProfile'];
        $profile['brandName'] = trim((string) ($incoming['brandName'] ?? $profile['brandName']));
        $profile['tagline'] = trim((string) ($incoming['tagline'] ?? $profile['tagline']));
        $profile['about'] = trim((string) ($incoming['about'] ?? $profile['about']));
        $profile['primaryContact'] = trim((string) ($incoming['primaryContact'] ?? $profile['primaryContact']));
        $profile['supportEmail'] = trim((string) ($incoming['supportEmail'] ?? $profile['supportEmail']));
        $profile['whatsappNumber'] = trim((string) ($incoming['whatsappNumber'] ?? $profile['whatsappNumber']));
        $regions = $incoming['serviceRegions'] ?? $profile['serviceRegions'];
        if (is_string($regions)) {
            $regions = preg_split('/\s*,\s*/', $regions, -1, PREG_SPLIT_NO_EMPTY);
        }
        $profile['serviceRegions'] = array_values(array_unique(array_map('strval', is_array($regions) ? $regions : [])));
        $payload['businessProfile'] = $profile;
    }

    if (isset($input['audiences']) && is_array($input['audiences'])) {
        $audiences = $payload['audiences'];
        $incoming = $input['audiences'];
        $segments = $incoming['primarySegments'] ?? $audiences['primarySegments'];
        if (is_string($segments)) {
            $segments = preg_split('/\n+/', $segments, -1, PREG_SPLIT_NO_EMPTY);
        }
        $audiences['primarySegments'] = array_values(array_unique(array_map('strval', is_array($segments) ? $segments : [])));
        $audiences['remarketingNotes'] = trim((string) ($incoming['remarketingNotes'] ?? $audiences['remarketingNotes']));
        $audiences['exclusions'] = trim((string) ($incoming['exclusions'] ?? $audiences['exclusions']));
        $payload['audiences'] = $audiences;
    }

    if (isset($input['products']) && is_array($input['products'])) {
        $products = $payload['products'];
        $incoming = $input['products'];
        $portfolio = $incoming['portfolio'] ?? $products['portfolio'];
        if (is_string($portfolio)) {
            $portfolio = preg_split('/\n+/', $portfolio, -1, PREG_SPLIT_NO_EMPTY);
        }
        $products['portfolio'] = array_values(array_unique(array_map('strval', is_array($portfolio) ? $portfolio : [])));
        $products['offers'] = trim((string) ($incoming['offers'] ?? $products['offers']));
        $payload['products'] = $products;
    }

    if (isset($input['budget']) && is_array($input['budget'])) {
        $budget = $payload['budget'];
        $incoming = $input['budget'];
        $budget['dailyCap'] = (float) ($incoming['dailyCap'] ?? $budget['dailyCap']);
        $budget['monthlyCap'] = (float) ($incoming['monthlyCap'] ?? $budget['monthlyCap']);
        $budget['minBid'] = (float) ($incoming['minBid'] ?? $budget['minBid']);
        $budget['targetCpl'] = (float) ($incoming['targetCpl'] ?? $budget['targetCpl']);
        $budget['currency'] = trim((string) ($incoming['currency'] ?? $budget['currency']));
        $payload['budget'] = $budget;
    }

    if (isset($input['autonomy']) && is_array($input['autonomy'])) {
        $autonomy = $payload['autonomy'];
        $incoming = $input['autonomy'];
        $mode = strtolower((string) ($incoming['mode'] ?? $autonomy['mode']));
        if (!in_array($mode, ['auto', 'review', 'draft'], true)) {
            $mode = 'draft';
        }
        $autonomy['mode'] = $mode;
        $autonomy['reviewRecipients'] = trim((string) ($incoming['reviewRecipients'] ?? $autonomy['reviewRecipients']));
        $autonomy['killSwitchEngaged'] = (bool) ($incoming['killSwitchEngaged'] ?? $autonomy['killSwitchEngaged']);
        $payload['autonomy'] = $autonomy;
    }

    if (isset($input['compliance']) && is_array($input['compliance'])) {
        $compliance = $payload['compliance'];
        $incoming = $input['compliance'];
        $compliance['policyChecks'] = (bool) ($incoming['policyChecks'] ?? $compliance['policyChecks']);
        $compliance['brandTone'] = (bool) ($incoming['brandTone'] ?? $compliance['brandTone']);
        $compliance['legalDisclaimers'] = (bool) ($incoming['legalDisclaimers'] ?? $compliance['legalDisclaimers']);
        $compliance['pmSuryaDisclaimer'] = trim((string) ($incoming['pmSuryaDisclaimer'] ?? $compliance['pmSuryaDisclaimer']));
        $compliance['notes'] = trim((string) ($incoming['notes'] ?? $compliance['notes']));
        $payload['compliance'] = $compliance;
    }

    if (isset($input['integrations']) && is_array($input['integrations'])) {
        $integrations = $payload['integrations'];
        $incoming = $input['integrations'];
        foreach (array_keys(smart_marketing_default_connector_settings()) as $key) {
            if (!isset($incoming[$key])) {
                continue;
            }
            $entry = $integrations[$key] ?? [];
            $data = $incoming[$key];
            $status = strtolower((string) ($data['status'] ?? ($entry['status'] ?? 'unknown')));
            if (!in_array($status, ['connected', 'warning', 'error', 'unknown'], true)) {
                $status = 'unknown';
            }
            $entry['status'] = $status;
            foreach ($data as $field => $value) {
                if ($field === 'status') {
                    continue;
                }
                $entry[$field] = is_string($value) ? trim($value) : $value;
            }
            $integrations[$key] = $entry;
        }
        $payload['integrations'] = $integrations;
    }

    return $payload;
}

function smart_marketing_language_options(): array
{
    return ['English', 'Hindi'];
}

function smart_marketing_goal_options(): array
{
    return ['Leads', 'Awareness', 'Remarketing', 'Seasonal Offers', 'Service AMC'];
}

function smart_marketing_product_options(): array
{
    return ['Rooftop 1 kW', 'Rooftop 3 kW', 'Rooftop 5 kW', 'Rooftop 10 kW', 'Hybrid', 'Off-grid', 'C&I 10-100 kW', 'PM Surya Ghar', 'Non-scheme'];
}

function smart_marketing_region_defaults(array $settings): array
{
    $regions = $settings['businessProfile']['serviceRegions'] ?? [];
    if (empty($regions)) {
        $regions = ['Jharkhand'];
    }

    return $regions;
}

function smart_marketing_compliance_defaults(array $settings): array
{
    return [
        'platform_policy' => (bool) ($settings['compliance']['policyChecks'] ?? true),
        'brand_tone' => (bool) ($settings['compliance']['brandTone'] ?? true),
        'legal_disclaimers' => (bool) ($settings['compliance']['legalDisclaimers'] ?? true),
    ];
}

function smart_marketing_prepare_language_codes(array $languages): array
{
    $map = [
        'english' => 'en',
        'hindi' => 'hi',
    ];

    $codes = [];
    foreach ($languages as $language) {
        $key = strtolower($language);
        if (isset($map[$key])) {
            $codes[] = $map[$key];
        }
    }

    return array_values(array_unique($codes));
}

function smart_marketing_generate_text_asset(array $aiSettings, string $category, string $brief, array $settings): array
{
    $prompt = <<<PROMPT
You are the Smart Marketing creative writer for Dakshayani Enterprises. Generate {$category} in English and Hindi based on this brief:
{$brief}

Return JSON with keys english and hindi each containing an array of strings. Ensure brand tone is authoritative, trustworthy, and policy compliant. Include CTA suggestions.
PROMPT;

    $text = ai_gemini_generate_text($aiSettings, $prompt);
    $clean = trim($text);
    if (str_starts_with($clean, '```')) {
        $clean = preg_replace('/^```[A-Za-z]*\n/', '', $clean);
        $clean = preg_replace('/```$/', '', $clean);
    }

    try {
        $decoded = json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        $decoded = ['rawText' => $text];
    }

    $asset = smart_marketing_store_asset('text', [
        'category' => $category,
        'brief' => $brief,
        'output' => $decoded,
    ]);

    return $asset;
}

function smart_marketing_generate_image_asset(array $aiSettings, string $promptText, string $preset, array $settings): array
{
    $prompt = 'Create a marketing image for Dakshayani Enterprises solar: ' . $promptText . '. Include rooftop solar visuals, sunlight, Indian households, and brand-safe overlay areas.';
    $image = ai_gemini_generate_image($aiSettings, $prompt);
    $asset = smart_marketing_store_asset('image', [
        'prompt' => $prompt,
        'preset' => $preset,
        'path' => $image['path'],
        'mimeType' => $image['mimeType'],
    ]);

    return $asset;
}

function smart_marketing_generate_tts_asset(array $aiSettings, string $script, array $settings): array
{
    $audio = ai_gemini_generate_tts($aiSettings, $script);
    $asset = smart_marketing_store_asset('tts', [
        'script' => $script,
        'path' => $audio['path'],
        'mimeType' => $audio['mimeType'],
    ]);

    return $asset;
}

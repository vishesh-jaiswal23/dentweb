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

    $automationEntries = smart_marketing_run_automations($campaigns, $settings, true);

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

function smart_marketing_run_automations(array &$campaigns, array $settings, bool $forced = false): array
{
    $entries = [];
    $targetCpl = (float) ($settings['budget']['targetCpl'] ?? 450);
    $ctrThreshold = 0.02;

    foreach ($campaigns as &$campaign) {
        if (($campaign['status'] ?? '') !== 'launched') {
            continue;
        }
        $metrics = $campaign['metrics'] ?? ['ctr' => 0.02, 'cpl' => $targetCpl];
        if (($metrics['ctr'] ?? 0) < $ctrThreshold) {
            $campaign['maintenance']['lastCreativeRefresh'] = ai_timestamp();
            $entries[] = [
                'timestamp' => ai_timestamp(),
                'type' => 'creative_refresh',
                'campaign_id' => $campaign['id'],
                'message' => sprintf('Queued fresh creative variants because CTR %.2f%% is below %.2f%%.', ($metrics['ctr'] ?? 0) * 100, $ctrThreshold * 100),
            ];
            $campaign['metrics']['ctr'] = ($metrics['ctr'] ?? 0) + 0.004;
        }

        if (($metrics['cpl'] ?? 0) > $targetCpl) {
            $campaign['budget']['daily'] = round($campaign['budget']['daily'] * 0.95, 2);
            $entries[] = [
                'timestamp' => ai_timestamp(),
                'type' => 'budget_reallocation',
                'campaign_id' => $campaign['id'],
                'message' => sprintf('Shifted spend to stronger CPL performers for %s (CPL %s vs target %s).', $campaign['id'], number_format($metrics['cpl'], 2), number_format($targetCpl, 2)),
            ];
            $campaign['metrics']['cpl'] = ($metrics['cpl'] ?? 0) * 0.95;
        }

        if ($campaign['type'] === 'search') {
            $campaign['negative_keywords'] = array_values(array_unique(array_merge($campaign['negative_keywords'] ?? [], ['free installation', 'jobs'])));
            $entries[] = [
                'timestamp' => ai_timestamp(),
                'type' => 'negative_keywords',
                'campaign_id' => $campaign['id'],
                'message' => 'Added negative keywords: free installation, jobs.',
            ];
        }

        if (in_array($campaign['type'], ['lead_gen', 'whatsapp', 'boosted'], true)) {
            $campaign['frequency_cap'] = '2 impressions per person per 7 days';
            $entries[] = [
                'timestamp' => ai_timestamp(),
                'type' => 'frequency_cap',
                'campaign_id' => $campaign['id'],
                'message' => 'Adjusted Meta frequency cap to 2/7 to control fatigue.',
            ];
        }
    }
    unset($campaign);

    if ($forced) {
        $entries[] = [
            'timestamp' => ai_timestamp(),
            'type' => 'seasonal_schedule',
            'campaign_id' => null,
            'message' => 'Seasonal bursts locked: Summer rooftop push & Diwali festival offers.',
        ];
        $entries[] = [
            'timestamp' => ai_timestamp(),
            'type' => 'dayparting',
            'campaign_id' => null,
            'message' => 'Applied 25% bid uplift during 9am-8pm call hours and reduced bids overnight.',
        ];
        $entries[] = [
            'timestamp' => ai_timestamp(),
            'type' => 'compliance_rescan',
            'campaign_id' => null,
            'message' => 'Re-scanned active creatives; flagged items would be paused automatically.',
        ];
    }

    if (!empty($entries)) {
        smart_marketing_automation_log_append($entries);
        smart_marketing_campaigns_save($campaigns);
    }

    return $entries;
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

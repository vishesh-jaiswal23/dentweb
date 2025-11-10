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
        'integrations' => [
            'googleAds' => ['status' => 'unknown', 'account' => ''],
            'meta' => ['status' => 'unknown', 'account' => ''],
            'email' => ['status' => 'unknown', 'provider' => ''],
            'whatsapp' => ['status' => 'unknown', 'number' => ''],
        ],
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
    $result = [];
    foreach (['googleAds', 'meta', 'email', 'whatsapp'] as $key) {
        $entry = $integrations[$key] ?? [];
        $status = strtolower((string) ($entry['status'] ?? 'unknown'));
        if (!in_array($status, ['connected', 'warning', 'error', 'unknown'], true)) {
            $status = 'unknown';
        }

        $result[$key] = [
            'status' => $status,
            'label' => smart_marketing_integration_label($key),
            'details' => $entry,
        ];
    }

    return $result;
}

function smart_marketing_integration_label(string $key): string
{
    return match ($key) {
        'googleAds' => 'Google Ads',
        'meta' => 'Meta',
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        default => ucfirst($key),
    };
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
        foreach (['googleAds', 'meta', 'email', 'whatsapp'] as $key) {
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

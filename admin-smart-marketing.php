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
                smart_marketing_settings_save($merged);
                smart_marketing_audit_log_append('settings.updated', ['keys' => array_keys($updates)], $admin);
                $marketingSettings = smart_marketing_settings_load();
                $response = [
                    'ok' => true,
                    'settings' => $marketingSettings,
                    'aiHealth' => smart_marketing_ai_health($aiSettings),
                    'integrations' => smart_marketing_integrations_health($marketingSettings),
                    'audit' => smart_marketing_audit_log_read(),
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

                $response = [
                    'ok' => true,
                    'run' => $record,
                    'runs' => array_reverse($runs),
                    'audit' => smart_marketing_audit_log_read(),
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

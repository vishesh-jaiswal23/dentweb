<?php
require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/auth.php';
require_once __DIR__ . '/../common/security.php';
require_once __DIR__ . '/../common/settings.php';
require_once __DIR__ . '/layout.php';

portal_require_role(['admin']);
portal_require_session();

$user = portal_current_user();
$aiSettings = portal_ai_settings_get();
$maskedKey = portal_mask_secret($aiSettings['api_key'] ?? '');
$toasts = [];
$testResults = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        portal_verify_csrf($_POST['csrf_token'] ?? '');
        $intent = $_POST['intent'] ?? 'save';

        if ($intent === 'reset') {
            portal_require_capability('settings.update', $user);
            portal_ai_settings_reset();
            $aiSettings = portal_ai_settings_get();
            $maskedKey = portal_mask_secret($aiSettings['api_key'] ?? '');
            $toasts[] = ['type' => 'info', 'message' => 'AI settings restored to defaults.'];
        } elseif ($intent === 'test') {
            portal_require_capability('ai.test', $user);
            $candidate = $aiSettings;
            $candidate['api_key'] = trim((string) ($_POST['api_key'] ?? $candidate['api_key'] ?? '')) ?: ($candidate['api_key'] ?? '');
            $candidate['models'] = [
                'text' => trim((string) ($_POST['model_text'] ?? ($candidate['models']['text'] ?? ''))),
                'image' => trim((string) ($_POST['model_image'] ?? ($candidate['models']['image'] ?? ''))),
                'tts' => trim((string) ($_POST['model_tts'] ?? ($candidate['models']['tts'] ?? ''))),
            ];
            $testResults = portal_ai_settings_test($candidate);
            $toasts[] = ['type' => 'success', 'message' => 'Test command executed. Review the status table below.'];
        } else {
            portal_require_capability('settings.update', $user);
            $payload = [
                'api_key' => $_POST['api_key'] ?? '',
                'text' => $_POST['model_text'] ?? '',
                'image' => $_POST['model_image'] ?? '',
                'tts' => $_POST['model_tts'] ?? '',
            ];
            portal_ai_settings_set($payload);
            $aiSettings = portal_ai_settings_get();
            $maskedKey = portal_mask_secret($aiSettings['api_key'] ?? '');
            $toasts[] = ['type' => 'success', 'message' => 'AI settings saved successfully.'];
        }
    } catch (Throwable $th) {
        $toasts[] = ['type' => 'error', 'message' => $th->getMessage()];
    }
}

if ($testResults === null) {
    $testResults = portal_ai_settings_test($aiSettings);
}

$usageMatrix = [
    ['feature' => 'Content/blog generation', 'model' => $aiSettings['models']['text'] ?? '', 'type' => 'Text'],
    ['feature' => 'Cover & creative images', 'model' => $aiSettings['models']['image'] ?? '', 'type' => 'Image'],
    ['feature' => 'Audio narration & TTS', 'model' => $aiSettings['models']['tts'] ?? '', 'type' => 'TTS'],
];

portal_admin_shell_open('AI Settings | Dakshayani Enterprises', 'ai-settings', $user, []);
?>
          <section class="admin-section">
            <header class="admin-section__header">
              <div>
                <h1>Gemini provider</h1>
                <p class="text-muted">Manage credentials and ensure downstream modules consume the right models.</p>
              </div>
              <div class="admin-section__actions">
                <form method="post" class="inline-form">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(portal_csrf_token()); ?>" />
                  <input type="hidden" name="intent" value="reset" />
                  <button class="btn btn-secondary" type="submit"><i class="fa-solid fa-rotate"></i> Reset to defaults</button>
                </form>
              </div>
            </header>
            <form method="post" class="admin-form" data-capability="settings.update">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(portal_csrf_token()); ?>" />
              <input type="hidden" name="intent" value="save" />
              <fieldset>
                <legend>API credentials</legend>
                <label>
                  <span>API key <small>(current: <?php echo htmlspecialchars($maskedKey); ?>)</small></span>
                  <input type="password" name="api_key" placeholder="Enter new API key" autocomplete="off" />
                  <span class="form-help">Stored securely, never logged. Leave blank to retain existing key.</span>
                </label>
              </fieldset>
              <fieldset>
                <legend>Model mapping</legend>
                <div class="admin-grid admin-grid--form">
                  <label>
                    <span>Text model</span>
                    <input type="text" name="model_text" value="<?php echo htmlspecialchars($aiSettings['models']['text'] ?? ''); ?>" />
                  </label>
                  <label>
                    <span>Image model</span>
                    <input type="text" name="model_image" value="<?php echo htmlspecialchars($aiSettings['models']['image'] ?? ''); ?>" />
                  </label>
                  <label>
                    <span>TTS model</span>
                    <input type="text" name="model_tts" value="<?php echo htmlspecialchars($aiSettings['models']['tts'] ?? ''); ?>" />
                  </label>
                </div>
              </fieldset>
              <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save settings</button>
                <button type="submit" name="intent" value="test" class="btn btn-secondary"><i class="fa-solid fa-plug"></i> Test connection</button>
              </div>
            </form>
          </section>

          <section class="admin-section">
            <header class="admin-section__header">
              <div>
                <h2>Connection status</h2>
                <p class="text-muted">Results are not logged and secrets remain masked.</p>
              </div>
            </header>
            <div class="admin-table-wrapper">
              <table class="admin-table admin-table--compact">
                <thead>
                  <tr>
                    <th scope="col">Type</th>
                    <th scope="col">Model</th>
                    <th scope="col">Status</th>
                    <th scope="col">Message</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($testResults as $type => $result): ?>
                    <tr>
                      <td><?php echo strtoupper(htmlspecialchars($type)); ?></td>
                      <td><?php echo htmlspecialchars($result['model']); ?></td>
                      <td>
                        <span class="badge badge-<?php echo $result['status'] === 'pass' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars(strtoupper($result['status'])); ?></span>
                      </td>
                      <td><?php echo htmlspecialchars($result['message']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>

          <section class="admin-section">
            <header class="admin-section__header">
              <div>
                <h2>Global usage</h2>
                <p class="text-muted">Downstream modules automatically consume the configured models.</p>
              </div>
            </header>
            <div class="admin-table-wrapper">
              <table class="admin-table admin-table--compact">
                <thead>
                  <tr>
                    <th scope="col">Feature</th>
                    <th scope="col">Model</th>
                    <th scope="col">Type</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($usageMatrix as $row): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($row['feature']); ?></td>
                      <td><?php echo htmlspecialchars($row['model']); ?></td>
                      <td><?php echo htmlspecialchars($row['type']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>

          <script>
            window.__ADMIN_TOASTS = <?php echo json_encode($toasts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
          </script>
<?php portal_admin_shell_close();

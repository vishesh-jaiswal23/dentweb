        </main>
      </div>
    </div>
    <div class="admin-toast-container" aria-live="polite"></div>
    <div class="admin-modal" data-component="confirmation-modal" hidden>
      <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="admin-modal-title">
        <div class="admin-modal__content">
          <h2 id="admin-modal-title">Confirm action</h2>
          <p class="admin-modal__body"></p>
        </div>
        <div class="admin-modal__footer">
          <button type="button" class="btn btn-secondary" data-action="modal-cancel">Cancel</button>
          <button type="button" class="btn btn-primary" data-action="modal-confirm">Confirm</button>
        </div>
      </div>
    </div>
    <?php if (!empty($extraScripts)): ?>
      <?php foreach ($extraScripts as $scriptSrc): ?>
        <script src="<?php echo htmlspecialchars($scriptSrc); ?>" defer></script>
      <?php endforeach; ?>
    <?php endif; ?>
    <script src="<?php echo htmlspecialchars(portal_url('users/admin/admin.js')); ?>" defer></script>
  </body>
</html>

<div class="notification-panel" hidden>
  <div class="notification-panel__header">
    <h2>Notifications</h2>
    <button type="button" class="btn btn-link" data-action="clear-notifications">Clear all</button>
  </div>
  <ul class="notification-panel__list">
    <?php if (empty($notifications)): ?>
      <li class="notification-panel__empty">You're all caught up.</li>
    <?php else: ?>
      <?php foreach ($notifications as $note): ?>
        <li class="notification-panel__item <?php echo htmlspecialchars($note['tone'] ?? 'info'); ?>">
          <div class="notification-panel__icon"><i class="fa-solid <?php echo htmlspecialchars($note['icon'] ?? 'fa-circle-info'); ?>"></i></div>
          <div class="notification-panel__body">
            <p><?php echo htmlspecialchars($note['message']); ?></p>
            <?php if (!empty($note['time'])): ?>
              <span class="notification-panel__time"><?php echo htmlspecialchars($note['time']); ?></span>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    <?php endif; ?>
  </ul>
</div>

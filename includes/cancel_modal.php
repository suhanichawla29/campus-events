<div class="modal-overlay" id="cancel-modal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cancel-modal-title" aria-describedby="cancel-modal-desc">
        <div class="modal-header">
            <h2 id="cancel-modal-title">Cancel Registration?</h2>
            <button type="button" class="modal-close" id="cancel-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body" id="cancel-modal-desc">
            <p>Are you sure you want to cancel your registration for &ldquo;<span id="cancel-event-title"></span>&rdquo;?</p>
            <p class="modal-event-date">Event date: <span id="cancel-event-date"></span></p>
            <p>Your reserved seat will become available to another student.</p>
        </div>
        <div class="modal-footer">
            <form method="POST" action="dashboard.php" id="cancel-form">
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" id="cancel-event-id" value="">
                <button type="button" class="btn btn-secondary" id="cancel-keep">Keep Registration</button>
                <button type="submit" name="cancel_registration" value="1" class="btn btn-danger">Yes, Cancel Registration</button>
            </form>
        </div>
    </div>
</div>

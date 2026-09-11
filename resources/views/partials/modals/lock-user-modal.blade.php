<div class="modal fade" id="lockUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lockUserModalTitle">{{ ui('confirm_lock') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="lockUserModalBody">{{ ui('confirm_lock_body') }}</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ ui('cancel') }}</button>
                <form id="lockUserModalForm" method="POST">@csrf
                    <button type="submit" class="btn btn-danger" id="lockUserModalSubmit">{{ ui('lock') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>

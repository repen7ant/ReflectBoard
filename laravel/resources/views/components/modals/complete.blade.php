<template x-if="completeModal.open">
    <div class="modal-overlay" @click.self="completeModal.open = false">
        <div class="modal">
            <div class="modal-title">Complete Task</div>
            <div class="modal-activity-title modal-activity-title-spaced" x-text="completeModal.activity?.title"></div>

            <div class="field">
                <label>Time spent in mins (optional)</label>
                <input type="number" x-model="completeModal.time_spent" placeholder="e.g. 45" min="0">
            </div>

            <div class="field">
                <label>Reflection (optional)</label>
                <textarea
                    x-model="completeModal.reflection"
                    rows="4"
                    placeholder="What are your thoughts on this task? Any challenges?"
                ></textarea>
            </div>

            <div class="modal-actions modal-actions-spaced">
                <button class="btn btn-ghost" @click="completeModal.open = false">Cancel</button>
                <button
                    class="btn btn-primary btn-success"
                    @click="completeActivity()"
                >Complete</button>
            </div>
        </div>
    </div>
</template>

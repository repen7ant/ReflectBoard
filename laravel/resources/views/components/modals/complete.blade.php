<template x-if="completeModal.open">
    <div class="modal-overlay" @mousedown.self="$el._md=true" @click.self="$el._md&&(completeModal.open=false);$el._md=false">
        <div class="modal">
            <button class="modal-close" @click="completeModal.open = false">&times;</button>
            <div class="modal-title">Complete Task</div>
            <div class="modal-activity-title modal-activity-title-spaced" x-text="completeModal.activity?.title"></div>

            <div class="field">
                <label>Time spent (minutes)</label>
                <input type="number" x-model="completeModal.time_spent" placeholder="e.g. 45" min="0">
            </div>

            <div class="field">
                <label>Reflection</label>
                <textarea
                    x-model="completeModal.reflection"
                    rows="4"
                    placeholder="What are your thoughts on this task? Any challenges?"
                ></textarea>
            </div>

            <div class="modal-actions">
                <button
                    class="btn btn-primary btn-success"
                    @click="completeActivity()"
                >Complete</button>
            </div>
        </div>
    </div>
</template>

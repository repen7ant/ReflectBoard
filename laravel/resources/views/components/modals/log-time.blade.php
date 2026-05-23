<template x-if="logTimeModal.open">
    <div class="modal-overlay" @mousedown.self="$el._md=true" @click.self="$el._md&&(logTimeModal.open=false);$el._md=false">
        <div class="modal">
            <button class="modal-close" @click="logTimeModal.open = false">&times;</button>
            <div class="modal-title">Log Time</div>
            <div class="modal-activity-title modal-activity-title-spaced" x-text="logTimeModal.activity?.title"></div>

            <div class="field">
                <label>Minutes to add</label>
                <input
                    type="number"
                    x-model="logTimeModal.minutes"
                    placeholder="e.g. 45"
                    min="1"
                    @keydown.enter="logTime()"
                >
            </div>

            <div class="modal-actions">
                <button
                    class="btn btn-primary"
                    @click="logTime()"
                    :disabled="!logTimeModal.minutes || parseInt(logTimeModal.minutes) < 1"
                >Add</button>
            </div>
        </div>
    </div>
</template>

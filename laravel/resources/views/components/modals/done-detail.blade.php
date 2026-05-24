<template x-if="detailModal.open">
    <div class="modal-overlay" @mousedown.self="$el._md=true" @click.self="$el._md&&(detailModal.open=false);$el._md=false">
        <div class="modal">
            <button class="modal-close" @click="detailModal.open = false">&times;</button>
            <div class="modal-header">
                <div style="flex: 1;">
                    <div class="modal-activity-title" x-text="detailModal.activity?.title"></div>
                    <template x-if="detailModal.activity?.category || detailModal.activity?.category_snapshot_name">
                        <div class="card-category" style="margin-top: 0.5rem;">
                            <div class="category-dot" :style="'background:' + (detailModal.activity.category?.color ?? detailModal.activity.category_snapshot_color)"></div>
                            <span x-text="detailModal.activity.category?.name ?? detailModal.activity.category_snapshot_name"></span>
                        </div>
                    </template>
                </div>
            </div>

            <template x-if="!detailModal.activity?.is_quick_capture">
                <div class="detail-row">
                    <span class="detail-label">Created</span>
                    <span class="detail-value" x-text="formatDate(detailModal.activity?.created_at, { withTime: true })"></span>
                </div>
            </template>

            <div class="detail-row">
                <span class="detail-label">Completed</span>
                <span class="detail-value" x-text="formatDate(detailModal.activity?.completed_at, { withTime: true })"></span>
            </div>

            <template x-if="detailModal.activity?.time_spent_minutes">
                <div class="detail-row">
                    <span class="detail-label">Time Spent</span>
                    <span class="detail-value" x-text="formatMinutes(detailModal.activity.time_spent_minutes)"></span>
                </div>
            </template>

            <template x-if="detailModal.activity?.description">
                <div style="margin-top: 1rem;">
                    <div class="detail-label" style="margin-bottom: 0.5rem;">Description</div>
                    <div class="description-block" x-text="detailModal.activity.description"></div>
                </div>
            </template>

            <template x-if="detailModal.activity?.reflection_text">
                <div style="margin-top: 1rem;">
                    <div class="detail-label" style="margin-bottom: 0.5rem;">Reflection</div>
                    <div class="description-block" x-text="detailModal.activity.reflection_text"></div>
                </div>
            </template>

            <!-- Subtasks list for projects -->
            <template x-if="detailModal.activity?.is_project">
                <div style="margin-top: 1rem;">
                    <div class="detail-label" style="margin-bottom: 0.5rem;">Subtasks</div>
                    <template x-if="detailModal.loadingSubtasks">
                        <div class="loading-center" style="padding: 1rem;"><div class="spinner"></div></div>
                    </template>
                    <template x-if="!detailModal.loadingSubtasks && detailModal.subtasks.length === 0">
                        <div style="color: var(--text-muted); font-size: 0.875rem; padding: 0.5rem;">No subtasks</div>
                    </template>
                    <template x-if="!detailModal.loadingSubtasks && detailModal.subtasks.length > 0">
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <template x-for="subtask in detailModal.subtasks" :key="subtask.id">
                                <div style="padding: 0.75rem; background: var(--surface-2); border: 1px solid var(--border); border-radius: 0.375rem;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span style="color: var(--text-muted);">✓</span>
                                        <span x-text="subtask.title"></span>
                                    </div>
                                    <template x-if="subtask.completed_at">
                                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; margin-left: 1.5rem;" x-text="'Completed: ' + formatDate(subtask.completed_at)"></div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            <div class="modal-actions modal-actions-spaced">
                <button class="btn btn-ghost" @click="returnToBoard(detailModal.activity)">↩ Return to Board</button>
                <button class="btn btn-danger" @click="deleteActivity(detailModal.activity)">Delete</button>
            </div>
        </div>
    </div>
</template>

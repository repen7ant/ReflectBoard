<template x-if="detailModal.open">
    <div class="modal-overlay" @click.self="detailModal.open = false">
        <div class="modal">
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

            <div class="detail-row">
                <span class="detail-label">Completed</span>
                <span class="detail-value" x-text="formatDate(detailModal.activity?.completed_at, true)"></span>
            </div>

            <template x-if="detailModal.activity?.time_spent_minutes">
                <div class="detail-row">
                    <span class="detail-label">Time Spent</span>
                    <span class="detail-value" x-text="formatTime(detailModal.activity.time_spent_minutes)"></span>
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

            <div class="modal-actions modal-actions-spaced">
                <button class="btn btn-danger" @click="deleteActivity(detailModal.activity)">Delete</button>
                <button class="btn btn-ghost" @click="detailModal.open = false">Close</button>
            </div>
        </div>
    </div>
</template>

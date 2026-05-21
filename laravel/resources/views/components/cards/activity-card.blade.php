<div
    x-data="{ activity }"
    class="card"
    :class="{ 'card-project': activity.is_project }"
    :data-id="activity.id"
    @click="activity.is_project ? openProjectModal(activity) : openEditModal(activity)"
>
    <button
        class="complete-circle"
        title="Complete"
        @click.stop="openCompleteModal(activity)"
    ></button>

    <!-- Parent project name for subtasks -->
    <template x-if="activity.parent_id && activity.parent_title">
        <div class="card-parent-badge">↳ <span x-text="activity.parent_title"></span></div>
    </template>

    <div class="card-title" x-text="activity.title"></div>

    <template x-if="activity.category">
        <div class="card-category">
            <div class="category-dot--sm" :style="'background:' + activity.category.color"></div>
            <span x-text="activity.category.name"></span>
        </div>
    </template>

    <div x-show="activity.tags && activity.tags.length > 0" class="tags-container">
        <template x-for="tag in (activity.tags || [])" :key="tag">
            <span class="tag-badge">#<span x-text="tag"></span></span>
        </template>
    </div>

    <template x-if="activity.deadline">
        <div
            class="card-deadline"
            :class="isOverdue(activity.deadline) ? 'overdue' : ''"
            x-text="formatDate(activity.deadline, false)"
        ></div>
    </template>

    <!-- Progress bar for project -->
    <template x-if="activity.is_project && activity.subtasks_total > 0">
        <div class="card-progress">
            <div class="card-progress-label">
                <span x-text="activity.subtasks_done"></span>/<span x-text="activity.subtasks_total"></span> done
            </div>
            <div class="card-progress-bar">
                <div
                    class="card-progress-fill"
                    :class="activity.subtasks_done === activity.subtasks_total ? 'done' : ''"
                    :style="'width:' + Math.round((activity.subtasks_done / activity.subtasks_total) * 100) + '%'"
                ></div>
            </div>
        </div>
    </template>
</div>

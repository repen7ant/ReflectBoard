<div
    class="card"
    :class="{ 'card-project': activity.is_project, [deadlineStatus(activity.deadline)]: activity.deadline }"
    :data-id="activity.id"
    @click="activity.is_project ? openProjectModal(activity) : openEditModal(activity)"
    @contextmenu.prevent="openContextMenu($event, activity)"
>
    <button
        class="complete-circle"
        title="Complete"
        @click.stop="openCompleteModal(activity)"
    ></button>
    <template x-if="!activity.is_project">
        <button
            class="log-time-btn"
            title="Log time"
            @click.stop="openLogTimeModal(activity)"
        >
            <svg class="log-time-icon" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M8 2.5v11M2.5 8h11" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
        </button>
    </template>

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
            :class="deadlineStatus(activity.deadline)"
            x-text="formatDate(activity.deadline, { showYear: false })"
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

    <template x-if="activity.time_spent_minutes">
        <div class="card-time-badge" x-text="formatMinutes(activity.time_spent_minutes)"></div>
    </template>
</div>

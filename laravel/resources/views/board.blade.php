<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="api-token" content="{{ auth()->check() ? auth()->user()->api_token : '' }}">
    <title>Board — ReflectBoard</title>

    <link rel="icon" href="/icon.svg" type="image/svg+xml">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="board()" x-init="init()">

    <!-- Topbar -->
    <nav class="topbar">
        <div class="topbar-logo">
            <a href="/">
                <img src="/icon.svg" alt="ReflectBoard" class="logo-icon">
            </a>
        </div>
        <div class="nav-links">
            <a href="/board" class="nav-link active">Board</a>
            <a href="/done" class="nav-link">Done</a>
            <a href="/analytics" class="nav-link">Analytics</a>
        </div>
    </nav>

    <!-- Board -->
    <div class="board-wrapper">
        <div class="board-inner">
            <template x-for="col in columns" :key="col.status">
                <div class="column">
                    <div class="column-header">
                        <span class="column-label" x-text="col.label"></span>
                        <span class="column-count" x-text="activities[col.status]?.length ?? 0"></span>
                    </div>
                    <div
                        class="column-body"
                        :id="'col-' + col.status"
                        :data-status="col.status"
                    >
                        <template x-if="loading">
                            <div class="loading-center">
                                <div class="spinner"></div>
                            </div>
                        </template>

                        <!-- Card: normal task or project -->
                        <template x-if="!loading">
                            <template x-for="activity in activities[col.status] ?? []" :key="activity.id">
                                <div
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
                            </template>
                        </template>
                    </div>

                    <!-- Add button -->
                    <template x-if="col.status !== 'on_reflection' && !loading">
                        <div class="column-footer">
                            <button class="add-btn" @click.stop="openCreateModal(col.status)">+</button>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <!-- Modals -->
    @include('components.modals.create-activity')

    @include('components.modals.edit-activity')

    @include('components.modals.project')

    @include('components.modals.complete')

    @include('components.modals.create-category')

    <!-- Toast -->
    <template x-if="toast.show">
        <div class="toast" x-text="toast.message"></div>
    </template>

<script>
    window.API_BASE = '{{ config("services.api_base.url") }}';
</script>

@include('components.fab')
</body>
</html>

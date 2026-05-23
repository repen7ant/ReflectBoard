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
                                @include('components.cards.activity-card')
                            </template>
                        </template>
                    </div>

                    <!-- Add button -->
                    <template x-if="!loading">
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

    @include('components.modals.log-time')

    @include('components.modals.create-category')

    <!-- Context menu -->
    <template x-if="contextMenu.open">
        <div
            class="context-menu"
            :style="`left:${contextMenu.x}px;top:${contextMenu.y}px`"
            @click.outside="closeContextMenu()"
            @keydown.escape.window="closeContextMenu()"
        >
            <template x-if="contextMenu.activity?.parent_id">
                <button class="context-menu-item" @click="contextMenuMoveToProject()">Move to project</button>
            </template>
            <button class="context-menu-item context-menu-item--danger" @click="contextMenuDelete()">Delete</button>
        </div>
    </template>

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

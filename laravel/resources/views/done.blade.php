<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="api-token" content="{{ auth()->check() ? auth()->user()->api_token : '' }}">
    <title>Done — ReflectBoard</title>
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="donePage()" x-init="init()">

    <!-- Topbar -->
    <nav class="topbar">
        <div class="topbar-logo">
            <a href="/">
                <img src="/icon.svg" alt="ReflectBoard" class="logo-icon">
            </a>
        </div>
        <div class="nav-links">
            <a href="/board" class="nav-link">Board</a>
            <a href="/done" class="nav-link active">Done</a>
            <a href="/analytics" class="nav-link">Analytics</a>
        </div>
    </nav>

    <!-- Done Page -->
    <div class="done-wrapper">

        <!-- Filters Panel -->
        <div class="filters-panel">
            <!-- Row 1: content filters -->
            <div class="filters-grid">
                <!-- Search -->
                <div class="filter-group">
                    <label class="filter-label">Search</label>
                    <input
                        type="text"
                        class="filter-input"
                        placeholder="Search by title, reflection or #tag..."
                        x-model="filters.search"
                        @input.debounce.300ms="applyFilters()"
                    >
                </div>

                <!-- Category -->
                <div class="filter-group">
                    <label class="filter-label">Category</label>
                    <select class="filter-input" x-model="filters.category_id" @change="applyFilters()">
                        <option value="">All categories</option>
                        <template x-for="cat in categories" :key="cat.id">
                            <option :value="cat.id" x-text="cat.name"></option>
                        </template>
                    </select>
                </div>

                <!-- Productivity -->
                <div class="filter-group">
                    <label class="filter-label">Productivity</label>
                    <select class="filter-input" x-model="filters.productivity" @change="applyProductivityFilter()">
                        <option value="">All</option>
                        <option value="productive">Productive</option>
                        <option value="unproductive">Unproductive</option>
                    </select>
                </div>
            </div>

            <!-- Row 2: date filters -->
            <div class="date-row">
                <div class="date-presets">
                    <button class="preset-btn" :class="{ 'active': activePreset === 'today' }"  @click="setDatePreset('today')">Today</button>
                    <button class="preset-btn" :class="{ 'active': activePreset === '7days' }"  @click="setDatePreset('7days')">7 Days</button>
                    <button class="preset-btn" :class="{ 'active': activePreset === '30days' }" @click="setDatePreset('30days')">30 Days</button>
                    <button class="preset-btn" :class="{ 'active': activePreset === 'all' }"    @click="setDatePreset('all')">All Time</button>
                </div>
                <div class="date-inputs">
                    <div class="filter-group">
                        <label class="filter-label">From</label>
                        <input type="date" class="filter-input" x-model="filters.date_from" @change="applyFilters()">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">To</label>
                        <input type="date" class="filter-input" x-model="filters.date_to" @change="applyFilters()">
                    </div>
                </div>
            </div>

            <!-- Active Filters -->
            <div x-show="hasActiveFilters()" class="active-filters" style="margin-top: 1rem;">
                <template x-if="filters.search">
                    <div class="filter-badge">
                        <span>Search: <span x-text="filters.search"></span></span>
                        <span class="filter-badge-remove" @click="filters.search = ''; applyFilters()">×</span>
                    </div>
                </template>
                <template x-if="filters.category_id">
                    <div class="filter-badge">
                        <span>Category: <span x-text="getCategoryName(filters.category_id)"></span></span>
                        <span class="filter-badge-remove" @click="filters.category_id = ''; applyFilters()">×</span>
                    </div>
                </template>
                <template x-if="filters.date_from || filters.date_to">
                    <div class="filter-badge">
                        <span>Date: <span x-text="formatDateRange()"></span></span>
                        <span class="filter-badge-remove" @click="clearDateFilter()">×</span>
                    </div>
                </template>
                <template x-if="filters.productivity">
                    <div class="filter-badge">
                        <span x-text="filters.productivity === 'productive' ? 'Productive only' : 'Unproductive only'"></span>
                        <span class="filter-badge-remove" @click="filters.productivity = ''; applyProductivityFilter()">×</span>
                    </div>
                </template>
                <button class="clear-all-btn" @click="clearAllFilters()">Clear All</button>
            </div>
        </div>

        <!-- Activities List -->
        <template x-if="loading">
            <div class="loading-center"><div class="spinner"></div></div>
        </template>

        <template x-if="!loading && activities.length === 0">
            <div class="empty-state">
                <div class="empty-state-icon">✓</div>
                <div class="empty-state-text">No completed activities found</div>
            </div>
        </template>

        <template x-if="!loading && activities.length > 0">
            <div class="activities-list">
                <template x-for="activity in activities" :key="activity.id">
                    <div class="activity-item" :class="{ 'activity-project': activity.is_project }" @click="openDetailModal(activity)" @contextmenu.prevent="openContextMenu($event, activity)">
                        <div class="activity-header">
                            <div style="flex: 1;">
                                <!-- Parent project badge for subtasks -->
                                <template x-if="activity.parent_id && activity.parent_title">
                                    <div class="card-parent-badge" style="margin-bottom: 0.5rem;">↳ <span x-text="activity.parent_title"></span></div>
                                </template>

                                <div class="activity-title-large" x-text="activity.title"></div>
                                <template x-if="activity.category || activity.category_snapshot_name">
                                    <div class="card-category" style="margin-top: 0.5rem;">
                                        <div class="category-dot" :style="'background:' + (activity.category?.color ?? activity.category_snapshot_color)"></div>
                                        <span x-text="activity.category?.name ?? activity.category_snapshot_name"></span>
                                    </div>
                                </template>
                                <div x-show="activity.tags && activity.tags.length > 0" class="tags-container">
                                    <template x-for="tag in (activity.tags || [])" :key="tag">
                                        <span class="tag-badge">#<span x-text="tag"></span></span>
                                    </template>
                                </div>

                                <!-- Subtasks counter for projects -->
                                <template x-if="activity.is_project && activity.subtasks_total > 0">
                                    <div style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--text-muted);" x-text="pluralizeSubtasks(activity.subtasks_total)"></div>
                                </template>
                            </div>
                            <div class="activity-meta">
                                <div class="activity-date" x-text="formatDate(activity.completed_at)"></div>
                                <template x-if="activity.time_spent_minutes">
                                    <div class="activity-time" x-text="formatTime(activity.time_spent_minutes)"></div>
                                </template>
                                <template x-if="activity.deadline">
                                    <div class="activity-deadline" x-text="'Due: ' + formatDate(activity.deadline)"></div>
                                </template>
                                <template x-if="activity.is_productive === false">
                                    <div class="unproductive-badge">unproductive</div>
                                </template>
                            </div>
                        </div>
                        <template x-if="activity.reflection_text">
                            <div class="activity-preview" x-text="activity.reflection_text"></div>
                        </template>
                    </div>
                </template>
            </div>
        </template>

    </div>

    <!-- Context menu -->
    <template x-if="contextMenu.open">
        <div
            class="context-menu"
            :style="`left:${contextMenu.x}px;top:${contextMenu.y}px`"
            @click.outside="closeContextMenu()"
            @keydown.escape.window="closeContextMenu()"
        >
            <button class="context-menu-item context-menu-item--danger" @click="contextMenuDelete()">Delete</button>
        </div>
    </template>

    <!-- Modals -->
    @include('components.modals.done-detail')

    <script>
        window.API_BASE = '{{ config("services.api_base.url") }}';
    </script>

    @include('components.fab')
</body>
</html>

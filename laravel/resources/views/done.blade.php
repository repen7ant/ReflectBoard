<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="api-token" content="{{ auth()->check() ? auth()->user()->api_token : '' }}">
    <title>Done — ReflectBoard</title>

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

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

                <!-- Date Range -->
                <div style="display: flex; gap: 0.5rem;">
                    <div class="filter-group" style="flex: 1; min-width: 0;">
                        <label class="filter-label">From</label>
                        <input
                            type="date"
                            class="filter-input"
                            x-model="filters.date_from"
                            @change="applyFilters()"
                            style="width: 100%; box-sizing: border-box;"
                        >
                    </div>
                    <div class="filter-group" style="flex: 1; min-width: 0;">
                        <label class="filter-label">To</label>
                        <input
                            type="date"
                            class="filter-input"
                            x-model="filters.date_to"
                            @change="applyFilters()"
                            style="width: 100%; box-sizing: border-box;"
                        >
                    </div>
                </div>
            </div>

            <!-- Date Presets -->
            <div class="date-presets">
                <button
                    class="preset-btn"
                    :class="{ 'active': activePreset === 'today' }"
                    @click="setDatePreset('today')"
                >Today</button>
                <button
                    class="preset-btn"
                    :class="{ 'active': activePreset === '7days' }"
                    @click="setDatePreset('7days')"
                >7 Days</button>
                <button
                    class="preset-btn"
                    :class="{ 'active': activePreset === '30days' }"
                    @click="setDatePreset('30days')"
                >30 Days</button>
                <button
                    class="preset-btn"
                    :class="{ 'active': activePreset === 'all' }"
                    @click="setDatePreset('all')"
                >All Time</button>
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
                <button class="clear-all-btn" @click="clearAllFilters()">Clear All</button>
            </div>
        </div>

        <!-- Activities List -->
        <template x-if="loading">
            <div class="loading-center">
                <div class="spinner"></div>
            </div>
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
                    <div class="activity-item" @click="openDetailModal(activity)">
                        <div class="activity-header">
                            <div style="flex: 1;">
                                <div class="activity-title-large" x-text="activity.title"></div>
                                <template x-if="activity.category || activity.category_snapshot_name">
                                    <div class="card-category" style="margin-top: 0.5rem;">
                                        <div class="category-dot" :style="'background:' + (activity.category?.color ?? activity.category_snapshot_color)"></div>
                                        <span x-text="activity.category?.name ?? activity.category_snapshot_name"></span>
                                    </div>
                                </template>
                                <div x-show="activity.tags && activity.tags.length > 0" class="tags-container">
                                    <template x-for="tag in (activity.tags || [])" :key="tag">
                                        <span class="tag-badge">
                                            #<span x-text="tag"></span>
                                        </span>
                                    </template>
                                </div>
                            </div>
                            <div class="activity-meta">
                                <div class="activity-date" x-text="formatDate(activity.completed_at)"></div>
                                <template x-if="activity.time_spent_minutes">
                                    <div class="activity-time" x-text="formatTime(activity.time_spent_minutes)"></div>
                                </template>
                                <template x-if="activity.deadline">
                                    <div class="activity-deadline" x-text="'Due: ' + formatDate(activity.deadline)"></div>
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

    <!-- Detail Modal -->
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
                    <button class="btn btn-danger" @click="deleteActivity(detailModal.activity); detailModal.open = false">Delete</button>
                    <button class="btn btn-ghost" @click="detailModal.open = false">Close</button>
                </div>
            </div>
        </div>
    </template>

    <script>
        const API_BASE = 'http://reflectboard-api.local/api/v1';

        function donePage() {
            return {
                loading: true,
                activities: [],
                categories: [],
                filters: {
                    search: '',
                    category_id: '',
                    date_from: '',
                    date_to: '',
                },
                activePreset: '30days',
                detailModal: {
                    open: false,
                    activity: null,
                },
                ws: null,

                async init() {
                    const token = document.querySelector('meta[name="api-token"]')?.content;
                    if (!token) {
                        window.location.href = '/login';
                        return;
                    }

                    await this.loadCategories();
                    this.setDatePreset('30days');
                    this.initWs(token);
                },

                initWs(token) {
                    const url = new URL(API_BASE);
                    const wsProtocol = url.protocol === 'https:' ? 'wss:' : 'ws:';
                    const wsUrl = `${wsProtocol}//${url.host}/api/v1/ws?token=${token}`;

                    this.ws = new WebSocket(wsUrl);

                    this.ws.onmessage = (event) => {
                        const payload = JSON.parse(event.data);
                        this.handleRemoteUpdate(payload);
                    };

                    this.ws.onclose = () => {
                        console.log("WebSocket disconnected. Auto-reconnect in 3s...");
                        setTimeout(() => this.initWs(token), 3000);
                    };
                },

                handleRemoteUpdate(payload) {
                    const action = payload.action;
                    const item = payload.data;

                    if (action === 'update' && item.status === 'done') {
                        const index = this.activities.findIndex(a => a.id === item.id);
                        if (index !== -1) {
                            this.activities[index] = item;
                        } else {
                            this.activities.unshift(item);
                        }
                    } else if (action === 'delete') {
                        this.activities = this.activities.filter(a => a.id !== item.id);
                    } else if (action === 'reorder' || action === 'create') {
                        this.applyFilters();
                    }
                },

                getAuthConfig() {
                    const token = document.querySelector('meta[name="api-token"]')?.content;
                    return { headers: { Authorization: `Bearer ${token}` } };
                },

                async loadCategories() {
                    try {
                        const res = await axios.get(`${API_BASE}/categories`, this.getAuthConfig());
                        this.categories = res.data;
                    } catch (e) {
                        console.error('Error loading categories', e);
                    }
                },

                async applyFilters() {
                    this.loading = true;
                    this.activePreset = '';
                    try {
                        const params = {};
                        if (this.filters.search) params.search = this.filters.search;
                        if (this.filters.category_id) params.category_id = this.filters.category_id;
                        if (this.filters.date_from) params.date_from = this.filters.date_from;
                        if (this.filters.date_to) params.date_to = this.filters.date_to;

                        const res = await axios.get(`${API_BASE}/activities/done`, {
                            ...this.getAuthConfig(),
                            params,
                        });
                        this.activities = res.data;
                    } catch (e) {
                        console.error('Error loading activities', e);
                    } finally {
                        this.loading = false;
                    }
                },

                setDatePreset(preset) {
                    this.activePreset = preset;
                    const today = new Date();
                    const formatDate = (d) => d.toISOString().split('T')[0];

                    if (preset === 'today') {
                        this.filters.date_from = formatDate(today);
                        this.filters.date_to = formatDate(today);
                    } else if (preset === '7days') {
                        const weekAgo = new Date(today);
                        weekAgo.setDate(weekAgo.getDate() - 7);
                        this.filters.date_from = formatDate(weekAgo);
                        this.filters.date_to = formatDate(today);
                    } else if (preset === '30days') {
                        const monthAgo = new Date(today);
                        monthAgo.setDate(monthAgo.getDate() - 30);
                        this.filters.date_from = formatDate(monthAgo);
                        this.filters.date_to = formatDate(today);
                    } else if (preset === 'all') {
                        this.filters.date_from = '';
                        this.filters.date_to = '';
                    }

                    this.applyFilters();
                },

                hasActiveFilters() {
                    return this.filters.search || this.filters.category_id || this.filters.date_from || this.filters.date_to;
                },

                clearAllFilters() {
                    this.filters.search = '';
                    this.filters.category_id = '';
                    this.filters.date_from = '';
                    this.filters.date_to = '';
                    this.activePreset = '';
                    this.applyFilters();
                },

                clearDateFilter() {
                    this.filters.date_from = '';
                    this.filters.date_to = '';
                    this.activePreset = '';
                    this.applyFilters();
                },

                getCategoryName(id) {
                    const cat = this.categories.find(c => c.id == id);
                    return cat ? cat.name : '';
                },

                formatDateRange() {
                    if (this.filters.date_from && this.filters.date_to) {
                        return `${this.filters.date_from} — ${this.filters.date_to}`;
                    } else if (this.filters.date_from) {
                        return `From ${this.filters.date_from}`;
                    } else if (this.filters.date_to) {
                        return `Until ${this.filters.date_to}`;
                    }
                    return '';
                },

                formatDate(dateStr, withTime = false) {
                    if (!dateStr) return '';
                    const d = new Date(dateStr);
                    const date = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    if (withTime) {
                        const time = d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                        return `${date} at ${time}`;
                    }
                    return date;
                },

                formatTime(minutes) {
                    if (!minutes) return '';
                    const h = Math.floor(minutes / 60);
                    const m = minutes % 60;
                    if (h > 0 && m > 0) return `${h}h ${m}m`;
                    if (h > 0) return `${h}h`;
                    return `${m}m`;
                },

                openDetailModal(activity) {
                    this.detailModal.activity = activity;
                    this.detailModal.open = true;
                },

                async deleteActivity(activity) {
                    if (!confirm(`Delete "${activity.title}"?`)) return;

                    try {
                        await axios.delete(`${API_BASE}/activities/${activity.id}`, this.getAuthConfig());
                        this.activities = this.activities.filter(a => a.id !== activity.id);
                    } catch (e) {
                        console.error('Error deleting activity', e);
                        alert('Failed to delete activity');
                    }
                },
            };
        }
    </script>
</body>
</html>

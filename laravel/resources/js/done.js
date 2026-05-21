export function donePage() {
    const API_BASE = window.API_BASE;

    return {
        // ─── State ───────────────────────────────────────────
        loading: true,
        activities: [],
        allActivities: [],
        categories: [],
        filters: {
            search: '',
            category_id: '',
            date_from: '',
            date_to: '',
            productivity: '',
        },
        activePreset: '30days',
        detailModal: {
            open: false,
            activity: null,
            subtasks: [],
            loadingSubtasks: false,
        },
        ws: null,

        // ─── Auth ─────────────────────────────────────────────
        getAuthConfig() {
            const token = document.querySelector('meta[name="api-token"]')?.content;
            return { headers: { Authorization: `Bearer ${token}` } };
        },

        // ─── Init ─────────────────────────────────────────────
        async init() {
            const token = document.querySelector('meta[name="api-token"]')?.content;
            if (!token) {
                window.location.href = '/login';
                return;
            }
            await this.loadCategories();
            this.setDatePreset('30days');
            this.initWs(token);
            window.addEventListener('fab:captured', () => this.applyFilters());
        },

        // ─── WebSocket ────────────────────────────────────────
        initWs(token) {
            const url = new URL(API_BASE);
            const wsProtocol = url.protocol === 'https:' ? 'wss:' : 'ws:';
            const wsUrl = `${wsProtocol}//${url.host}/api/v1/ws?token=${token}`;

            this.ws = new WebSocket(wsUrl);
            this.ws.onmessage = (event) => {
                this.handleRemoteUpdate(JSON.parse(event.data));
            };
            this.ws.onclose = () => {
                setTimeout(() => this.initWs(token), 3000);
            };
        },

        handleRemoteUpdate(payload) {
            const { action, data: item } = payload;

            if (action === 'update' && item.status === 'done') {
                const index = this.activities.findIndex(a => a.id === item.id);
                if (index !== -1) {
                    this.activities[index] = item;
                } else {
                    this.activities.unshift(item);
                }
            } else if (action === 'delete') {
                this.activities = this.activities.filter(a => a.id !== item.id);
            } else if (action === 'create' || action === 'reorder') {
                this.applyFilters();
            }
        },

        // ─── Data ─────────────────────────────────────────────
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
                if (this.filters.search)      params.search      = this.filters.search;
                if (this.filters.category_id) params.category_id = this.filters.category_id;
                if (this.filters.date_from)   params.date_from   = this.filters.date_from;
                if (this.filters.date_to)     params.date_to     = this.filters.date_to;

                const res = await axios.get(`${API_BASE}/activities/done`, {
                    ...this.getAuthConfig(),
                    params,
                });
                this.allActivities = res.data;
                this.applyProductivityFilter();
            } catch (e) {
                console.error('Error loading activities', e);
            } finally {
                this.loading = false;
            }
        },

        // ─── Filters ──────────────────────────────────────────
        setDatePreset(preset) {
            this.activePreset = preset;
            const today = new Date();
            const fmt = (d) => d.toISOString().split('T')[0];

            if (preset === 'today') {
                this.filters.date_from = fmt(today);
                this.filters.date_to   = fmt(today);
            } else if (preset === '7days') {
                const d = new Date(today);
                d.setDate(d.getDate() - 7);
                this.filters.date_from = fmt(d);
                this.filters.date_to   = fmt(today);
            } else if (preset === '30days') {
                const d = new Date(today);
                d.setDate(d.getDate() - 30);
                this.filters.date_from = fmt(d);
                this.filters.date_to   = fmt(today);
            } else if (preset === 'all') {
                this.filters.date_from = '';
                this.filters.date_to   = '';
            }

            this.applyFilters();
        },

        applyProductivityFilter() {
            if (!this.filters.productivity) {
                this.activities = this.allActivities;
            } else {
                const wantProductive = this.filters.productivity === 'productive';
                this.activities = this.allActivities.filter(a =>
                    wantProductive ? a.is_productive !== false : a.is_productive === false
                );
            }
        },

        hasActiveFilters() {
            return this.filters.search || this.filters.category_id || this.filters.date_from || this.filters.date_to || this.filters.productivity;
        },

        clearAllFilters() {
            this.filters = { search: '', category_id: '', date_from: '', date_to: '', productivity: '' };
            this.activePreset = '';
            this.applyFilters();
        },

        clearDateFilter() {
            this.filters.date_from = '';
            this.filters.date_to   = '';
            this.activePreset = '';
            this.applyFilters();
        },

        getCategoryName(id) {
            return this.categories.find(c => c.id == id)?.name ?? '';
        },

        formatDateRange() {
            const { date_from: from, date_to: to } = this.filters;
            if (from && to)  return `${from} — ${to}`;
            if (from)        return `From ${from}`;
            if (to)          return `Until ${to}`;
            return '';
        },

        // ─── Modal ────────────────────────────────────────────
        async openDetailModal(activity) {
            this.detailModal = {
                open: true,
                activity,
                subtasks: [],
                loadingSubtasks: false,
            };

            if (activity.is_project) {
                await this.loadSubtasks(activity.id);
            }
        },

        async loadSubtasks(projectId) {
            this.detailModal.loadingSubtasks = true;
            try {
                const res = await axios.get(`${API_BASE}/activities/${projectId}/subtasks`, this.getAuthConfig());
                this.detailModal.subtasks = res.data.filter(s => s.status === 'done');
            } catch (e) {
                console.error('Error loading subtasks', e);
                this.detailModal.subtasks = [];
            } finally {
                this.detailModal.loadingSubtasks = false;
            }
        },

        async deleteActivity(activity) {
            if (!confirm(`Delete "${activity.title}"?`)) return;
            try {
                await axios.delete(`${API_BASE}/activities/${activity.id}`, this.getAuthConfig());
                this.activities = this.activities.filter(a => a.id !== activity.id);
                this.detailModal.open = false;
            } catch (e) {
                alert('Failed to delete activity');
            }
        },

        // ─── Utilities ────────────────────────────────────────
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
            if (h > 0)          return `${h}h`;
            return `${m}m`;
        },

        pluralizeSubtasks(count) {
            if (count === 1) return '1 subtask';
            return `${count} subtasks`;
        },
    };
}

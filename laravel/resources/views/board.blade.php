<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="api-token" content="{{ auth()->check() ? auth()->user()->api_token : '' }}">
    <title>ReflectBoard</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;500&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="board()" x-init="init()">

    <!-- Topbar -->
    <nav class="topbar">
        <div style="margin-right: auto;">
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
                            <div style="display:flex;justify-content:center;padding:1.5rem;">
                                <div class="spinner"></div>
                            </div>
                        </template>

                        <template x-if="!loading && activities[col.status]?.length === 0">
                            <div class="empty">— empty —</div>
                        </template>

                        <template x-if="!loading">
                            <template x-for="activity in activities[col.status] ?? []" :key="activity.id">
                                <div class="card" :data-id="activity.id" @click="openEditModal(activity)">
                                    <button
                                        class="complete-circle"
                                        title="Complete"
                                        @click.stop="openCompleteModal(activity)"
                                    ></button>
                                    <div class="card-title" x-text="activity.title"></div>

                                    <template x-if="activity.category">
                                        <div class="card-category">
                                            <div class="category-dot" :style="'background:' + activity.category.color"></div>
                                            <span x-text="activity.category.name"></span>
                                        </div>
                                    </template>

                                    <template x-if="activity.deadline">
                                        <div
                                            class="card-deadline"
                                            :class="isOverdue(activity.deadline) ? 'overdue' : ''"
                                            x-text="formatDate(activity.deadline, false)"
                                        ></div>
                                    </template>
                                </div>
                            </template>
                        </template>
                    </div>

                    <template x-if="col.status !== 'on_reflection'">
                        <div style="padding: 0 0.625rem 0.625rem;">
                            <button class="add-btn" @click.stop="openCreateModal(col.status)">+ add</button>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <!-- Modal: creating activity -->
    <template x-if="modal.open">
        <div class="modal-overlay" @click.self="modal.open = false">
            <div class="modal">
                <div class="modal-title">New Task — <span x-text="columnLabel(modal.status)"></span></div>

                <div class="field">
                    <label>Title *</label>
                    <input type="text" x-model="modal.title" placeholder="What needs to be done?" @keydown.enter="createActivity()">
                </div>

                <div class="field">
                    <label>Description</label>
                    <textarea x-model="modal.description" rows="2" placeholder="Details"></textarea>
                </div>

                <div class="field">
                    <label>Category</label>
                    <div class="category-grid">
                        <template x-for="cat in categories" :key="cat.id">
                            <button
                                type="button"
                                @click="selectCategory(cat.id)"
                                :class="{ 'selected': modal.category_id == cat.id }"
                                class="category-btn"
                            >
                                <div class="category-dot" :style="'background:' + cat.color"></div>
                                <span class="flex-1 text-left" x-text="cat.name"></span>
                                <span @click.stop="deleteCategory(cat.id)" class="delete-x">×</span>
                            </button>
                        </template>
                    </div>
                    <button
                        type="button"
                        @click="openCreateCategoryModal()"
                        class="btn btn-ghost"
                        style="width: 100%; font-size: 0.875rem; justify-content: center;">
                        + New category
                    </button>
                </div>

                <div class="field">
                    <label>Tags</label>
                    <input type="text" x-model="modal.tags_string" placeholder="e.g. #fastapi #gym" @keydown.enter="createActivity()">
                </div>

                <div class="field">
                    <label>Deadline</label>
                    <div style="display:flex;gap:0.5rem;">
                        <input type="date" x-model="modal.deadlineDate" style="flex:1;">
                        <input type="time" x-model="modal.deadlineTime" style="width:9rem;">
                    </div>
                </div>

                <div class="modal-actions">
                    <button class="btn btn-ghost" @click="modal.open = false">Cancel</button>
                    <button class="btn btn-primary" @click="createActivity()" :disabled="!modal.title.trim()">Create</button>
                </div>
            </div>
        </div>
    </template>

    <!-- Modal: viewing/editing activity -->
    <template x-if="editModal.open">
        <div class="modal-overlay" @click.self="editModal.open = false">
            <div class="modal">
                <div class="modal-header" style="display:flex; justify-content:center;">
                    <div style="text-align:center;">
                        <div class="detail-label">Created</div>
                        <div class="detail-value" x-text="formatDate(editModal.activity?.created_at, true)"></div>
                    </div>
                </div>

                <div class="field">
                    <label>Title</label>
                    <input type="text" x-model="editModal.title">
                </div>

                <div class="field">
                    <label>Description</label>
                    <textarea x-model="editModal.description" rows="2"></textarea>
                </div>

                <div class="field">
                    <label>Category</label>
                    <div class="category-grid">
                        <template x-for="cat in categories" :key="cat.id">
                            <button
                                type="button"
                                @click="selectCategory(cat.id, true)"
                                :class="{ 'selected': editModal.category_id == cat.id }"
                                class="category-btn"
                            >
                                <div class="category-dot" :style="'background:' + cat.color"></div>
                                <span class="flex-1 text-left" x-text="cat.name"></span>
                                <span @click.stop="deleteCategory(cat.id)" class="delete-x">×</span>
                            </button>
                        </template>
                    </div>

    <button
        type="button"
        @click="openCreateCategoryModal()"
        class="btn btn-ghost"
        style="width: 100%; font-size: 0.875rem; justify-content: center;">
        + New category
    </button>
                </div>

                <div class="field">
                    <label>Tags</label>
                    <input type="text" x-model="editModal.tags_string" placeholder="e.g. #fastapi #gym">
                </div>

                <div class="field">
                    <label>Deadline</label>
                    <div style="display:flex;gap:0.5rem;">
                        <input type="date" x-model="editModal.deadlineDate" style="flex:1;">
                        <input type="time" x-model="editModal.deadlineTime" style="width:9rem;">
                    </div>
                </div>

                <div class="modal-actions" style="justify-content: space-between; margin-top: 1.5rem;">
                    <button class="btn btn-danger" @click="deleteActivity(editModal.activity)">Delete</button>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn btn-ghost" @click="editModal.open = false">Cancel</button>
                        <button class="btn btn-primary" @click="saveActivity()">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- Modal: completing activity -->
    <template x-if="completeModal.open">
        <div class="modal-overlay" @click.self="completeModal.open = false">
            <div class="modal">
                <div class="modal-title">Complete Task</div>
                <div class="modal-activity-title" style="margin-bottom: 1.25rem;" x-text="completeModal.activity?.title"></div>

                <div class="field">
                    <label>Time spent in mins (optional)</label>
                    <input type="number" x-model="completeModal.time_spent" placeholder="e.g. 45" min="0">
                </div>

                <div class="field">
                    <label>Reflection (optional)</label>
                    <textarea
                        x-model="completeModal.reflection"
                        rows="4"
                        placeholder="What are your thoughts on this task? Any challenges?"
                    ></textarea>
                </div>

                <div class="modal-actions" style="justify-content: space-between;">
                    <button class="btn btn-ghost" @click="completeModal.open = false">Cancel</button>
                    <button
                        class="btn btn-primary"
                        style="background: #98bb6c; color: var(--bg);"
                        @click="completeActivity()"
                    >Complete</button>
                </div>
            </div>
        </div>
    </template>

    <!-- Modal: creating new category -->
    <template x-if="categoryModal.open">
        <div class="modal-overlay" @click.self="categoryModal.open = false">
            <div class="modal" style="max-width: 26rem;">
                <div class="modal-title">New Category</div>

                <div class="field">
                    <label>Name *</label>
                    <input type="text" x-model="categoryModal.name" placeholder="e.g. Work, Health, Study" @keydown.enter="createNewCategory()">
                </div>

                <div class="field">
                    <label>Color</label>
                    <input type="color" x-model="categoryModal.color" style="height: 2.75rem; width: 100%; padding: 0.25rem; border: 1px solid var(--border); border-radius: 0.375rem;">
                </div>

                <div class="modal-actions">
                    <button class="btn btn-ghost" @click="categoryModal.open = false">Cancel</button>
                    <button class="btn btn-primary" @click="createNewCategory()" :disabled="!categoryModal.name.trim()">Create Category</button>
                </div>
            </div>
        </div>
    </template>

    <!-- Toast -->
    <template x-if="toast.show">
        <div class="toast" x-text="toast.message"></div>
    </template>

<script>
        const API_BASE = '{{ config("services.api_base.url") }}';

        function board() {
            return {
                loading: true,
                columns: [
                    { status: 'backlog',       label: 'Backlog' },
                    { status: 'today',         label: 'Today' },
                    { status: 'in_process',    label: 'In Process' },
                    { status: 'on_reflection', label: 'On Reflection' },
                ],
                activities: {
                    backlog: [],
                    today: [],
                    in_process: [],
                    on_reflection: [],
                },
                categories: [],
                modal: {
                    open: false,
                    status: 'backlog',
                    title: '',
                    description: '',
                    category_id: '',
                    deadlineDate: '',
                    deadlineTime: '',
                    tags_string: '',
                },
                editModal: {
                    open: false,
                    activity: null,
                    title: '',
                    description: '',
                    category_id: '',
                    deadlineDate: '',
                    deadlineTime: '',
                    reflection_text: '',
                    time_spent_minutes: '',
                    tags_string: '',
                },
                completeModal: {
                    open: false,
                    activity: null,
                    reflection: '',
                    time_spent: '',
                },
                categoryModal: {
                    open: false,
                    name: '',
                    color: '#957fb8',
                },
                toast: { show: false, message: '' },
                ws: null,

                getAuthConfig() {
                    const token = document.querySelector('meta[name="api-token"]')?.content;
                    return {
                        headers: {
                            'Authorization': `Bearer ${token}`
                        }
                    };
                },

                async init() {
                    const token = document.querySelector('meta[name="api-token"]')?.content;
                    if (!token) {
                        window.location.href = '/login';
                        return;
                    }

                    await this.loadActivities();
                    await this.loadCategories();
                    this.$nextTick(() => this.initSortable());
                    this.initWs(token);
                },

                initWs(token) {
                    const url = new URL(API_BASE);
                    const wsProtocol = url.protocol === 'https:' ? 'wss:' : 'ws:';
                    const wsUrl = `${wsProtocol}//${url.host}/api/v1/ws?token=${token}`;

                    this.ws = new WebSocket(wsUrl);

                    this.ws.onmessage = (event) => {
                        const updatedActivity = JSON.parse(event.data);
                        this.handleRemoteUpdate(updatedActivity);
                    };

                    this.ws.onclose = () => {
                        console.log("WebSocket disconnected. Auto-reconnect in 3s...");
                        setTimeout(() => this.initWs(token), 3000);
                    };
                },

                handleRemoteUpdate(payload) {
                    const action = payload.action;
                    const item = payload.data;

                    if (action === 'create') {
                        if (this.activities[item.status]) {
                            const exists = this.activities[item.status].find(a => a.id === item.id);
                            if (!exists) {
                                this.activities[item.status].push(item);
                            }
                        }

                    } else if (action === 'update') {
                        let foundInCorrectColumn = false;
                        const targetCol = this.activities[item.status];

                        if (targetCol) {
                            const idx = targetCol.findIndex(a => a.id === item.id);
                            if (idx > -1) {
                                targetCol[idx] = item;
                                foundInCorrectColumn = true;
                            }
                        }

                        if (!foundInCorrectColumn) {
                            Object.keys(this.activities).forEach(status => {
                                this.activities[status] = this.activities[status].filter(a => a.id !== item.id);
                            });
                            if (this.activities[item.status]) {
                                this.activities[item.status].push(item);
                            }
                        }

                    } else if (action === 'delete') {
                        Object.keys(this.activities).forEach(status => {
                            this.activities[status] = this.activities[status].filter(a => a.id !== item.id);
                        });
                    }
                },

                async loadActivities() {
                    this.loading = true;
                    try {
                        const res = await axios.get(`${API_BASE}/activities`, this.getAuthConfig());
                        const all = res.data;
                        Object.keys(this.activities).forEach(k => this.activities[k] = []);
                        all.forEach(a => {
                            if (this.activities[a.status] !== undefined) {
                                this.activities[a.status].push(a);
                            }
                        });
                    } catch (e) {
                        this.showToast('Error loading activities');
                    } finally {
                        this.loading = false;
                    }
                },

                async loadCategories() {
                    try {
                        const res = await axios.get(`${API_BASE}/categories`, this.getAuthConfig());
                        this.categories = res.data;
                    } catch (e) {
                        this.categories = [];
                    }
                },

                initSortable() {
                    this.columns.forEach(col => {
                        const el = document.getElementById('col-' + col.status);
                        if (!el) return;
                        Sortable.create(el, {
                            group: 'board',
                            animation: 150,
                            ghostClass: 'sortable-ghost',
                            chosenClass: 'sortable-chosen',
                            draggable: '.card',
                            onEnd: async (evt) => {
                                const activityId = parseInt(evt.item.dataset.id);
                                const newStatus = evt.to.dataset.status;
                                if (evt.from === evt.to) return;
                                try {
                                    await axios.patch(`${API_BASE}/activities/${activityId}`, { status: newStatus }, this.getAuthConfig());
                                    await this.loadActivities();
                                } catch (e) {
                                    this.showToast('Error moving activity');
                                    await this.loadActivities();
                                }
                            }
                        });
                    });
                },

                openCreateModal(status) {
                    this.modal = {
                        open: true,
                        status,
                        title: '',
                        description: '',
                        category_id: '',
                        deadlineDate: '',
                        deadlineTime: '',
                    };
                },

                openEditModal(activity) {
                    const dl = activity.deadline ? activity.deadline.split('T') : ['', ''];
                    const timeRaw = dl[1] ? dl[1].slice(0, 5) : '';
                    const timeVal = (timeRaw === '00:00') ? '' : timeRaw;

                    this.editModal = {
                        open: true,
                        activity,
                        title: activity.title,
                        description: activity.description || '',
                        category_id: activity.category_id || '',
                        deadlineDate: dl[0] || '',
                        deadlineTime: timeVal,
                        reflection_text: activity.reflection_text || '',
                        time_spent_minutes: activity.time_spent_minutes || '',
                        tags_string: (activity.tags || []).map(t => `#${t}`).join(' '),
                    };
                },

                openCompleteModal(activity) {
                    this.completeModal = {
                        open: true,
                        activity,
                        reflection: '',
                        time_spent: '',
                    };
                },

                selectCategory(id, isEdit = false) {
                    if (isEdit) {
                        this.editModal.category_id = id;
                    } else {
                        this.modal.category_id = id;
                    }
                },

                async deleteCategory(categoryId) {
                    if (!confirm('Delete this category? It will be removed from all tasks.')) return;

                    try {
                        await axios.delete(`${API_BASE}/categories/${categoryId}`, this.getAuthConfig());

                        this.categories = this.categories.filter(c => c.id !== categoryId);

                        if (this.modal.category_id == categoryId) this.modal.category_id = '';
                        if (this.editModal.category_id == categoryId) this.editModal.category_id = '';

                        this.showToast('Category deleted');
                    } catch (e) {
                        console.error(e);
                        this.showToast('Error deleting category');
                    }
                },

                openCreateCategoryModal() {
                    this.categoryModal = {
                        open: true,
                        name: '',
                        color: '#957fb8',
                    };
                },

                async createNewCategory() {
                    if (!this.categoryModal.name.trim()) return;
                    try {
                        const res = await axios.post(`${API_BASE}/categories`, {
                            name: this.categoryModal.name.trim(),
                            color: this.categoryModal.color,
                        }, this.getAuthConfig());
                        this.categories.push(res.data);
                        this.categoryModal.open = false;
                        this.showToast('Category created');
                    } catch (e) {
                        this.showToast('Error creating category');
                    }
                },

                getDeadline(dateVal, timeVal) {
                    if (!dateVal) return null;
                    if (!timeVal) return dateVal;
                    return `${dateVal}T${timeVal}:00`;
                },

                parseTags(tagsString) {
                    if (!tagsString) return [];
                    if (!tagsString.trim()) return [];
                    return tagsString.split(/[\s,]+/)
                        .map(tag => tag.replace(/^#/, '').trim())
                        .filter(tag => tag.length > 0);
                },

                async createActivity() {
                    if (!this.modal.title.trim()) return;
                    try {
                        await axios.post(`${API_BASE}/activities`, {
                            title: this.modal.title.trim(),
                            description: this.modal.description || null,
                            category_id: this.modal.category_id || null,
                            deadline: this.getDeadline(this.modal.deadlineDate, this.modal.deadlineTime),
                            status: this.modal.status,
                            tags: this.parseTags(this.modal.tags_string),
                        }, this.getAuthConfig());

                        this.modal.open = false;
                        await this.loadActivities();
                        this.showToast('Activity created');
                    } catch (e) {
                        this.showToast('Error creating activity');
                    }
                },

                async saveActivity() {
                    const id = this.editModal.activity.id;
                    try {
                        await axios.patch(`${API_BASE}/activities/${id}`, {
                            title: this.editModal.title,
                            description: this.editModal.description || null,
                            category_id: this.editModal.category_id || null,
                            deadline: this.getDeadline(this.editModal.deadlineDate, this.editModal.deadlineTime),
                            reflection_text: this.editModal.reflection_text || null,
                            time_spent_minutes: this.editModal.time_spent_minutes ? parseInt(this.editModal.time_spent_minutes) : null,
                            tags: this.parseTags(this.editModal.tags_string),
                        }, this.getAuthConfig());
                        this.editModal.open = false;
                        await this.loadActivities();
                        this.showToast('Updated successfully');
                    } catch (e) {
                        this.showToast('Error updating activity');
                    }
                },

                async completeActivity() {
                    if (!this.completeModal.activity) return;
                    const activityId = this.completeModal.activity.id;
                    try {
                        await axios.patch(`${API_BASE}/activities/${activityId}`, {
                            status: 'done',
                            reflection_text: this.completeModal.reflection || null,
                            time_spent_minutes: parseInt(this.completeModal.time_spent) || null,
                        }, this.getAuthConfig());
                        this.completeModal.open = false;
                        await this.loadActivities();
                        this.showToast('Activity completed');
                    } catch (e) {
                        this.showToast('Error completing activity');
                    }
                },

                async deleteActivity(activity) {
                    if (!confirm(`Delete "${activity.title}"?`)) return;
                    try {
                        await axios.delete(`${API_BASE}/activities/${activity.id}`, this.getAuthConfig());
                        this.editModal.open = false;
                        await this.loadActivities();
                        this.showToast('Activity deleted');
                    } catch (e) {
                        this.showToast('Error deleting activity');
                    }
                },

                columnLabel(status) {
                    return this.columns.find(c => c.status === status)?.label ?? status;
                },

                statusLabel(status) {
                    const map = {
                        backlog: 'Backlog', today: 'Today',
                        in_process: 'In Process', on_reflection: 'On Reflection', done: 'Done',
                    };
                    return map[status] ?? status;
                },

                formatDate(dt, showYear = false) {
                    if (!dt) return '';
                    const date = new Date(dt);
                    if (isNaN(date.getTime())) return dt;
                    const baseOptions = { day: 'numeric', month: 'short', ...(showYear ? { year: 'numeric' } : {}) };
                    const isDateOnly = dt.length === 10 || (date.getHours() === 0 && date.getMinutes() === 0 && date.getSeconds() === 0);
                    if (isDateOnly) return date.toLocaleDateString('en-US', baseOptions);
                    return date.toLocaleString('en-US', { ...baseOptions, hour: '2-digit', minute: '2-digit' });
                },

                isOverdue(dt) {
                    return dt && new Date(dt) < new Date();
                },

                showToast(message) {
                    this.toast = { show: true, message };
                    setTimeout(() => this.toast.show = false, 2500);
                },
            };
        }
    </script>

</body>
</html>

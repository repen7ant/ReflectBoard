
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="api-token" content="{{ auth()->check() ? 'stub-token' : '' }}">
    <title>ReflectBoard</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;500&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/board.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
                                            x-text="formatDate(activity.deadline)"
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
                    <textarea x-model="modal.description" rows="2" placeholder="Details..."></textarea>
                </div>

                <div class="field">
                    <label>Category</label>
                    <select x-model="modal.category_id">
                        <option value="">— no category —</option>
                        <template x-for="cat in categories" :key="cat.id">
                            <option :value="cat.id" x-text="cat.name"></option>
                        </template>
                    </select>
                </div>

                <div class="field">
                    <label>Deadline</label>
                    <input
                        type="text"
                        id="deadline-picker"
                        x-model="modal.deadline"
                        placeholder="Select date..."
                        readonly
                    >
                </div>

                <div class="modal-actions">
                    <button class="btn btn-ghost" @click="modal.open = false">Cancel</button>
                    <button class="btn btn-primary" @click="createActivity()" :disabled="!modal.title.trim()">Create</button>
                </div>
            </div>
        </div>
    </template>

    <!-- Modal: viewing activity -->
    <template x-if="editModal.open">
        <div class="modal-overlay" @click.self="editModal.open = false">
            <div class="modal">
                <div class="modal-header">
                    <div>
                        <div class="modal-title" style="margin-bottom:0.25rem;">Activity</div>
                        <div class="modal-activity-title" x-text="editModal.activity?.title"></div>
                    </div>
                    <button class="close-btn" @click="editModal.open = false">✕</button>
                </div>

                <div style="margin-bottom:1rem;">
                    <span class="status-badge" :class="'status-' + editModal.activity?.status" x-text="statusLabel(editModal.activity?.status)"></span>
                </div>

                <template x-if="editModal.activity?.category">
                    <div class="detail-row">
                        <span class="detail-label">Category</span>
                        <div style="display:flex;align-items:center;gap:0.375rem;">
                            <div class="category-dot" :style="'background:' + editModal.activity.category.color"></div>
                            <span class="detail-value" x-text="editModal.activity.category.name"></span>
                        </div>
                    </div>
                </template>

                <template x-if="editModal.activity?.deadline">
                    <div class="detail-row">
                        <span class="detail-label">Deadline</span>
                        <span
                            class="detail-value"
                            :class="isOverdue(editModal.activity.deadline) ? 'overdue' : ''"
                            x-text="formatDate(editModal.activity.deadline)"
                        ></span>
                    </div>
                </template>

                <template x-if="editModal.activity?.created_at">
                    <div class="detail-row">
                        <span class="detail-label">Created</span>
                        <span class="detail-value" x-text="formatDate(editModal.activity.created_at)"></span>
                    </div>
                </template>

                <template x-if="editModal.activity?.description">
                    <div style="margin-top:1rem;">
                        <div class="detail-label" style="margin-bottom:0.5rem;">Description</div>
                        <div class="description-block" x-text="editModal.activity.description"></div>
                    </div>
                </template>

                <div class="coming-soon">✏️ Editing coming soon</div>

                <div class="modal-actions" style="justify-content:space-between;">
                    <button class="btn btn-danger" @click="deleteActivity(editModal.activity)">Delete</button>
                    <button class="btn btn-ghost" @click="editModal.open = false">Close</button>
                </div>
            </div>
        </div>
    </template>

    <!-- Toast -->
    <template x-if="toast.show">
        <div class="toast" x-text="toast.message"></div>
    </template>

    <script>
        const API_BASE = '{{ config("services.api_base.url") }}'
        const USER_ID = 1 // TODO: replace with ID from JWT in sprint #3

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
                    deadline: '',
                },
                editModal: {
                    open: false,
                    activity: null,
                },
                toast: { show: false, message: '' },
                fp: null,

                async init() {
                    await this.loadActivities()
                    await this.loadCategories()

                    this.$nextTick(() => {
                        this.initSortable()
                    });

                    window.addEventListener('clear-date', () => {
                                    if (this.fp) this.fp.clear();
                    });
                },

                async loadActivities() {
                    this.loading = true
                    try {
                        const res = await axios.get(`${API_BASE}/activities`)
                        const all = res.data
                        Object.keys(this.activities).forEach(k => this.activities[k] = [])
                        all.forEach(a => {
                            if (this.activities[a.status] !== undefined) {
                                this.activities[a.status].push(a)
                            }
                        })
                    } catch (e) {
                        this.showToast('Error loading activities')
                    } finally {
                        this.loading = false
                    }
                },

                async loadCategories() {
                    // TODO: GET /categories endpoint
                    this.categories = []
                },

                initSortable() {
                    this.columns.forEach(col => {
                        const el = document.getElementById('col-' + col.status)
                        if (!el) return
                        Sortable.create(el, {
                            group: 'board',
                            animation: 150,
                            ghostClass: 'sortable-ghost',
                            chosenClass: 'sortable-chosen',
                            draggable: '.card',
                            onEnd: async (evt) => {
                                const activityId = parseInt(evt.item.dataset.id)
                                const newStatus = evt.to.dataset.status
                                if (evt.from === evt.to) return
                                try {
                                    await axios.patch(`${API_BASE}/activities/${activityId}/status`, { status: newStatus })
                                    await this.loadActivities()
                                } catch (e) {
                                    this.showToast('Error moving activity')
                                    await this.loadActivities()
                                }
                            }
                        })
                    })
                },

                initDatePicker() {
                    const el = document.getElementById('deadline-picker');
                    if (!el) return;

                    if (this.fp) this.fp.destroy();

                    this.fp = flatpickr(el, {
                        enableTime: true,
                        time_24hr: true,
                        disableMobile: true,
                        dateFormat: "Y-m-d H:i",
                        altInput: true,
                        altFormat: "F j, Y (H:i)",
                        defaultHour: 0,
                        defaultMinute: 0,
                        allowInput: true,
                        onClose: (selectedDates, dateStr) => {
                            this.modal.deadline = dateStr;
                        }
                    });
                },

                openCreateModal(status) {
                    this.modal = {
                        open: true,
                        status,
                        title: '',
                        description: '',
                        category_id: '',
                        deadline: ''
                    };

                    this.$nextTick(() => this.initDatePicker());
                },

                openEditModal(activity) {
                    this.editModal = { open: true, activity }
                },

                async createActivity() {
                    if (!this.modal.title.trim()) return

                    let deadlineValue = this.modal.deadline || null;

                    try {
                        await axios.post(`${API_BASE}/activities`, {
                            user_id: USER_ID,
                            title: this.modal.title.trim(),
                            description: this.modal.description || null,
                            category_id: this.modal.category_id || null,
                            deadline: deadlineValue,
                            status: this.modal.status,
                        })
                        this.modal.open = false
                        await this.loadActivities()
                        this.showToast('Activity created')
                    } catch (e) {
                        this.showToast('Error creating activity')
                    }
                },

                async deleteActivity(activity) {
                    if (!confirm(`Delete "${activity.title}"?`)) return
                    try {
                        await axios.delete(`${API_BASE}/activities/${activity.id}`)
                        this.editModal.open = false
                        await this.loadActivities()
                        this.showToast('Activity deleted')
                    } catch (e) {
                        this.showToast('Error deleting activity')
                    }
                },

                columnLabel(status) {
                    return this.columns.find(c => c.status === status)?.label ?? status
                },

                statusLabel(status) {
                    const map = {
                        backlog: 'Backlog', today: 'Today',
                        in_process: 'In Process', on_reflection: 'On Reflection', done: 'Done',
                    }
                    return map[status] ?? status
                },

                formatDate(dt) {
                    if (!dt) return '';

                    const date = new Date(dt);
                    if (isNaN(date.getTime())) return dt;

                    const baseOptions = {
                        day: 'numeric',
                        month: 'short',
                        year: 'numeric'
                    };

                    const isDateOnly = date.getHours() === 0 &&
                                       date.getMinutes() === 0 &&
                                       date.getSeconds() === 0;

                    if (isDateOnly) {
                        return date.toLocaleDateString('en-US', baseOptions);
                    }

                    return date.toLocaleString('en-US', {
                        ...baseOptions,
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                },

                isOverdue(dt) {
                    return dt && new Date(dt) < new Date()
                },

                showToast(message) {
                    this.toast = { show: true, message }
                    setTimeout(() => this.toast.show = false, 2500)
                },
            }
        }
    </script>

</body>
</html>

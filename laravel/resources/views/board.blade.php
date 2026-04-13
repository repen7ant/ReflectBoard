<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="api-token" content="{{ auth()->check() ? 'stub-token' : '' }}">
    <title>ReflectBoard</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;500&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/board.css">

</head>
<body x-data="board()" x-init="init()">

    <nav class="topbar">
        <span class="logo">ReflectBoard</span>
        <a href="/board" class="nav-link active">Board</a>
        <a href="/done" class="nav-link">Done</a>
        <a href="/analytics" class="nav-link">Analytics</a>
        <div style="margin-left: auto; display: flex; align-items: center; gap: 16px;">
            <span style="font-size: 16px; color: var(--text-muted);">user@test.com</span>
        </div>
    </nav>

    <div style="padding: 24px; overflow-x: auto;">
        <div style="display: flex; gap: 16px; align-items: flex-start; min-width: max-content;">

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
                            <div style="display: flex; justify-content: center; padding: 24px;">
                                <div class="spinner"></div>
                            </div>
                        </template>

                        <template x-if="!loading && activities[col.status]?.length === 0">
                            <div class="empty">— empty —</div>
                        </template>

                        <template x-if="!loading">
                            <template x-for="activity in activities[col.status] ?? []" :key="activity.id">
                                <div
                                    class="card"
                                    :data-id="activity.id"
                                >
                                    <div class="card-actions">
                                        <button class="card-btn danger" @click.stop="deleteActivity(activity)" title="Delete">✕</button>
                                    </div>

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

                    <div style="padding: 0 10px 10px;">
                        <button class="add-btn" @click="openModal(col.status)">+ add</button>
                    </div>
                </div>
            </template>

        </div>
    </div>

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
                    <input type="datetime-local" x-model="modal.deadline">
                </div>

                <div class="modal-actions">
                    <button class="btn btn-ghost" @click="modal.open = false">Cancel</button>
                    <button class="btn btn-primary" @click="createActivity()" :disabled="!modal.title.trim()">Create</button>
                </div>
            </div>
        </div>
    </template>

    <template x-if="toast.show">
        <div class="toast" x-text="toast.message"></div>
    </template>

    <script>
        const API_BASE = '{{ config("services.api_base.url") }}'
        const USER_ID = 1 // TODO: replace with ID from JWT

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
                toast: { show: false, message: '' },
                sortables: [],

                async init() {
                    await this.loadActivities()
                    await this.loadCategories()
                    this.$nextTick(() => this.initSortable())
                },

                async loadActivities() {
                    this.loading = true
                    try {
                        const res = await axios.get(`${API_BASE}/activities`)
                        const all = res.data

                        // Reset
                        Object.keys(this.activities).forEach(k => this.activities[k] = [])

                        // Distribute to columns
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
                    // TODO: add GET /categories endpoint in FastAPI
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
                            filter: '.add-btn',
                            draggable: '.card',
                            onEnd: async (evt) => {
                                const activityId = parseInt(evt.item.dataset.id)
                                const newStatus = evt.to.dataset.status

                                if (evt.from === evt.to) return

                                try {
                                    await axios.patch(`${API_BASE}/activities/${activityId}/status`, {
                                        status: newStatus
                                    })

                                    await this.loadActivities()
                                } catch (e) {
                                    this.showToast('Error moving activity')
                                    await this.loadActivities()
                                }
                            }
                        })
                    })
                },

                openModal(status) {
                    this.modal = {
                        open: true,
                        status,
                        title: '',
                        description: '',
                        category_id: '',
                        deadline: '',
                    }
                },

                async createActivity() {
                    if (!this.modal.title.trim()) return

                    try {
                        await axios.post(`${API_BASE}/activities`, {
                            user_id: USER_ID,
                            title: this.modal.title.trim(),
                            description: this.modal.description || null,
                            category_id: this.modal.category_id || null,
                            deadline: this.modal.deadline || null,
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
                        await this.loadActivities()
                        this.showToast('Activity deleted')
                    } catch (e) {
                        this.showToast('Error deleting activity')
                    }
                },

                columnLabel(status) {
                    return this.columns.find(c => c.status === status)?.label ?? status
                },

                formatDate(dt) {
                    if (!dt) return ''
                    return new Date(dt).toLocaleDateString('en-US', { day: 'numeric', month: 'short' })
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

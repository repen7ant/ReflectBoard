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

                        <template x-if="!loading && activities[col.status]?.length === 0">
                            <div class="empty">— empty —</div>
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

                                    <!-- Project badge -->
                                    <template x-if="activity.is_project">
                                        <div class="card-project-badge">▸ Project</div>
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

                    <template x-if="col.status !== 'on_reflection'">
                        <div class="column-footer">
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
                        class="btn btn-ghost btn-full">
                        + New category
                    </button>
                </div>

                <div class="field" x-data="{ newTag: '' }">
                    <label>Tags</label>
                    <div class="tag-input-wrapper">

                        <template x-for="(tag, index) in modal.tags" :key="index">
                            <span class="tag-pill">
                                <span x-text="'#' + tag"></span>
                                <button type="button" @click="modal.tags.splice(index, 1)" class="tag-pill-remove">&times;</button>
                            </span>
                        </template>

                        <input
                            type="text"
                            x-model="newTag"
                            :placeholder="modal.tags.length === 0 ? 'Add tag & press space...' : ''"
                            @keydown.space.prevent="if(newTag.trim()){ modal.tags.push(newTag.replace(/^#/, '').trim()); newTag = ''; }"
                            @keydown.enter.prevent="if(newTag.trim()){ modal.tags.push(newTag.replace(/^#/, '').trim()); newTag = ''; }"
                            @keydown.backspace="if(newTag === '' && modal.tags.length > 0){ modal.tags.pop(); }"
                            class="tag-input"
                        >
                    </div>
                </div>

                <div class="field">
                    <label>Project</label>
                    <label style="display:flex; align-items:center; gap:0.75rem; cursor:pointer;">
                        <input
                            type="checkbox"
                            x-model="modal.is_project"
                            style="width:1.1rem; height:1.1rem; accent-color:var(--accent); cursor:pointer;"
                        >
                        <span style="font-size:0.9rem; color:var(--text);">This is a project</span>
                    </label>
                </div>

                <div class="field">
                    <label>Deadline</label>
                    <div class="deadline-inputs">
                        <input type="date" x-model="modal.deadlineDate" class="deadline-date">
                        <input type="time" x-model="modal.deadlineTime" class="deadline-time">
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
                <div class="modal-header modal-header-center">
                    <div class="modal-header-content">
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
                        class="btn btn-ghost btn-full">
                        + New category
                    </button>
                </div>

                <div class="field" x-data="{ newTag: '' }">
                    <label>Tags</label>
                    <div class="tag-input-wrapper">

                        <template x-for="(tag, index) in editModal.tags" :key="index">
                            <span class="tag-pill">
                                <span x-text="'#' + tag"></span>
                                <button type="button" @click="editModal.tags.splice(index, 1)" class="tag-pill-remove">&times;</button>
                            </span>
                        </template>

                        <input
                            type="text"
                            x-model="newTag"
                            :placeholder="editModal.tags.length === 0 ? 'Add tag & press space...' : ''"
                            @keydown.space.prevent="if(newTag.trim()){ editModal.tags.push(newTag.replace(/^#/, '').trim()); newTag = ''; }"
                            @keydown.enter.prevent="if(newTag.trim()){ editModal.tags.push(newTag.replace(/^#/, '').trim()); newTag = ''; }"
                            @keydown.backspace="if(newTag === '' && editModal.tags.length > 0){ editModal.tags.pop(); }"
                            class="tag-input"
                        >
                    </div>
                </div>

                <div class="field">
                    <label>Deadline</label>
                    <div class="deadline-inputs">
                        <input type="date" x-model="editModal.deadlineDate" class="deadline-date">
                        <input type="time" x-model="editModal.deadlineTime" class="deadline-time">
                    </div>
                </div>

                <div class="modal-actions modal-actions-spaced">
                    <button class="btn btn-danger" @click="deleteActivity(editModal.activity)">Delete</button>
                    <div class="modal-actions-group">
                        <button class="btn btn-ghost" @click="editModal.open = false">Cancel</button>
                        <button class="btn btn-primary" @click="saveActivity()">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>
    </template>


    <!-- Modal: project -->
    <template x-if="projectModal.open">
        <div class="modal-overlay" @click.self="projectModal.open = false">
            <div class="modal modal-wide">

                <div class="modal-title">Project</div>
                <div class="modal-activity-title modal-activity-title-spaced" x-text="projectModal.project?.title"></div>

                <!-- Progress -->
                <template x-if="projectModal.subtasks.length > 0">
                    <div style="margin-bottom:1.25rem;">
                        <div class="card-progress-label" style="margin-bottom:0.375rem;">
                            <span x-text="projectModal.subtasks.filter(s => s.status === 'done').length"></span>
                            /
                            <span x-text="projectModal.subtasks.length"></span>
                            subtasks done
                        </div>
                        <div class="card-progress-bar">
                            <div
                                class="card-progress-fill"
                                :style="'width:' + (projectModal.subtasks.length
                                    ? Math.round(projectModal.subtasks.filter(s => s.status === 'done').length / projectModal.subtasks.length * 100)
                                    : 0) + '%'"
                            ></div>
                        </div>
                    </div>
                </template>

                <!-- List of subtasks -->
                <div class="project-section-label">Subtasks</div>

                <template x-if="projectModal.loadingSubtasks">
                    <div class="loading-center"><div class="spinner"></div></div>
                </template>

                <template x-if="!projectModal.loadingSubtasks">
                    <div class="subtask-list">
                        <template x-if="projectModal.subtasks.length === 0">
                            <div class="subtask-empty">No subtasks yet</div>
                        </template>

                        <template x-for="sub in projectModal.subtasks" :key="sub.id">
                            <div
                                class="subtask-item"
                                :class="{
                                    'done': sub.status === 'done',
                                    'on-board': sub.is_on_board && sub.status !== 'done'
                                }"
                            >
                                <div class="subtask-title" :class="{ 'done': sub.status === 'done' }" x-text="sub.title"></div>

                                <div class="subtask-status" :class="{ 'on-board': sub.is_on_board && sub.status !== 'done' }">
                                    <template x-if="sub.status === 'done'">
                                        <span>✓ done</span>
                                    </template>
                                    <template x-if="sub.is_on_board && sub.status !== 'done'">
                                        <span>on board</span>
                                    </template>
                                </div>

                                <div class="subtask-actions">
                                    <!-- Take to board -->
                                    <template x-if="!sub.is_on_board && sub.status !== 'done'">
                                        <button
                                            class="btn btn-ghost btn-xs"
                                            @click.stop="takeToBoard(sub)"
                                            title="Take to board"
                                        >→ Board</button>
                                    </template>
                                    <!-- Remove from board -->
                                    <template x-if="sub.is_on_board && sub.status !== 'done'">
                                        <button
                                            class="btn btn-ghost btn-xs"
                                            @click.stop="removeFromBoard(sub)"
                                            title="Remove from board"
                                        >← Back</button>
                                    </template>
                                    <!-- Delete -->
                                    <button
                                        class="btn btn-danger btn-xs"
                                        @click.stop="deleteSubtask(sub)"
                                    >×</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Add subtask -->
                <div style="display:flex; gap:0.5rem; margin-bottom:1.25rem;">
                    <input
                        type="text"
                        x-model="projectModal.newSubtaskTitle"
                        placeholder="New subtask..."
                        class="field"
                        style="flex:1; background:var(--surface-2); border:1px solid var(--border); border-radius:0.375rem; padding:0.625rem 0.75rem; color:var(--text); font-family:inherit; font-size:0.9rem; outline:none; margin:0;"
                        @keydown.enter="addSubtask()"
                        @focus="$el.style.borderColor='var(--accent)'"
                        @blur="$el.style.borderColor='var(--border)'"
                    >
                    <button
                        class="btn btn-primary"
                        @click="addSubtask()"
                        :disabled="!projectModal.newSubtaskTitle.trim()"
                    >+ Add</button>
                </div>

                <div class="modal-actions modal-actions-spaced">
                    <button class="btn btn-danger" @click="deleteActivity(projectModal.project); projectModal.open = false">
                        Delete project
                    </button>
                    <div class="modal-actions-group">
                        <button class="btn btn-ghost" @click="projectModal.open = false">Close</button>
                        <button
                            class="btn btn-success"
                            @click="completeProject()"
                            :disabled="projectModal.subtasks.filter(s => s.status !== 'done').length > 0"
                            :title="projectModal.subtasks.filter(s => s.status !== 'done').length > 0 ? 'Complete all subtasks first' : 'Complete project'"
                        >Complete</button>
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
                <div class="modal-activity-title modal-activity-title-spaced" x-text="completeModal.activity?.title"></div>

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

                <div class="modal-actions modal-actions-spaced">
                    <button class="btn btn-ghost" @click="completeModal.open = false">Cancel</button>
                    <button
                        class="btn btn-primary btn-success"
                        @click="completeActivity()"
                    >Complete</button>
                </div>
            </div>
        </div>
    </template>

    <!-- Modal: creating new category -->
    <template x-if="categoryModal.open">
        <div class="modal-overlay" @click.self="categoryModal.open = false">
            <div class="modal modal-narrow">
                <div class="modal-title">New Category</div>

                <div class="field">
                    <label>Name *</label>
                    <input type="text" x-model="categoryModal.name" placeholder="e.g. Work, Health, Study" @keydown.enter="createNewCategory()">
                </div>

                <div class="field">
                    <label>Color</label>
                    <input type="color" x-model="categoryModal.color" class="color-input">
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
                    is_project: false,
                    tags: [],
                },
                projectModal: {
                    open: false,
                    project: null,
                    subtasks: [],
                    loadingSubtasks: false,
                    newSubtaskTitle: '',
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
                    tags: [],
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
                    window.addEventListener('fab:captured', () => {
                        this.showToast('Captured!');
                    });
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
                        // Don't add subtasks that are not on board
                        if (item.parent_id && !item.is_on_board) {
                            return;
                        }

                        if (this.activities[item.status]) {
                            const exists = this.activities[item.status].find(a => a.id === item.id);
                            if (!exists) {
                                this.activities[item.status].push(item);
                            }
                        }

                    } else if (action === 'update') {
                        // If subtask is removed from board, delete it from activities
                        if (item.parent_id && !item.is_on_board) {
                            Object.keys(this.activities).forEach(status => {
                                this.activities[status] = this.activities[status].filter(a => a.id !== item.id);
                            });
                            return;
                        }

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

                        if (this.projectModal.open && item.parent_id === this.projectModal.project?.id) {
                            const idx = this.projectModal.subtasks.findIndex(s => s.id === item.id);
                            if (idx > -1) this.projectModal.subtasks[idx] = item;
                            this.updateProjectCounters(this.projectModal.project.id);
                        }


                    } else if (action === 'delete') {
                        Object.keys(this.activities).forEach(status => {
                            this.activities[status] = this.activities[status].filter(a => a.id !== item.id);
                        });
                    } else if (action === 'reorder') {
                        this.loadActivities(false);
                    }
                },

                async loadActivities(showSpinner = true) {
                    if (showSpinner) this.loading = true;
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
                        if (showSpinner) this.loading = false;
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

                            onChoose: (evt) => {
                                evt.item.setAttribute('x-ignore', '');
                            },
                            onUnchoose: (evt) => {
                                evt.item.removeAttribute('x-ignore');
                            },

                            onEnd: async (evt) => {
                                const activityId = parseInt(evt.item.dataset.id);
                                const oldStatus = evt.from.dataset.status;
                                const newStatus = evt.to.dataset.status;
                                const oldIndex = evt.oldIndex;
                                const newIndex = evt.newIndex;

                                evt.item.remove();
                                if (oldIndex !== undefined) {
                                    evt.from.insertBefore(evt.item, evt.from.children[oldIndex]);
                                }

                                if (oldStatus === newStatus && oldIndex === newIndex) return;

                                const taskIndex = this.activities[oldStatus].findIndex(a => a.id === activityId);
                                if (taskIndex > -1) {
                                    const [task] = this.activities[oldStatus].splice(taskIndex, 1);
                                    task.status = newStatus;
                                    this.activities[newStatus].splice(newIndex, 0, task);
                                }

                                const orderedIds = this.activities[newStatus].map(a => a.id);

                                try {
                                    await axios.post(`${API_BASE}/activities/reorder`, {
                                        activity_id: activityId,
                                        new_status: newStatus,
                                        ordered_ids: orderedIds
                                    }, this.getAuthConfig());
                                } catch (e) {
                                    this.showToast('Error moving activity');
                                    await this.loadActivities(false);
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
                        is_project: false,
                        tags: [],
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
                        tags: activity.tags ? [...activity.tags] : [],
                    };
                },

                async openProjectModal(project) {
                    this.projectModal = {
                        open: true,
                        project,
                        subtasks: [],
                        loadingSubtasks: true,
                        newSubtaskTitle: '',
                    };
                    try {
                        const res = await axios.get(
                            `${API_BASE}/activities/${project.id}/subtasks`,
                            this.getAuthConfig()
                        );
                        this.projectModal.subtasks = res.data;
                    } catch (e) {
                        this.showToast('Error loading subtasks');
                    } finally {
                        this.projectModal.loadingSubtasks = false;
                    }
                },

                async addSubtask() {
                    const title = this.projectModal.newSubtaskTitle.trim();
                    if (!title) return;

                    try {
                        const res = await axios.post(`${API_BASE}/activities`, {
                            title,
                            parent_id: this.projectModal.project.id,
                            status: 'backlog',
                        }, this.getAuthConfig());

                        this.projectModal.subtasks.push(res.data);
                        this.projectModal.newSubtaskTitle = '';
                        this.updateProjectCounters(this.projectModal.project.id);
                    } catch (e) {
                        this.showToast('Error adding subtask');
                    }
                },

                async takeToBoard(subtask) {
                    try {
                        const res = await axios.patch(
                            `${API_BASE}/activities/${subtask.id}`,
                            { is_on_board: true, status: 'backlog' },
                            this.getAuthConfig()
                        );
                        const idx = this.projectModal.subtasks.findIndex(s => s.id === subtask.id);
                        if (idx > -1) this.projectModal.subtasks[idx] = res.data;
                        await this.loadActivities(false);
                        this.showToast('Added to board');
                    } catch (e) {
                        this.showToast('Error');
                    }
                },

                async removeFromBoard(subtask) {
                    try {
                        const res = await axios.patch(
                            `${API_BASE}/activities/${subtask.id}`,
                            { is_on_board: false },
                            this.getAuthConfig()
                        );
                        const idx = this.projectModal.subtasks.findIndex(s => s.id === subtask.id);
                        if (idx > -1) this.projectModal.subtasks[idx] = res.data;
                        await this.loadActivities(false);
                        this.showToast('Removed from board');
                    } catch (e) {
                        this.showToast('Error');
                    }
                },

                async deleteSubtask(subtask) {
                    if (!confirm(`Delete "${subtask.title}"?`)) return;
                    try {
                        await axios.delete(`${API_BASE}/activities/${subtask.id}`, this.getAuthConfig());
                        this.projectModal.subtasks = this.projectModal.subtasks.filter(s => s.id !== subtask.id);
                        await this.loadActivities(false);
                        this.updateProjectCounters(this.projectModal.project.id);
                    } catch (e) {
                        this.showToast('Error deleting subtask');
                    }
                },

                async completeProject() {
                    if (!this.projectModal.project) return;
                    this.projectModal.open = false;
                    this.openCompleteModal(this.projectModal.project);
                },

                updateProjectCounters(projectId) {
                    Object.keys(this.activities).forEach(status => {
                        const project = this.activities[status].find(a => a.id === projectId);
                        if (project) {
                            project.subtasks_total = this.projectModal.subtasks.length;
                            project.subtasks_done = this.projectModal.subtasks.filter(s => s.status === 'done').length;
                        }
                    });
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

                async createActivity() {
                    if (!this.modal.title.trim()) return;
                    try {
                        const res = await axios.post(`${API_BASE}/activities`, {
                            title: this.modal.title.trim(),
                            description: this.modal.description || null,
                            category_id: this.modal.category_id || null,
                            deadline: this.getDeadline(this.modal.deadlineDate, this.modal.deadlineTime),
                            status: this.modal.status,
                            is_project: this.modal.is_project,
                            tags: this.modal.tags,
                        }, this.getAuthConfig());

                        this.modal.open = false;

                        const newActivity = res.data;
                        if (!this.activities[newActivity.status].find(a => a.id === newActivity.id)) {
                            this.activities[newActivity.status].push(newActivity);
                        }

                        const orderedIds = this.activities[newActivity.status].map(a => a.id);
                        await axios.post(`${API_BASE}/activities/reorder`, {
                            new_status: newActivity.status,
                            ordered_ids: orderedIds
                        }, this.getAuthConfig());

                        await this.loadActivities(false);
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
                            tags: this.editModal.tags,
                        }, this.getAuthConfig());
                        this.editModal.open = false;
                        await this.loadActivities(false);
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
                        await this.loadActivities(false);
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
                        await this.loadActivities(false);
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

@include('components.fab')
</body>
</html>

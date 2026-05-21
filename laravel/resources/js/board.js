import { modalMethods } from './board/modals.js';
import { activityMethods } from './board/activities.js';
import { projectMethods } from './board/projects.js';

export function board() {
    const API_BASE = window.API_BASE;

    return {
        // ─── State ───────────────────────────────────────────
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
            title: '',
            description: '',
            category_id: '',
            deadlineDate: '',
            deadlineTime: '',
            tags: [],
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
        contextMenu: { open: false, x: 0, y: 0, activity: null },
        ws: null,

        // ─── Modules ─────────────────────────────────────────
        ...modalMethods(),
        ...activityMethods(API_BASE),
        ...projectMethods(API_BASE),

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
            await this.loadActivities();
            await this.loadCategories();
            this.$nextTick(() => this.initSortable());
            this.initWs(token);
            window.addEventListener('fab:captured', () => {
                this.showToast('Captured!');
            });
        },

        // ─── Context menu ─────────────────────────────────────
        openContextMenu(e, activity) {
            const menuW = 180, menuH = 120;
            const x = e.clientX + menuW > window.innerWidth  ? e.clientX - menuW : e.clientX;
            const y = e.clientY + menuH > window.innerHeight ? e.clientY - menuH : e.clientY;
            this.contextMenu = { open: true, x, y, activity };
        },

        closeContextMenu() {
            this.contextMenu.open = false;
        },

        contextMenuDelete() {
            const a = this.contextMenu.activity;
            this.closeContextMenu();
            this.deleteActivity(a);
        },

        async contextMenuMoveToProject() {
            const a = this.contextMenu.activity;
            this.closeContextMenu();
            try {
                await axios.patch(`${API_BASE}/activities/${a.id}`, { is_on_board: false }, this.getAuthConfig());
                await this.loadActivities(false);
                this.showToast('Moved to project');
            } catch (e) {
                this.showToast('Error moving to project');
            }
        },

        // ─── WebSocket ─────────────────────────────────────────
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
            const action = payload.action;
            const item = payload.data;

            if (action === 'create') {
                if (item.parent_id && !item.is_on_board) return;
                if (this.activities[item.status]) {
                    const exists = this.activities[item.status].find(a => a.id === item.id);
                    if (!exists) this.activities[item.status].push(item);
                }

            } else if (action === 'update') {
                if (item.parent_id && !item.is_on_board) {
                    Object.keys(this.activities).forEach(s => {
                        this.activities[s] = this.activities[s].filter(a => a.id !== item.id);
                    });
                    return;
                }

                let found = false;
                const targetCol = this.activities[item.status];
                if (targetCol) {
                    const idx = targetCol.findIndex(a => a.id === item.id);
                    if (idx > -1) { targetCol[idx] = item; found = true; }
                }
                if (!found) {
                    Object.keys(this.activities).forEach(s => {
                        this.activities[s] = this.activities[s].filter(a => a.id !== item.id);
                    });
                    if (this.activities[item.status]) this.activities[item.status].push(item);
                }

                if (this.projectModal.open && item.parent_id === this.projectModal.project?.id) {
                    const idx = this.projectModal.subtasks.findIndex(s => s.id === item.id);
                    if (idx > -1) this.projectModal.subtasks[idx] = item;
                    this.updateProjectCounters(this.projectModal.project.id);
                }

            } else if (action === 'delete') {
                Object.keys(this.activities).forEach(s => {
                    this.activities[s] = this.activities[s].filter(a => a.id !== item.id);
                });
                if (this.projectModal.open && item.parent_id === this.projectModal.project?.id) {
                    this.projectModal.subtasks = this.projectModal.subtasks.filter(s => s.id !== item.id);
                    this.updateProjectCounters(this.projectModal.project.id);
                }

            } else if (action === 'reorder') {
                this.loadActivities(false);
            }
        },

        // ─── Data loading ─────────────────────────────────────
        async loadActivities(showLoading = true) {
            if (showLoading) this.loading = true;
            try {
                const res = await axios.get(`${API_BASE}/activities`, this.getAuthConfig());
                this.activities = {
                    backlog:       res.data.filter(a => a.status === 'backlog'),
                    today:         res.data.filter(a => a.status === 'today'),
                    in_process:    res.data.filter(a => a.status === 'in_process'),
                    on_reflection: res.data.filter(a => a.status === 'on_reflection'),
                };
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

        // ─── Sortable ─────────────────────────────────────────
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
                    fallbackTolerance: 5,

                    onStart: (evt) => {
                        document.body.classList.add('is-dragging');
                        window.Alpine?.deferMutations();
                    },

                    onEnd: async (evt) => {
                        window.Alpine?.flushAndStopDeferringMutations();
                        document.body.classList.remove('is-dragging');
                        document.body.classList.add('no-hover');
                        setTimeout(() => document.body.classList.remove('no-hover'), 100);

                        const activityId = parseInt(evt.item.dataset.id);
                        const oldStatus = evt.from.dataset.status;
                        const newStatus = evt.to.dataset.status;
                        const oldIndex = evt.oldIndex;
                        const newIndex = evt.newIndex;

                        if (oldStatus === newStatus && oldIndex === newIndex) return;

                        evt.item.remove();
                        if (evt.from.children[oldIndex]) {
                            evt.from.insertBefore(evt.item, evt.from.children[oldIndex]);
                        } else {
                            evt.from.appendChild(evt.item);
                        }

                        const taskIndex = this.activities[oldStatus].findIndex(a => a.id === activityId);
                        if (taskIndex > -1) {
                            const [task] = this.activities[oldStatus].splice(taskIndex, 1);
                            task.status = newStatus;
                            this.activities[newStatus].splice(newIndex, 0, task);
                        }

                        try {
                            await axios.post(`${API_BASE}/activities/reorder`, {
                                activity_id: activityId,
                                new_status: newStatus,
                                ordered_ids: this.activities[newStatus].map(a => a.id),
                            }, this.getAuthConfig());
                        } catch (e) {
                            this.showToast('Error moving activity');
                            await this.loadActivities(false);
                        }
                    },
                });
            });
        },

        // ─── Utilities ────────────────────────────────────────
        columnLabel(status) {
            return this.columns.find(c => c.status === status)?.label ?? status;
        },

        formatDate(dt, showYear = false) {
            if (!dt) return '';
            const date = new Date(dt);
            if (isNaN(date.getTime())) return dt;
            const opts = { day: 'numeric', month: 'short', ...(showYear ? { year: 'numeric' } : {}) };
            const isDateOnly = dt.length === 10 || (date.getHours() === 0 && date.getMinutes() === 0 && date.getSeconds() === 0);
            if (isDateOnly) return date.toLocaleDateString('en-US', opts);
            return date.toLocaleString('en-US', { ...opts, hour: '2-digit', minute: '2-digit' });
        },

        deadlineStatus(dt) {
            if (!dt) return '';
            const now = new Date();
            const todayMidnight = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const d = new Date(dt);
            const dMidnight = new Date(d.getFullYear(), d.getMonth(), d.getDate());
            if (dMidnight < todayMidnight) return 'overdue';
            if (dMidnight.getTime() === todayMidnight.getTime()) return 'today';
            const soonMidnight = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 4);
            if (dMidnight < soonMidnight) return 'soon';
            return '';
        },

        showToast(message) {
            this.toast = { show: true, message };
            setTimeout(() => this.toast.show = false, 2500);
        },
    };
}

import { modalMethods } from './board/modals.js';
import { activityMethods } from './board/activities.js';
import { projectMethods } from './board/projects.js';
import { getAuthConfig, requireAuthOrRedirect, initWebSocket } from './shared/api.js';
import { formatDate, formatMinutes } from './shared/format.js';
import { deadlineStatus } from './shared/deadline.js';

const TOAST_MS = 2500;
const NO_HOVER_MS = 100;

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
        logTimeModal: {
            open: false,
            activity: null,
            minutes: '',
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

        // ─── Shared helpers ───────────────────────────────────
        getAuthConfig,
        formatDate,
        formatMinutes,
        deadlineStatus,

        // ─── Init ─────────────────────────────────────────────
        async init() {
            const token = requireAuthOrRedirect();
            if (!token) return;
            await this.loadActivities();
            await this.loadCategories();
            this.$nextTick(() => this.initSortable());
            this.ws = initWebSocket(API_BASE, token, (payload) => this.handleRemoteUpdate(payload));
            window.addEventListener('fab:captured', () => this.showToast('Captured!'));
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

        // ─── WebSocket dispatch ───────────────────────────────
        handleRemoteUpdate(payload) {
            const { action, data: item } = payload;
            switch (action) {
                case 'create':  return this.handleWsCreate(item);
                case 'update':  return this.handleWsUpdate(item);
                case 'delete':  return this.handleWsDelete(item);
                case 'reorder': return this.loadActivities(false);
            }
        },

        handleWsCreate(item) {
            // Subtasks not on board are not shown
            if (item.parent_id && !item.is_on_board) return;
            const col = this.activities[item.status];
            if (!col) return;
            if (!col.find(a => a.id === item.id)) col.push(item);
        },

        handleWsUpdate(item) {
            // Subtask removed from board → drop from all columns
            if (item.parent_id && !item.is_on_board) {
                this.removeFromAllColumns(item.id);
                return;
            }

            const targetCol = this.activities[item.status];
            const idx = targetCol ? targetCol.findIndex(a => a.id === item.id) : -1;

            if (idx > -1) {
                targetCol[idx] = item;
            } else {
                // Status changed — remove from old column, add to new
                this.removeFromAllColumns(item.id);
                if (targetCol) targetCol.push(item);
            }

            this.syncProjectModalSubtask(item);
        },

        handleWsDelete(item) {
            this.removeFromAllColumns(item.id);
            if (this.projectModal.open && item.parent_id === this.projectModal.project?.id) {
                this.projectModal.subtasks = this.projectModal.subtasks.filter(s => s.id !== item.id);
                this.updateProjectCounters(this.projectModal.project.id);
            }
        },

        removeFromAllColumns(id) {
            Object.keys(this.activities).forEach(s => {
                this.activities[s] = this.activities[s].filter(a => a.id !== id);
            });
        },

        syncProjectModalSubtask(item) {
            if (!this.projectModal.open || item.parent_id !== this.projectModal.project?.id) return;
            const idx = this.projectModal.subtasks.findIndex(s => s.id === item.id);
            if (idx > -1) this.projectModal.subtasks[idx] = item;
            this.updateProjectCounters(this.projectModal.project.id);
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
                    onStart: () => {
                        document.body.classList.add('is-dragging');
                        window.Alpine?.deferMutations();
                    },
                    onEnd: (evt) => this.onSortEnd(evt),
                });
            });
        },

        async onSortEnd(evt) {
            window.Alpine?.flushAndStopDeferringMutations();
            document.body.classList.remove('is-dragging');
            document.body.classList.add('no-hover');
            setTimeout(() => document.body.classList.remove('no-hover'), NO_HOVER_MS);

            const activityId = parseInt(evt.item.dataset.id);
            const oldStatus = evt.from.dataset.status;
            const newStatus = evt.to.dataset.status;
            const oldIndex = evt.oldIndex;
            const newIndex = evt.newIndex;

            if (oldStatus === newStatus && oldIndex === newIndex) return;

            // Return DOM to original position; Alpine will re-render from state
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

        // ─── Utilities ────────────────────────────────────────
        columnLabel(status) {
            return this.columns.find(c => c.status === status)?.label ?? status;
        },

        showToast(message) {
            this.toast = { show: true, message };
            setTimeout(() => this.toast.show = false, TOAST_MS);
        },
    };
}

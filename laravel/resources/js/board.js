export function board() {
    const API_BASE = window.API_BASE;

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

                if (this.projectModal.open && item.parent_id === this.projectModal.project?.id) {
                    this.projectModal.subtasks = this.projectModal.subtasks.filter(s => s.id !== item.id);
                    this.updateProjectCounters(this.projectModal.project.id);
                }
            }
        },

        async loadActivities(showLoading = true) {
            if (showLoading) this.loading = true;
            try {
                const res = await axios.get(`${API_BASE}/activities`, this.getAuthConfig());
                this.activities = {
                    backlog: res.data.filter(a => a.status === 'backlog'),
                    today: res.data.filter(a => a.status === 'today'),
                    in_process: res.data.filter(a => a.status === 'in_process'),
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
                    delayOnTouchOnly: false,
                    fallbackTolerance: 5,

                    onStart: (evt) => {
                        document.body.classList.add('is-dragging');
                    },

                    onEnd: async (evt) => {
                        document.body.classList.remove('is-dragging');

                        // Temporarily disable hover on all cards to prevent unwanted highlighting
                        document.body.classList.add('no-hover');
                        setTimeout(() => {
                            document.body.classList.remove('no-hover');
                        }, 100);

                        const activityId = parseInt(evt.item.dataset.id);
                        const oldStatus = evt.from.dataset.status;
                        const newStatus = evt.to.dataset.status;
                        const oldIndex = evt.oldIndex;
                        const newIndex = evt.newIndex;

                        // If nothing changed, just return
                        if (oldStatus === newStatus && oldIndex === newIndex) {
                            return;
                        }

                        // Revert the DOM change first
                        evt.item.remove();
                        if (oldIndex !== undefined && evt.from.children[oldIndex]) {
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
            const dl = project.deadline ? project.deadline.split('T') : ['', ''];
            const timeRaw = dl[1] ? dl[1].slice(0, 5) : '';
            const timeVal = (timeRaw === '00:00') ? '' : timeRaw;

            this.projectModal = {
                open: true,
                project,
                subtasks: [],
                loadingSubtasks: true,
                newSubtaskTitle: '',
                title: project.title,
                description: project.description || '',
                category_id: project.category_id || '',
                deadlineDate: dl[0] || '',
                deadlineTime: timeVal,
                tags: project.tags ? [...project.tags] : [],
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

        async toggleSubtaskOnBoard(subtask) {
            try {
                await axios.patch(
                    `${API_BASE}/activities/${subtask.id}`,
                    { is_on_board: !subtask.is_on_board },
                    this.getAuthConfig()
                );
                subtask.is_on_board = !subtask.is_on_board;
                this.showToast(subtask.is_on_board ? 'Added to board' : 'Removed from board');
            } catch (e) {
                this.showToast('Error updating subtask');
            }
        },

        async completeSubtask(subtask) {
            try {
                await axios.patch(
                    `${API_BASE}/activities/${subtask.id}`,
                    { status: 'done' },
                    this.getAuthConfig()
                );
                subtask.status = 'done';
                this.updateProjectCounters(this.projectModal.project.id);
                this.showToast('Subtask completed');
            } catch (e) {
                this.showToast('Error completing subtask');
            }
        },

        async deleteSubtask(subtask) {
            if (!confirm(`Delete "${subtask.title}"?`)) return;
            try {
                await axios.delete(`${API_BASE}/activities/${subtask.id}`, this.getAuthConfig());
                this.projectModal.subtasks = this.projectModal.subtasks.filter(s => s.id !== subtask.id);
                this.updateProjectCounters(this.projectModal.project.id);
                this.showToast('Subtask deleted');
            } catch (e) {
                this.showToast('Error deleting subtask');
            }
        },

        updateProjectCounters(projectId) {
            const subtasks = this.projectModal.subtasks;
            const total = subtasks.length;
            const done = subtasks.filter(s => s.status === 'done').length;

            Object.keys(this.activities).forEach(status => {
                const project = this.activities[status].find(a => a.id === projectId);
                if (project) {
                    project.subtasks_total = total;
                    project.subtasks_done = done;
                }
            });

            if (this.projectModal.project && this.projectModal.project.id === projectId) {
                this.projectModal.project.subtasks_total = total;
                this.projectModal.project.subtasks_done = done;
            }
        },

        openCompleteModal(activity) {
            this.completeModal = {
                open: true,
                activity,
                reflection: activity.reflection_text || '',
                time_spent: activity.time_spent_minutes || '',
            };
        },

        selectCategory(categoryId) {
            if (this.modal.open) {
                this.modal.category_id = this.modal.category_id === categoryId ? '' : categoryId;
            } else if (this.editModal.open) {
                this.editModal.category_id = this.editModal.category_id === categoryId ? '' : categoryId;
            } else if (this.projectModal.open) {
                this.projectModal.category_id = this.projectModal.category_id === categoryId ? '' : categoryId;
            }
        },

        async createActivity() {
            const title = this.modal.title.trim();
            if (!title) return;

            const deadline = this.modal.deadlineDate
                ? `${this.modal.deadlineDate}T${this.modal.deadlineTime || '00:00'}:00`
                : null;

            try {
                await axios.post(`${API_BASE}/activities`, {
                    title,
                    description: this.modal.description || null,
                    category_id: this.modal.category_id || null,
                    status: this.modal.status,
                    deadline,
                    is_project: this.modal.is_project,
                    tags: this.modal.tags,
                }, this.getAuthConfig());
                this.modal.open = false;
                await this.loadActivities(false);
                this.showToast('Activity created');
            } catch (e) {
                this.showToast('Error creating activity');
            }
        },

        async updateActivity() {
            const title = this.editModal.title.trim();
            if (!title) return;

            const deadline = this.editModal.deadlineDate
                ? `${this.editModal.deadlineDate}T${this.editModal.deadlineTime || '00:00'}:00`
                : null;

            try {
                await axios.patch(`${API_BASE}/activities/${this.editModal.activity.id}`, {
                    title,
                    description: this.editModal.description || null,
                    category_id: this.editModal.category_id || null,
                    deadline,
                    reflection_text: this.editModal.reflection_text || null,
                    time_spent_minutes: parseInt(this.editModal.time_spent_minutes) || null,
                    tags: this.editModal.tags,
                }, this.getAuthConfig());
                this.editModal.open = false;
                await this.loadActivities(false);
                this.showToast('Activity updated');
            } catch (e) {
                this.showToast('Error updating activity');
            }
        },

        async updateProject() {
            const title = this.projectModal.title.trim();
            if (!title) return;

            const deadline = this.projectModal.deadlineDate
                ? `${this.projectModal.deadlineDate}T${this.projectModal.deadlineTime || '00:00'}:00`
                : null;

            try {
                await axios.patch(`${API_BASE}/activities/${this.projectModal.project.id}`, {
                    title,
                    description: this.projectModal.description || null,
                    category_id: this.projectModal.category_id || null,
                    deadline,
                    tags: this.projectModal.tags,
                }, this.getAuthConfig());
                this.projectModal.project.title = title;
                this.projectModal.project.description = this.projectModal.description;
                this.projectModal.project.category_id = this.projectModal.category_id;
                this.projectModal.project.deadline = deadline;
                this.projectModal.project.tags = this.projectModal.tags;
                await this.loadActivities(false);
                this.showToast('Project updated');
            } catch (e) {
                this.showToast('Error updating project');
            }
        },

        async completeActivity() {
            try {
                await axios.patch(`${API_BASE}/activities/${this.completeModal.activity.id}`, {
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

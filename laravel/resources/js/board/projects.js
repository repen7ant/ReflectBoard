import { parseDeadline, buildDeadline } from '../shared/deadline.js';

export function projectMethods(API_BASE) {
    return {
        async openProjectModal(project) {
            const { date, time } = parseDeadline(project.deadline);

            this.projectModal = {
                open: true,
                project,
                subtasks: [],
                loadingSubtasks: true,
                newSubtaskTitle: '',
                title: project.title,
                description: project.description || '',
                category_id: project.category_id || '',
                deadlineDate: date,
                deadlineTime: time,
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
                const res = await axios.patch(
                    `${API_BASE}/activities/${subtask.id}`,
                    { is_on_board: !subtask.is_on_board },
                    this.getAuthConfig()
                );
                const idx = this.projectModal.subtasks.findIndex(s => s.id === subtask.id);
                if (idx > -1) this.projectModal.subtasks[idx] = res.data;
                await this.loadActivities(false);
                this.showToast(res.data.is_on_board ? 'Added to board' : 'Removed from board');
            } catch (e) {
                this.showToast('Error updating subtask');
            }
        },

        async completeSubtask(subtask) {
            try {
                const res = await axios.patch(
                    `${API_BASE}/activities/${subtask.id}`,
                    { status: 'done' },
                    this.getAuthConfig()
                );
                const idx = this.projectModal.subtasks.findIndex(s => s.id === subtask.id);
                if (idx > -1) this.projectModal.subtasks[idx] = res.data;
                await this.loadActivities(false);
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

        async updateProject() {
            const title = this.projectModal.title.trim();
            if (!title) return;

            try {
                await axios.patch(`${API_BASE}/activities/${this.projectModal.project.id}`, {
                    title,
                    description: this.projectModal.description || null,
                    category_id: this.projectModal.category_id || null,
                    deadline: buildDeadline(this.projectModal.deadlineDate, this.projectModal.deadlineTime),
                    tags: this.projectModal.tags,
                }, this.getAuthConfig());
                this.projectModal.open = false;
                await this.loadActivities(false);
                this.showToast('Project updated');
            } catch (e) {
                this.showToast('Error updating project');
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

            if (this.projectModal.project?.id === projectId) {
                this.projectModal.project.subtasks_total = total;
                this.projectModal.project.subtasks_done = done;
            }
        },
    };
}

import { buildDeadline } from '../shared/deadline.js';

export function activityMethods(API_BASE) {
    return {
        async createActivity() {
            const title = this.modal.title.trim();
            if (!title) return;

            try {
                await axios.post(`${API_BASE}/activities`, {
                    title,
                    description: this.modal.description || null,
                    category_id: this.modal.category_id || null,
                    status: this.modal.status,
                    deadline: buildDeadline(this.modal.deadlineDate, this.modal.deadlineTime),
                    is_project: this.modal.is_project,
                    is_productive: this.modal.is_productive,
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

            try {
                await axios.patch(`${API_BASE}/activities/${this.editModal.activity.id}`, {
                    title,
                    description: this.editModal.description || null,
                    category_id: this.editModal.category_id || null,
                    deadline: buildDeadline(this.editModal.deadlineDate, this.editModal.deadlineTime),
                    reflection_text: this.editModal.reflection_text || null,
                    time_spent_minutes: parseInt(this.editModal.time_spent_minutes) || null,
                    tags: this.editModal.tags,
                    is_productive: this.editModal.is_productive,
                }, this.getAuthConfig());
                this.editModal.open = false;
                await this.loadActivities(false);
                this.showToast('Activity updated');
            } catch (e) {
                this.showToast('Error updating activity');
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

        async logTime() {
            const minutes = parseInt(this.logTimeModal.minutes);
            if (!minutes || minutes < 1) return;
            try {
                await axios.post(
                    `${API_BASE}/activities/${this.logTimeModal.activity.id}/log-time`,
                    { minutes },
                    this.getAuthConfig(),
                );
                this.logTimeModal.open = false;
                await this.loadActivities(false);
                this.showToast(`+${minutes} min logged`);
            } catch (e) {
                this.showToast('Error logging time');
            }
        },

        async deleteActivity(activity) {
            if (!confirm(`Delete "${activity.title}"?`)) return;
            try {
                await axios.delete(`${API_BASE}/activities/${activity.id}`, this.getAuthConfig());
                this.editModal.open = false;
                this.projectModal.open = false;
                await this.loadActivities(false);
                this.showToast('Activity deleted');
            } catch (e) {
                this.showToast('Error deleting activity');
            }
        },

        async createCategory() {
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

        async deleteCategory(categoryId) {
            if (!confirm('Delete this category? It will be removed from all tasks.')) return;
            try {
                await axios.delete(`${API_BASE}/categories/${categoryId}`, this.getAuthConfig());
                this.categories = this.categories.filter(c => c.id !== categoryId);
                if (this.modal.category_id === categoryId) this.modal.category_id = '';
                if (this.editModal.category_id === categoryId) this.editModal.category_id = '';
                if (this.projectModal.category_id === categoryId) this.projectModal.category_id = '';
                this.showToast('Category deleted');
            } catch (e) {
                this.showToast('Error deleting category');
            }
        },
    };
}

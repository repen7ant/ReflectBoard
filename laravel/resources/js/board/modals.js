import { parseDeadline } from '../shared/deadline.js';

export function modalMethods() {
    return {
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
                is_productive: true,
                tags: [],
            };
        },

        openEditModal(activity) {
            const { date, time } = parseDeadline(activity.deadline);

            this.editModal = {
                open: true,
                activity,
                title: activity.title,
                description: activity.description || '',
                category_id: activity.category_id || '',
                deadlineDate: date,
                deadlineTime: time,
                reflection_text: activity.reflection_text || '',
                time_spent_minutes: activity.time_spent_minutes || '',
                tags: activity.tags ? [...activity.tags] : [],
                is_productive: activity.is_productive !== false,
            };
        },

        openCompleteModal(activity) {
            if (activity.is_project && activity.subtasks_total > 0 && activity.subtasks_done < activity.subtasks_total) {
                this.showToast(`Finish subtasks first (${activity.subtasks_done}/${activity.subtasks_total} done)`);
                return;
            }
            this.completeModal = {
                open: true,
                activity,
                reflection: activity.reflection_text || '',
                time_spent: activity.time_spent_minutes || '',
            };
        },

        openCreateCategoryModal() {
            this.categoryModal = {
                open: true,
                name: '',
                color: '#957fb8',
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
    };
}

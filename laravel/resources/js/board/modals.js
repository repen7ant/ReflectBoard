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
                is_productive: activity.is_productive !== false,
            };
        },

        openCompleteModal(activity) {
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

import { getAuthConfig } from './shared/api.js';

export function fab() {
    const API_BASE = window.API_BASE;

    return {
        open: false,
        submitting: false,
        categories: [],
        form: emptyForm(),

        getAuthConfig,

        async init() {
            await this.loadCategories();
            window.addEventListener('categories:changed', () => this.loadCategories());
        },

        async loadCategories() {
            try {
                const res = await axios.get(`${API_BASE}/categories`, this.getAuthConfig());
                this.categories = res.data;
            } catch (e) {
                console.error('FAB: failed to load categories', e);
            }
        },

        close() {
            this.open = false;
            this.form = emptyForm();
        },

        async submit() {
            if (!this.form.title.trim() || this.submitting) return;

            this.submitting = true;
            try {
                await axios.post(`${API_BASE}/activities`, {
                    title: this.form.title.trim(),
                    description: this.form.description || null,
                    time_spent_minutes: this.form.time_spent_minutes ? parseInt(this.form.time_spent_minutes) : null,
                    category_id: this.form.category_id || null,
                    tags: this.form.tags,
                    reflection_text: this.form.reflection_text || null,
                    is_productive: this.form.is_productive,
                    is_quick_capture: true,
                    status: 'done',
                }, this.getAuthConfig());

                this.close();
                window.dispatchEvent(new CustomEvent('fab:captured'));
            } catch (e) {
                console.error('FAB: failed to submit', e);
                alert('Failed to save. Please try again.');
            } finally {
                this.submitting = false;
            }
        },
    };
}

function emptyForm() {
    return {
        title: '',
        description: '',
        time_spent_minutes: '',
        category_id: null,
        tags: [],
        reflection_text: '',
        is_productive: false,
    };
}

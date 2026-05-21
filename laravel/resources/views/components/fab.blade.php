<!-- Floating Action Button: Quick Capture -->
<div x-data="fab()" x-init="init()">

    <!-- FAB Button -->
    <button
        class="fab-btn"
        @click="open = true"
        title="Quick capture"
        aria-label="Quick capture"
    >+</button>

    <!-- Modal -->
    <template x-if="open">
        <div class="modal-overlay" @mousedown.self="$el._md=true" @click.self="$el._md&&close();$el._md=false">
            <div class="modal">
                <button class="modal-close" @click="close()">&times;</button>
                <div class="modal-title">Quick Capture</div>

                <div class="field">
                    <label>What did you do? *</label>
                    <input
                        type="text"
                        x-model="form.title"
                        placeholder="e.g. watched YouTube video"
                        @keydown.enter="submit()"
                        x-ref="titleInput"
                    >
                </div>

                <div class="field">
                    <label>Description</label>
                    <textarea x-model="form.description" rows="2" placeholder="Any details..."></textarea>
                </div>

                <div class="field">
                    <label>Time spent in minutes</label>
                    <input type="number" x-model="form.time_spent_minutes" placeholder="e.g. 40" min="0">
                </div>

                <div class="field">
                    <label>Category</label>
                    <div class="category-grid">
                        <template x-for="cat in categories" :key="cat.id">
                            <button
                                type="button"
                                @click="form.category_id = form.category_id == cat.id ? null : cat.id"
                                :class="{ 'selected': form.category_id == cat.id }"
                                class="category-btn"
                            >
                                <div class="category-dot" :style="'background:' + cat.color"></div>
                                <span class="flex-1 text-left" x-text="cat.name"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="field" x-data="{ newTag: '' }">
                    <label>Tags</label>
                    <div class="tag-input-wrapper">
                        <template x-for="(tag, index) in form.tags" :key="index">
                            <span class="tag-pill">
                                <span x-text="'#' + tag"></span>
                                <button type="button" @click="form.tags.splice(index, 1)" class="tag-pill-remove">&times;</button>
                            </span>
                        </template>
                        <input
                            type="text"
                            x-model="newTag"
                            :placeholder="form.tags.length === 0 ? 'Add tag & press space...' : ''"
                            @keydown.space.prevent="if(newTag.trim()){ form.tags.push(newTag.replace(/^#/, '').trim()); newTag = ''; }"
                            @keydown.enter.prevent="if(newTag.trim()){ form.tags.push(newTag.replace(/^#/, '').trim()); newTag = ''; }"
                            @keydown.backspace="if(newTag === '' && form.tags.length > 0){ form.tags.pop(); }"
                            class="tag-input"
                        >
                    </div>
                </div>

                <div class="field">
                    <label style="display:flex; align-items:center; gap:0.5rem;">
                        Productive
                        <input
                            type="checkbox"
                            x-model="form.is_productive"
                            style="width:1.1rem; height:1.1rem; accent-color:var(--accent); cursor:pointer;"
                        >
                    </label>
                </div>

                <div class="field">
                    <label>Reflection</label>
                    <textarea x-model="form.reflection_text" rows="3" placeholder="What do you think about it?"></textarea>
                </div>

                <div class="modal-actions">
                    <button
                        class="btn btn-primary"
                        @click="submit()"
                        :disabled="!form.title.trim() || submitting"
                    >
                        <span x-show="!submitting">Capture</span>
                        <span x-show="submitting">Saving...</span>
                    </button>
                </div>
            </div>
        </div>
    </template>

</div>

<script>
const FAB_API_BASE = typeof API_BASE !== 'undefined' ? API_BASE : '{{ config("services.api_base.url") }}';

function fab() {
    return {
        open: false,
        submitting: false,
        categories: [],
        form: {
            title: '',
            description: '',
            time_spent_minutes: '',
            category_id: null,
            tags: [],
            reflection_text: '',
            is_productive: true,
        },

        getAuthConfig() {
            const token = document.querySelector('meta[name="api-token"]')?.content;
            return { headers: { Authorization: `Bearer ${token}` } };
        },

        async init() {
            try {
                const res = await axios.get(`${FAB_API_BASE}/categories`, this.getAuthConfig());
                this.categories = res.data;
            } catch (e) {
                console.error('FAB: failed to load categories', e);
            }
        },

        close() {
            this.open = false;
            this.resetForm();
        },

        resetForm() {
            this.form = {
                title: '',
                description: '',
                time_spent_minutes: '',
                category_id: null,
                tags: [],
                reflection_text: '',
                is_productive: true,
            };
        },

        async submit() {
            if (!this.form.title.trim() || this.submitting) return;

            this.submitting = true;
            try {
                await axios.post(`${FAB_API_BASE}/activities`, {
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
</script>

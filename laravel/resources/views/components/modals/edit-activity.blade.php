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

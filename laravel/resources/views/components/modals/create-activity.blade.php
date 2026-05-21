<template x-if="modal.open">
    <div class="modal-overlay" @click.self="modal.open = false">
        <div class="modal">
            <div class="modal-title">New Task — <span x-text="columnLabel(modal.status)"></span></div>

            <div class="field">
                <label>Title *</label>
                <input type="text" x-model="modal.title" placeholder="What needs to be done?" @keydown.enter="createActivity()">
            </div>

            <div class="field">
                <label>Description</label>
                <textarea x-model="modal.description" rows="2" placeholder="Details"></textarea>
            </div>

            <div class="field">
                <label>Category</label>
                <div class="category-grid">
                    <template x-for="cat in categories" :key="cat.id">
                        <button
                            type="button"
                            @click="selectCategory(cat.id)"
                            :class="{ 'selected': modal.category_id == cat.id }"
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

                    <template x-for="(tag, index) in modal.tags" :key="index">
                        <span class="tag-pill">
                            <span x-text="'#' + tag"></span>
                            <button type="button" @click="modal.tags.splice(index, 1)" class="tag-pill-remove">&times;</button>
                        </span>
                    </template>

                    <input
                        type="text"
                        x-model="newTag"
                        :placeholder="modal.tags.length === 0 ? 'Add tag & press space...' : ''"
                        @keydown.space.prevent="if(newTag.trim()){ modal.tags.push(newTag.replace(/^#/, '').trim()); newTag = ''; }"
                        @keydown.enter.prevent="if(newTag.trim()){ modal.tags.push(newTag.replace(/^#/, '').trim()); newTag = ''; }"
                        @keydown.backspace="if(newTag === '' && modal.tags.length > 0){ modal.tags.pop(); }"
                        class="tag-input"
                    >
                </div>
            </div>

            <div class="field" style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                <label style="display:flex; align-items:center; gap:0.5rem;">
                    Project
                    <input
                        type="checkbox"
                        x-model="modal.is_project"
                        style="width:1.1rem; height:1.1rem; accent-color:var(--accent); cursor:pointer;"
                    >
                </label>
                <label style="display:flex; align-items:center; gap:0.5rem;">
                    Productive
                    <input
                        type="checkbox"
                        x-model="modal.is_productive"
                        style="width:1.1rem; height:1.1rem; accent-color:var(--accent); cursor:pointer;"
                    >
                </label>
            </div>

            <div class="field">
                <label>Deadline</label>
                <div class="deadline-inputs">
                    <input type="date" x-model="modal.deadlineDate" class="deadline-date">
                    <input type="time" x-model="modal.deadlineTime" class="deadline-time">
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn btn-ghost" @click="modal.open = false">Cancel</button>
                <button class="btn btn-primary" @click="createActivity()" :disabled="!modal.title.trim()">Create</button>
            </div>
        </div>
    </div>
</template>

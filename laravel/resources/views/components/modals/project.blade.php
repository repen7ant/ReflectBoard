<template x-if="projectModal.open">
    <div class="modal-overlay" @click.self="projectModal.open = false">
        <div class="modal modal-wide">
            <button class="modal-close" @click="projectModal.open = false">&times;</button>

            <div class="modal-header modal-header-center">
                <div class="modal-header-content">
                    <div class="detail-label">Created</div>
                    <div class="detail-value" x-text="formatDate(projectModal.project?.created_at, true)"></div>
                </div>
            </div>

            <div class="field">
                <label>Title</label>
                <input type="text" x-model="projectModal.title">
            </div>

            <div class="field">
                <label>Description</label>
                <textarea x-model="projectModal.description" rows="2"></textarea>
            </div>

            <div class="field">
                <label>Category</label>
                <div class="category-grid">
                    <template x-for="cat in categories" :key="cat.id">
                        <button
                            type="button"
                            @click="projectModal.category_id = cat.id"
                            :class="{ 'selected': projectModal.category_id == cat.id }"
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

                    <template x-for="(tag, index) in projectModal.tags" :key="index">
                        <span class="tag-pill">
                            <span x-text="'#' + tag"></span>
                            <button type="button" @click="projectModal.tags.splice(index, 1)" class="tag-pill-remove">&times;</button>
                        </span>
                    </template>

                    <input
                        type="text"
                        x-model="newTag"
                        :placeholder="projectModal.tags.length === 0 ? 'Add tag & press space...' : ''"
                        @keydown.space.prevent="if(newTag.trim()){ projectModal.tags.push(newTag.replace(/^#/, '').trim()); newTag = ''; }"
                        @keydown.enter.prevent="if(newTag.trim()){ projectModal.tags.push(newTag.replace(/^#/, '').trim()); newTag = ''; }"
                        @keydown.backspace="if(newTag === '' && projectModal.tags.length > 0){ projectModal.tags.pop(); }"
                        class="tag-input"
                    >
                </div>
            </div>

            <div class="field">
                <label>Deadline</label>
                <div class="deadline-inputs">
                    <input type="date" x-model="projectModal.deadlineDate" class="deadline-date">
                    <input type="time" x-model="projectModal.deadlineTime" class="deadline-time">
                </div>
            </div>

            <!-- Progress -->
            <template x-if="projectModal.subtasks.length > 0">
                <div style="margin-bottom:1.25rem; margin-top:1.5rem;">
                    <div class="card-progress-label" style="margin-bottom:0.375rem;">
                        <span x-text="projectModal.subtasks.filter(s => s.status === 'done').length"></span>
                        /
                        <span x-text="projectModal.subtasks.length"></span>
                        subtasks done
                    </div>
                    <div class="card-progress-bar">
                        <div
                            class="card-progress-fill"
                            :style="'width:' + (projectModal.subtasks.length
                                ? Math.round(projectModal.subtasks.filter(s => s.status === 'done').length / projectModal.subtasks.length * 100)
                                : 0) + '%'"
                        ></div>
                    </div>
                </div>
            </template>

            <!-- List of subtasks -->
            <div class="project-section-label">Subtasks</div>

            <template x-if="projectModal.loadingSubtasks">
                <div class="loading-center"><div class="spinner"></div></div>
            </template>

            <template x-if="!projectModal.loadingSubtasks">
                <div class="subtask-list">
                    <template x-if="projectModal.subtasks.length === 0">
                        <div class="subtask-empty">No subtasks yet</div>
                    </template>

                    <template x-for="sub in projectModal.subtasks" :key="sub.id">
                        <div
                            class="subtask-item"
                            :class="{
                                'done': sub.status === 'done',
                                'on-board': sub.is_on_board && sub.status !== 'done'
                            }"
                        >
                            <div class="subtask-title" :class="{ 'done': sub.status === 'done' }" x-text="sub.title"></div>

                            <div class="subtask-status" :class="{ 'on-board': sub.is_on_board && sub.status !== 'done' }">
                                <template x-if="sub.status === 'done'">
                                    <span>✓ done</span>
                                </template>
                                <template x-if="sub.is_on_board && sub.status !== 'done'">
                                    <span>on board</span>
                                </template>
                            </div>

                            <div class="subtask-actions">
                                <!-- Take to board -->
                                <template x-if="!sub.is_on_board && sub.status !== 'done'">
                                    <button
                                        class="btn btn-ghost btn-xs"
                                        @click.stop="toggleSubtaskOnBoard(sub)"
                                        title="Take to board"
                                    >→ Board</button>
                                </template>
                                <!-- Remove from board -->
                                <template x-if="sub.is_on_board && sub.status !== 'done'">
                                    <button
                                        class="btn btn-ghost btn-xs"
                                        @click.stop="toggleSubtaskOnBoard(sub)"
                                        title="Remove from board"
                                    >← Back</button>
                                </template>
                                <!-- Delete -->
                                <button
                                    class="btn btn-danger btn-xs"
                                    @click.stop="deleteSubtask(sub)"
                                >×</button>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Add subtask -->
            <div style="display:flex; gap:0.5rem; margin-bottom:1.25rem;">
                <input
                    type="text"
                    x-model="projectModal.newSubtaskTitle"
                    placeholder="New subtask..."
                    class="field"
                    style="flex:1; background:var(--surface-2); border:1px solid var(--border); border-radius:0.375rem; padding:0.625rem 0.75rem; color:var(--text); font-family:inherit; font-size:0.9rem; outline:none; margin:0;"
                    @keydown.enter="addSubtask()"
                    @focus="$el.style.borderColor='var(--accent)'"
                    @blur="$el.style.borderColor='var(--border)'"
                >
                <button
                    class="btn btn-primary"
                    @click="addSubtask()"
                    :disabled="!projectModal.newSubtaskTitle.trim()"
                >+ Add</button>
            </div>

            <div class="modal-actions modal-actions-spaced">
                <button class="btn btn-danger" @click="deleteActivity(projectModal.project); projectModal.open = false">
                    Delete project
                </button>
                <div class="modal-actions-group">
                    <button class="btn btn-primary" @click="updateProject()">Save Changes</button>
                </div>
            </div>

        </div>
    </div>
</template>

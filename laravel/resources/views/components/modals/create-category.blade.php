<template x-if="categoryModal.open">
    <div class="modal-overlay" @click.self="categoryModal.open = false">
        <div class="modal modal-narrow">
            <div class="modal-title">New Category</div>

            <div class="field">
                <label>Name *</label>
                <input type="text" x-model="categoryModal.name" placeholder="e.g. Work, Health, Study" @keydown.enter="createNewCategory()">
            </div>

            <div class="field">
                <label>Color</label>
                <input type="color" x-model="categoryModal.color" class="color-input">
            </div>

            <div class="modal-actions">
                <button class="btn btn-ghost" @click="categoryModal.open = false">Cancel</button>
                <button class="btn btn-primary" @click="createNewCategory()" :disabled="!categoryModal.name.trim()">Create Category</button>
            </div>
        </div>
    </div>
</template>

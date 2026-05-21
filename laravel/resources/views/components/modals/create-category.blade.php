<template x-if="categoryModal.open">
    <div class="modal-overlay" @mousedown.self="$el._md=true" @click.self="$el._md&&(categoryModal.open=false);$el._md=false">
        <div class="modal modal-narrow">
            <button class="modal-close" @click="categoryModal.open = false">&times;</button>
            <div class="modal-title">New Category</div>

            <div class="field">
                <label>Name *</label>
                <input type="text" x-model="categoryModal.name" placeholder="e.g. Work, Health, Study" @keydown.enter="createCategory()">
            </div>

            <div class="field">
                <label>Color</label>
                <input type="color" x-model="categoryModal.color" class="color-input">
            </div>

            <div class="modal-actions">
                <button class="btn btn-primary" @click="createCategory()" :disabled="!categoryModal.name.trim()">Create Category</button>
            </div>
        </div>
    </div>
</template>

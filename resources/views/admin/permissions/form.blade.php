<div class="mb-6">
    <label class="form-label required">
        Permission Name
    </label>

    <input type="text"
           name="name"
           class="form-control"
           value="{{ old('name', $permission->name ?? '') }}"
           placeholder="Example: manage causes"
           required>
</div>

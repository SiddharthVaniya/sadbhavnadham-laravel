<div class="mb-6">
    <label class="form-label required">Role Name</label>
    <input type="text"
           name="name"
           class="form-control"
           value="{{ old('name', $role->name ?? '') }}"
           required>
</div>

<div class="mb-6">
    <label class="form-label">Assign Permissions</label>

    <div class="row">
        @foreach($permissions as $permission)
            <div class="col-md-4 mb-2">
                <div class="form-check">
                    <input type="checkbox"
                           name="permissions[]"
                           value="{{ $permission->name }}"
                           class="form-check-input"
                           @if(isset($role))
                               @checked($role->hasPermissionTo($permission->name))
                           @endif>

                    <label class="form-check-label">
                        {{ $permission->name }}
                    </label>
                </div>
            </div>
        @endforeach
    </div>
</div>

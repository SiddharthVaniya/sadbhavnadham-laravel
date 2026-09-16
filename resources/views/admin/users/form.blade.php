<div class="row mb-6">
    <div class="col-md-6">
        <label class="form-label required">Name</label>
        <input type="text"
               name="name"
               class="form-control"
               value="{{ old('name', $user->name ?? '') }}"
               required>
    </div>

    <div class="col-md-6">
        <label class="form-label required">Email</label>
        <input type="email"
               name="email"
               class="form-control"
               value="{{ old('email', $user->email ?? '') }}"
               required>
    </div>
</div>

<div class="row mb-6">
    <div class="col-md-6">
        <label class="form-label {{ isset($user) ? '' : 'required' }}">Password</label>
        <input type="password"
               name="password"
               class="form-control"
               {{ isset($user) ? '' : 'required' }}>
    </div>
</div>

<div class="mb-6">
    <label class="form-label required">Assign Roles</label>

    <div class="row">
        @foreach($roles as $role)
            <div class="col-md-4 mb-2">
                <div class="form-check">
                    <input type="checkbox"
                           name="roles[]"
                           value="{{ $role->name }}"
                           class="form-check-input"
                           @if(isset($user))
                               @checked($user->hasRole($role->name))
                           @endif>

                    <label class="form-check-label">
                        {{ $role->name }}
                    </label>
                </div>
            </div>
        @endforeach
    </div>
</div>

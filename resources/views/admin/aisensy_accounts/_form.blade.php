<div class="admin-form-grid">
    <div class="admin-section-title mb-3">Account Details</div>
    <div class="row g-5">
        <div class="col-md-6">
            <label class="form-label">Account Name</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $aisensy_account->name ?? '') }}" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Country Code</label>
            <input type="text" name="country_code" class="form-control" value="{{ old('country_code', $aisensy_account->country_code ?? '91') }}">
            <small class="text-muted">Example: 91 for India.</small>
        </div>

        <div class="col-12">
            <label class="form-label">API Key</label>
            <textarea name="api_key" class="form-control" rows="4" required>{{ old('api_key', $aisensy_account->api_key ?? '') }}</textarea>
        </div>

        <div class="col-md-6">
            <label class="form-label">Status</label>
            <select name="is_active" class="form-select">
                <option value="1" @selected(old('is_active', $aisensy_account->is_active ?? 1) == 1)>Active</option>
                <option value="0" @selected(old('is_active', $aisensy_account->is_active ?? 1) == 0)>Inactive</option>
            </select>
        </div>
    </div>
</div>

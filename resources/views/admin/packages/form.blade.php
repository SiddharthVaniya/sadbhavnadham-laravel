<div class="admin-form-grid">
    <div class="admin-section-title mb-3">Package Details</div>
    <div class="row g-5 mb-6">
        <div class="col-md-6">
            <label class="form-label">Title</label>
            <input class="form-control" name="title" value="{{ old('title', $package->title) }}" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Amount</label>
            <input class="form-control" type="number" min="1" name="amount" value="{{ old('amount', $package->amount) }}" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Image</label>
            <input class="form-control" type="file" name="image" accept="image/*">
            <input type="hidden" name="image_existing" value="{{ old('image_existing', $package->image) }}">
            @if ($package->image)
                <div class="mt-2"><img src="{{ asset($package->image) }}" alt="Package Image" style="max-height: 80px;"></div>
            @endif
        </div>
        <div class="col-md-6">
            <label class="form-label">Sort Order</label>
            <input class="form-control" type="number" min="0" name="sort_order" value="{{ old('sort_order', $package->sort_order ?? 0) }}">
        </div>
    </div>

    <div class="admin-section-title mb-3">Donation options</div>
    <div class="row g-5 mb-6">
        <div class="col-12">
            <div class="form-check form-switch admin-soft p-3">
                <input class="form-check-input" type="checkbox" id="allow_recurring" name="allow_recurring" value="1" @checked(old('allow_recurring', $package->allow_recurring ?? false)) @disabled(! ($cause->allow_recurring ?? false) && ! ($cause->allow_weekly_recurring ?? false))>
                <label class="form-check-label" for="allow_recurring">Allow recurring donations for this package</label>
                @if (! ($cause->allow_recurring ?? false) && ! ($cause->allow_weekly_recurring ?? false))
                    <small class="d-block text-muted mt-1">Enable monthly or weekly donations on the cause first.</small>
                @endif
            </div>
        </div>
    </div>

    <div class="admin-section-title mb-3">Visibility</div>
    <div class="row g-5">
        <div class="col-12">
            <div class="form-check form-switch admin-soft p-3">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $package->is_active ?? true))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
        </div>
    </div>
</div>

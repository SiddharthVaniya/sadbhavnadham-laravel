@php
    $imagesText = old('images_text', isset($cause) ? implode(PHP_EOL, $cause->images ?? []) : '');
    $detailsText = old('details_text', isset($cause) ? implode(PHP_EOL, $cause->details ?? []) : '');
@endphp

<div class="admin-form-grid">
    <div class="admin-section-title mb-3">Basic Information</div>
    <div class="admin-form-block mb-6">
    <div class="row g-5">
        <div class="col-md-6">
            <label class="form-label">Title</label>
            <input class="form-control" name="title" value="{{ old('title', $cause->title) }}" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Slug</label>
            <input class="form-control" name="slug" value="{{ old('slug', $cause->slug) }}" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Excerpt</label>
            <input class="form-control" name="excerpt" value="{{ old('excerpt', $cause->excerpt) }}" placeholder="Short one-line summary">
        </div>
        <div class="col-md-6">
            <label class="form-label">CTA Text</label>
            <input class="form-control" name="cta_text" value="{{ old('cta_text', $cause->cta_text) }}" placeholder="Donate Now">
        </div>
        <div class="col-12">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="3">{{ old('description', $cause->description) }}</textarea>
        </div>
    </div>
    </div>

    <div class="admin-section-title mb-3">Media</div>
    <div class="admin-form-block mb-6">
    <div class="row g-5">
        <div class="col-md-4">
            <label class="form-label">Hero Image</label>
            <input class="form-control" type="file" name="hero_image" accept="image/*">
            <input type="hidden" name="hero_image_existing" value="{{ old('hero_image_existing', $cause->hero_image) }}">
            @if ($cause->hero_image)
                <div class="mt-2"><img src="{{ asset($cause->hero_image) }}" alt="Hero Image" style="max-height: 80px;"></div>
            @endif
        </div>
        <div class="col-md-4">
            <label class="form-label">Cause Icon (SVG/PNG)</label>
            <input class="form-control" type="file" name="icon_uri_file" accept="image/svg+xml,image/*">
            <input type="hidden" name="icon_uri_existing" value="{{ old('icon_uri_existing', $cause->icon_uri) }}">
            @if ($cause->icon_uri)
                <div class="mt-2"><img src="{{ Illuminate\Support\Str::startsWith($cause->icon_uri, ['http://', 'https://']) ? $cause->icon_uri : asset($cause->icon_uri) }}" alt="Cause Icon" style="max-height: 48px; max-width: 48px;"></div>
            @endif
        </div>
        <div class="col-md-4">
            <label class="form-label">Active Icon (White)</label>
            <input class="form-control" type="file" name="icon_uri_active_file" accept="image/svg+xml,image/*">
            <input type="hidden" name="icon_uri_active_existing" value="{{ old('icon_uri_active_existing', $cause->icon_uri_active) }}">
            @if ($cause->icon_uri_active)
                <div class="mt-2"><img src="{{ Illuminate\Support\Str::startsWith($cause->icon_uri_active, ['http://', 'https://']) ? $cause->icon_uri_active : asset($cause->icon_uri_active) }}" alt="Cause Active Icon" style="max-height: 48px; max-width: 48px; background:#0f172a; padding:4px; border-radius:6px;"></div>
            @endif
        </div>
        <div class="col-12">
            <label class="form-label">Gallery Images</label>
            <input class="form-control" type="file" name="images[]" accept="image/*" multiple>
            <small class="text-muted">Upload multiple images for slider/gallery.</small>
        </div>
    </div>
    </div>

    <div class="admin-section-title mb-3">AiSensy Automation</div>
    <div class="admin-form-block mb-6">
    <div class="row g-5">
        <div class="col-md-6">
            <label class="form-label">AiSensy Account</label>
            <select name="aisensy_account_id" class="form-select">
                <option value="">None / Disabled</option>
                @foreach ($aisensyAccounts as $account)
                    <option value="{{ $account->id }}" @selected((string) old('aisensy_account_id', $cause->aisensy_account_id) === (string) $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
            <small class="text-muted">Choose account for payment-link and thank-you messages.</small>
        </div>
        <div class="col-md-6">
            <label class="form-label">Payment Link Campaign</label>
            <input class="form-control" name="aisensy_payment_link_campaign" value="{{ old('aisensy_payment_link_campaign', $cause->aisensy_payment_link_campaign) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">Thank You Campaign</label>
            <input class="form-control" name="aisensy_thank_you_campaign" value="{{ old('aisensy_thank_you_campaign', $cause->aisensy_thank_you_campaign) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">Thank You Image</label>
            <input class="form-control" type="file" name="aisensy_thank_you_image" accept="image/*">
            <input type="hidden" name="aisensy_thank_you_image_existing" value="{{ old('aisensy_thank_you_image_existing', $cause->aisensy_thank_you_image) }}">
            @if ($cause->aisensy_thank_you_image)
                <div class="mt-2"><img src="{{ asset($cause->aisensy_thank_you_image) }}" alt="Thank You Image" style="max-height: 80px;"></div>
            @endif
        </div>
        <div class="col-md-6">
            <label class="form-label">Thank You Message Mode</label>
            <select name="aisensy_thank_you_message_mode" class="form-select">
                <option value="template" @selected(old('aisensy_thank_you_message_mode', $cause->aisensy_thank_you_message_mode ?? 'template') === 'template')>Template</option>
                <option value="builder" @selected(old('aisensy_thank_you_message_mode', $cause->aisensy_thank_you_message_mode ?? 'template') === 'builder')>Builder</option>
            </select>
            <small class="text-muted">Template uses placeholders. Builder creates sentence from selected parts.</small>
        </div>
        <div class="col-12">
            <label class="form-label">Thank You Message Template</label>
            <textarea class="form-control" name="aisensy_thank_you_message_template" rows="3" placeholder="Thank you {name} for donating Rs {amount} towards {cause}.">{{ old('aisensy_thank_you_message_template', $cause->aisensy_thank_you_message_template) }}</textarea>
            <small class="text-muted">Supported placeholders: {name}, {full_name}, {amount}, {cause}, {receipt_number}</small>
            <div class="mt-2 small text-muted">
                <div><strong>Quick examples:</strong></div>
                <div>1) Thank you {name} for donating Rs {amount} towards {cause}.</div>
                <div>2) Thank you for your support. Receipt: {receipt_number}</div>
                <div>3) Thank you {full_name} for contributing Rs {amount}.</div>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label d-block">Builder Fields</label>
            <div class="d-flex flex-wrap gap-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="aisensy_thank_you_include_name" name="aisensy_thank_you_include_name" value="1" @checked(old('aisensy_thank_you_include_name', $cause->aisensy_thank_you_include_name ?? true))>
                    <label class="form-check-label" for="aisensy_thank_you_include_name">Include Name</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="aisensy_thank_you_include_amount" name="aisensy_thank_you_include_amount" value="1" @checked(old('aisensy_thank_you_include_amount', $cause->aisensy_thank_you_include_amount ?? true))>
                    <label class="form-check-label" for="aisensy_thank_you_include_amount">Include Amount</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="aisensy_thank_you_include_cause" name="aisensy_thank_you_include_cause" value="1" @checked(old('aisensy_thank_you_include_cause', $cause->aisensy_thank_you_include_cause ?? true))>
                    <label class="form-check-label" for="aisensy_thank_you_include_cause">Include Cause</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="aisensy_thank_you_include_receipt" name="aisensy_thank_you_include_receipt" value="1" @checked(old('aisensy_thank_you_include_receipt', $cause->aisensy_thank_you_include_receipt ?? false))>
                    <label class="form-check-label" for="aisensy_thank_you_include_receipt">Include Receipt Number</label>
                </div>
            </div>
        </div>
    </div>
    </div>

    <div class="admin-section-title mb-3">Donation Defaults</div>
    <div class="admin-form-block mb-6">
    <div class="row g-5">
        <div class="col-md-4">
            <label class="form-label">Sort Order</label>
            <input class="form-control" type="number" min="0" name="sort_order" value="{{ old('sort_order', $cause->sort_order ?? 0) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Default Amount</label>
            <input class="form-control" type="number" min="1" name="default_amount" value="{{ old('default_amount', $cause->default_amount) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Default Title</label>
            <input class="form-control" name="default_title" value="{{ old('default_title', $cause->default_title) }}">
        </div>
        <div class="col-12">
            <label class="form-label">Details (one line per bullet)</label>
            <textarea class="form-control" name="details_text" rows="4">{{ $detailsText }}</textarea>
        </div>
    </div>
    </div>

    <div class="admin-section-title mb-3">Donation form options</div>
    <div class="admin-form-block">
    <div class="row g-3">
        <div class="col-12">
            <div class="admin-toggle-card">
                <div>
                    <div class="admin-toggle-title">Published</div>
                    <div class="admin-toggle-sub">Show this cause on the public donation site</div>
                </div>
                <div class="form-check form-switch">
                    <input id="is_active" class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $cause->is_active ?? true))>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="admin-toggle-card">
                <div>
                    <div class="admin-toggle-title">Allow Custom Amount</div>
                    <div class="admin-toggle-sub">Donors can enter any amount instead of choosing a fixed package</div>
                </div>
                <div class="form-check form-switch">
                    <input id="allow_custom_amount" class="form-check-input" type="checkbox" name="allow_custom_amount" value="1" @checked(old('allow_custom_amount', $cause->allow_custom_amount ?? true))>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="admin-toggle-card">
                <div>
                    <div class="admin-toggle-title">Allow Monthly Donations</div>
                    <div class="admin-toggle-sub">Shows One-time / Every month when Razorpay subscriptions are enabled</div>
                </div>
                <div class="form-check form-switch">
                    <input id="allow_recurring" class="form-check-input" type="checkbox" name="allow_recurring" value="1" @checked(old('allow_recurring', $cause->allow_recurring ?? false))>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="admin-toggle-card">
                <div>
                    <div class="admin-toggle-title">Allow Weekly Donations</div>
                    <div class="admin-toggle-sub">Adds Every week on the public donate page (Razorpay weekly subscription)</div>
                </div>
                <div class="form-check form-switch">
                    <input id="allow_weekly_recurring" class="form-check-input" type="checkbox" name="allow_weekly_recurring" value="1" @checked(old('allow_weekly_recurring', $cause->allow_weekly_recurring ?? false))>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="admin-toggle-card">
                <div>
                    <div class="admin-toggle-title">PAN Required</div>
                    <div class="admin-toggle-sub">Make PAN mandatory on the donation form for 80G</div>
                </div>
                <div class="form-check form-switch">
                    <input id="pan_required" class="form-check-input" type="checkbox" name="pan_required" value="1" @checked(old('pan_required', $cause->pan_required ?? true))>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>



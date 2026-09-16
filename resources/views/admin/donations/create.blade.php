@extends('admin.layouts.minimal')

@section('title', 'Add Offline Donation')

@section('content')
    <div class="d-flex flex-column flex-column-fluid">
        <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
            <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
                <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                    <h1 class="page-heading fw-bold fs-3 my-0">Add Offline Donation</h1>
                    <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                        <li class="breadcrumb-item text-muted"><a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Dashboard</a></li>
                        <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
                        <li class="breadcrumb-item text-muted"><a href="{{ route('admin.donations.index') }}" class="text-muted text-hover-primary">Donations</a></li>
                        <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
                        <li class="breadcrumb-item text-muted">Create Offline</li>
                    </ul>
                </div>
            </div>
        </div>

        <div id="kt_app_content" class="app-content flex-column-fluid">
            <div id="kt_app_content_container" class="app-container container-fluid">
                @if ($errors->any())
                    <div class="alert alert-danger mb-5">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card card-flush">
                    <div class="card-body p-6">
                        <form method="POST" action="{{ route('admin.donations.store') }}">
                            @csrf

                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label class="form-label">Donor Name</label>
                                    <input type="text" name="donor_name" class="form-control" value="{{ old('donor_name') }}" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Donor Email</label>
                                    <input type="email" name="donor_email" class="form-control" value="{{ old('donor_email') }}" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Donor Phone</label>
                                    <input type="text" name="donor_phone" class="form-control" value="{{ old('donor_phone') }}" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">PAN Number</label>
                                    <input type="text" name="pan_number" class="form-control" value="{{ old('pan_number') }}" placeholder="AAAAA0000A">
                                </div>

                                <div class="col-md-8">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" id="donorAddress" class="form-control" required>{{ old('address') }}</textarea>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Pincode</label>
                                    <input type="text" name="pincode" id="donorPincode" class="form-control" value="{{ old('pincode') }}" maxlength="6" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" id="donorCity" class="form-control" value="{{ old('city') }}" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">State</label>
                                    <input type="text" name="state" id="donorState" class="form-control" value="{{ old('state') }}" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Country</label>
                                    <input type="text" name="country" id="donorCountryName" class="form-control" value="{{ old('country', 'INDIA') }}" required readonly>
                                    <input type="hidden" name="donor_country_code" value="{{ old('donor_country_code', 'IN') }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Date of Birth (optional)</label>
                                    <input type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth') }}" max="{{ now()->toDateString() }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Payment Provider</label>
                                    <select name="payment_provider" class="form-select" required>
                                        <option value="offline" @selected(old('payment_provider') == 'offline')>Offline</option>
                                        <option value="razorpay" @selected(old('payment_provider') == 'razorpay')>Razorpay</option>
                                        <option value="danamojo" @selected(old('payment_provider') == 'danamojo')>Danamojo</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Donation Amount</label>
                                    <input type="number" step="0.01" name="total_amount" class="form-control" value="{{ old('total_amount') }}" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-check form-switch mt-4">
                                        <input class="form-check-input" type="checkbox" name="send_receipt_email" value="1" checked>
                                        <span class="form-check-label">Send receipt email now</span>
                                    </label>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Cause (optional)</label>
                                    <select name="cause_id" class="form-select">
                                        <option value="">None</option>
                                        @foreach ($causes as $cause)
                                            <option value="{{ $cause->id }}" @selected(old('cause_id') == $cause->id)>{{ $cause->title }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Custom Item Title (optional)</label>
                                    <input type="text" name="item_title" class="form-control" value="{{ old('item_title') }}">
                                </div>
                            </div>

                            <div class="mt-6">
                                <button type="submit" class="btn btn-primary">Save Donation & Generate Receipt</button>
                                <a href="{{ route('admin.donations.index') }}" class="btn btn-light">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('customjs')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const pincodeInput = document.getElementById('donorPincode');
            const cityInput = document.getElementById('donorCity');
            const stateInput = document.getElementById('donorState');
            const countryInput = document.getElementById('donorCountryName');

            if (!pincodeInput || !cityInput || !stateInput) {
                return;
            }

            let pincodeDebounceTimer = null;
            let pincodeAbortController = null;

            const unlockLocationFields = () => {
                cityInput.readOnly = false;
                stateInput.readOnly = false;
                cityInput.value = '';
                stateInput.value = '';
            };

            const hydrateLocationByPincode = async (pincode) => {
                if (pincode.length !== 6) {
                    return;
                }

                if (pincodeAbortController) {
                    pincodeAbortController.abort();
                }
                pincodeAbortController = new AbortController();

                try {
                    const response = await fetch(`https://api.postalpincode.in/pincode/${pincode}`, {
                        signal: pincodeAbortController.signal,
                    });

                    if (!response.ok) {
                        return;
                    }

                    const data = await response.json();
                    const postOffice = data?.[0]?.PostOffice?.[0];
                    if (!postOffice) {
                        return;
                    }

                    cityInput.value = postOffice.District || '';
                    stateInput.value = postOffice.State || '';
                    if (countryInput) {
                        countryInput.value = 'INDIA';
                    }

                    cityInput.readOnly = true;
                    stateInput.readOnly = true;
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        // Keep manual entry possible when API is unavailable.
                    }
                }
            };

            pincodeInput.addEventListener('input', () => {
                pincodeInput.value = pincodeInput.value.replace(/\D/g, '').slice(0, 6);

                unlockLocationFields();

                if (pincodeDebounceTimer) {
                    clearTimeout(pincodeDebounceTimer);
                    pincodeDebounceTimer = null;
                }

                if (pincodeInput.value.length === 6) {
                    pincodeDebounceTimer = setTimeout(() => {
                        pincodeDebounceTimer = null;
                        hydrateLocationByPincode(pincodeInput.value);
                    }, 250);
                }
            });
        });
    </script>
@endsection

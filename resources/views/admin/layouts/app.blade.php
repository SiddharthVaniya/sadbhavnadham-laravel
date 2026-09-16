<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="{{ app()->getLocale() }}">
	<head>
		<title>{{ $branding['shortName'] }} - @yield('title') </title>
		<meta charset="utf-8" />
		<meta name="csrf-token" content="{{ csrf_token() }}" />
		<meta name="description" content="" />
		<meta name="keywords" content="" />
		<meta property="og:title" content="" /> 
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<meta property="og:locale" content="en_US" />
		<meta property="og:type" content="article" />
		<meta property="og:url" content="" />
		<meta property="og:site_name" content="s" />
		<link rel="shortcut icon" href="{{ $branding['faviconUrl'] }}"/>
		<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />

		<link href="{{asset('assets/plugins/global/plugins.bundle.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{asset('assets/css/style.bundle.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{ url('assets/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
		<link href="{{asset('assets/css/style.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{ asset('assets/css/admin-portal.css') }}?v={{ filemtime(public_path('assets/css/admin-portal.css')) }}" rel="stylesheet" type="text/css" />
		@if (file_exists(public_path('build/manifest.json')))
			@vite(['resources/css/app.css', 'resources/js/app.js'])
		@else
			<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
		@endif
		<style>
			.admin-page-shell {
				background: radial-gradient(circle at 100% 0, rgba(15, 127, 135, 0.08), transparent 45%), radial-gradient(circle at 0 100%, rgba(245, 159, 68, 0.08), transparent 55%);
				border-radius: 1rem;
				padding: 1rem;
			}

			.admin-surface {
				border: 1px solid #eef0f3;
				border-radius: 1rem;
				box-shadow: 0 10px 30px rgba(20, 34, 66, 0.05);
			}

			.admin-page-head {
				background: linear-gradient(125deg, #1f2a44 0%, #0f7f87 100%);
				border-radius: 1rem;
				padding: 1.25rem;
				color: #fff;
			}
			.btn-sadbhavna {
				background: #0d3b5e;
				color: #fff;
				border-radius: 0.75rem;
				padding: 0.5rem 1rem;
				border: none;
			}
			.btn-sadbhavna:hover {
				background: #0d3b5e;
				opacity: 0.9;
			}

			.admin-page-head h1,
			.admin-page-head p {
				color: #fff;
			}

			.admin-pills {
				display: flex;
				gap: 0.5rem;
				flex-wrap: wrap;
			}

			.admin-pill {
				padding: 0.35rem 0.65rem;
				border-radius: 999px;
				background: rgba(255, 255, 255, 0.14);
				font-size: 0.75rem;
				font-weight: 600;
			}

			.admin-section-title {
				font-size: 0.95rem;
				font-weight: 700;
				letter-spacing: 0.02em;
				text-transform: uppercase;
				color: #5e6278;
			}

			.admin-soft {
				background: #f9f9fb;
				border: 1px solid #efeff2;
				border-radius: 0.9rem;
			}

			.admin-form-grid .form-label {
				font-weight: 600;
				color: #3f4254;
			}

			.admin-form-grid .form-control,
			.admin-form-grid .form-select {
				border-radius: 0.65rem;
				background: #fff;
			}

			.admin-form-grid .form-control:focus,
			.admin-form-grid .form-select:focus {
				border-color: #0f7f87;
				box-shadow: 0 0 0 0.15rem rgba(15, 127, 135, 0.12);
			}

			.admin-form-block {
				background: #fff;
				border: 1px solid #eceef3;
				border-radius: 0.9rem;
				padding: 1rem;
			}

			.admin-toggle-card {
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 0.75rem;
				background: #fff;
				border: 1px solid #e8ebf1;
				border-radius: 0.8rem;
				padding: 0.85rem 0.95rem;
				min-height: 74px;
			}

			.admin-toggle-title {
				font-weight: 700;
				font-size: 0.92rem;
				color: #252f4a;
			}

			.admin-toggle-sub {
				font-size: 0.78rem;
				color: #7e8299;
			}

			.admin-toggle-card .form-check {
				margin: 0;
				padding: 0;
			}

			.admin-toggle-card .form-check-input {
				margin: 0;
				float: none;
			}

			.admin-form-grid .form-check.form-switch {
				padding-left: 0;
				display: flex;
				align-items: center;
				gap: 0.75rem;
			}

			.admin-form-grid .form-check.form-switch .form-check-input {
				margin: 0;
				margin-left: 0;
			}

			.admin-form-grid .form-check.form-switch .form-check-label {
				margin-bottom: 0;
				cursor: pointer;
				user-select: none;
			}

			.admin-table thead th {
				text-transform: uppercase;
				font-size: 0.75rem;
				letter-spacing: 0.03em;
				color: #7e8299;
			}

			.admin-table tbody tr:hover {
				background: #fcfcff;
			}

			.admin-stat-card {
				background: #fff;
				border: 1px solid #e8ebf1;
				border-radius: 0.85rem;
				padding: 0.9rem 1rem;
				min-width: 120px;
				text-align: center;
			}

			.admin-stat-card__value {
				font-size: 1.35rem;
				font-weight: 800;
				line-height: 1.2;
				color: #1f2a44;
			}

			.admin-stat-card__label {
				margin-top: 0.2rem;
				font-size: 0.72rem;
				font-weight: 600;
				text-transform: uppercase;
				letter-spacing: 0.03em;
				color: #7e8299;
			}

			.admin-stat-card--success .admin-stat-card__value {
				color: #0f7f87;
			}

			.admin-stat-card--warning .admin-stat-card__value {
				color: #b45309;
			}
		</style>

		<link href="https://fonts.googleapis.com/css2?family=Hind+Vadodara:wght@300;400;500;600;700&display=swap" rel="stylesheet">
		@yield('customcss')
	</head>
	<body id="kt_app_body" data-kt-app-layout="light-sidebar" data-kt-app-header-fixed="true" data-kt-app-sidebar-enabled="true" data-kt-app-sidebar-fixed="true" data-kt-app-sidebar-hoverable="true" data-kt-app-sidebar-push-header="true" data-kt-app-sidebar-push-toolbar="true" data-kt-app-sidebar-push-footer="true" data-kt-app-toolbar-enabled="true" class="app-default admin-portal">
		
		<div class="d-flex flex-column flex-root app-root" id="kt_app_root">
			<div class="app-page flex-column flex-column-fluid" id="kt_app_page">
				<!-- HEADER -->
				@include('admin.layouts.partials.header')
				<!-- HEADER -->
				<div class="app-wrapper flex-column flex-row-fluid" id="kt_app_wrapper">
					<!-- SIDEBAR -->
					@include('admin.layouts.partials.sidebar')
					<!-- SIDEBAR -->
					<div class="app-main flex-column flex-row-fluid" id="kt_app_main">
						<div class="admin-content-area">
							<div class="admin-page-shell">
								@yield('content')
							</div>
						</div>
					</div>
					<!-- FOOTER -->
					@include('admin.layouts.partials.footer')
					<!-- FOOTER -->
				</div>
			</div>
		</div>
	    @livewireScripts

		  <!-- assets/js/scripts.bundle.js -->
		<script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
		<script src="{{ asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
		<script src="{{ asset('assets/js/scripts.bundle.js') }}"></script>
		@flasher_render
		<script>
			window.adminNotify = function (type, message, title) {
				if (window.toastr && typeof window.toastr[type] === 'function') {
					window.toastr[type](message, title || '');
					return;
				}

				window.alert(message);
			};
		</script>
		@yield('customjs')
		@stack('scripts')
	</body>
</html>
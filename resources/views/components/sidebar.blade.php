	<!-- Menu -->
	<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

		<div class="app-brand demo">
			<a href="{{url('dashboard')}}" class="app-brand-link">
				<span class="app-brand-logo demo mt-4">
				<img id="logoImage" src="{{ asset('farm_assets/images/farm_images/logo.png') }}" alt="Logo" class="logo-image w-100">
			</span>
				{{-- <span class="app-brand-text demo menu-text fw-bold"></span> --}}
			</a>

			<a id="toggleButton" href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
				<i class="ti menu-toggle-icon d-none d-xl-block ti-sm align-middle"></i>
				<i class="ti ti-x d-block d-xl-none ti-sm align-middle"></i>
			</a>
		</div>


		<div class="menu-inner-shadow"></div>

		<ul class="menu-inner mt-4 py-1">

			<li class="menu-item {{ request()->is('dashboard') ? "active" : "" }}">
				<a href="{{ route('dashboard') }}" class="menu-link">
					<i class="menu-icon tf-icons ti ti-smart-home"></i>
					<div data-i18n="Dashboard">Dashboard</div>
				</a>
			</li>
			

			@canany(['role-list', 'permission-list', 'user-list'])
			<li class="menu-item {{ request()->is('roles') || request()->is('permissions') || request()->is('users*') || request()->is('region*') ? "active open" : "" }}">

				<a href="javascript:void(0);" class="menu-link menu-toggle">
					<i class="menu-icon tf-icons ti ti-settings"></i>
					<div data-i18n="Settings">Settings</div>
				</a>

				<ul class="menu-sub">

					@can('role-list')
					<li class="menu-item {{ request()->is('roles') ? "active" : "" }}">
						<a href="{{ route('roles') }}" class="menu-link">
						<div data-i18n="Roles">Roles</div>
						</a>
					</li>
					@endcan

					@can('user-list')
					<li class="menu-item {{ request()->is('users*') ? "active" : "" }}">
						<a href="{{ route('users') }}" class="menu-link">
							<div data-i18n="Users">Users</div>
						</a>
					</li>
					@endcan

				</ul>
			</li>
			@endcanany


			@can('company-list')
			<li class="menu-item {{ request()->is('companies*') ? "active" : "" }}">
				<a href="{{ route('companies') }}" class="menu-link">
					<i class="menu-icon tf-icons ti ti-building"></i>
					<div data-i18n="Companies">Companies</div>
				</a>
			</li>
			@endcan


		</ul>

		<script>
			const toggleButton = document.getElementById("toggleButton");
			const logoImage = document.getElementById("logoImage");
			let isImageVisible = true;

			toggleButton.addEventListener("click", function() {
				if (isImageVisible) {
				logoImage.style.display = "none";
				} else {
				logoImage.style.display = "block";
				}
				isImageVisible = !isImageVisible;
			});
		</script>

	</aside>
	<!-- / Menu -->


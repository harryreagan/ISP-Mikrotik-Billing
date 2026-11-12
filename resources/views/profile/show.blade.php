<x-app-layout>
    <x-slot name="header">
        {{ __('Profile Dashboard') }}
    </x-slot>

    <div class="ui-page-wrap">
        <x-ui.page-header :title="__('My Profile')" :subtitle="__('Manage your personal profile, security settings, and active sessions')" />

        <div class="row g-4">
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-body profile-card pt-4 d-flex flex-column align-items-center text-center">
                        <img src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" style="width: 6.5rem; height: 6.5rem; object-fit: cover;" class="profileImagePreview rounded-circle mb-3 border">
                        <h3 class="ui-section-title mb-1">{{ Auth::user()->name }}</h3> 
                        @php
                            $userRoles = Auth::user()->roles ? Auth::user()->roles->pluck('name')->toArray() : [];
                            if (empty($userRoles) && !empty(Auth::user()->role)) {
                                $userRoles = [ucfirst(Auth::user()->role)];
                            }
                            if (empty($userRoles)) {
                                $userRoles = ['ISP Administrator'];
                            }
                        @endphp
                        <div class="small text-muted mb-2">Act as : <span class="badge bg-warning-subtle text-warning">{{ implode(', ', $userRoles) }}</span></div>

                        <div class="social-links mt-2 d-flex gap-3 text-muted">
                            <a wire:navigate.hover wire:current="active" href="#" class="twitter text-muted"><i class="bi bi-twitter"></i></a>
                            <a wire:navigate.hover wire:current="active" href="#" class="facebook text-muted"><i class="bi bi-facebook"></i></a>
                            <a wire:navigate.hover wire:current="active" href="#" class="instagram text-muted"><i class="bi bi-instagram"></i></a>
                            <a wire:navigate.hover wire:current="active" href="#" class="linkedin text-muted"><i class="bi bi-linkedin"></i></a>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    @canany(['create-role', 'edit-role', 'delete-role'])
                        <a wire:navigate.hover wire:current="active" class="btn btn-secondary flex-fill" href="{{ route('admin-roles') }}">
                            <i class="bi bi-shield-lock me-1"></i> Manage Roles</a>
                    @endcanany
                    @canany(['create-user', 'edit-user', 'delete-user'])
                        <a wire:navigate.hover wire:current="active" class="btn btn-secondary flex-fill" href="{{ route('admin-users') }}">
                            <i class="bi bi-people me-1"></i> Manage Users</a>
                    @endcanany
                    @canany(['create', 'edit', 'delete','view'])
                        <a wire:navigate.hover wire:current="active" class="btn btn-primary flex-fill" href="{{ route('dashboard') }}"><i class="bi bi-grid me-1"></i> {{ __('Dashboard') }}</a>
                    @endcanany
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card">
                    <div class="card-body pt-3">
                        <!-- Bordered Tabs -->
                        <ul class="nav nav-tabs nav-tabs-bordered" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile-overview" aria-selected="true" role="tab">Overview</button>
                            </li>
                            @if (Laravel\Fortify\Features::canUpdateProfileInformation() || Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-edit" aria-selected="false" tabindex="-1" role="tab">Edit Profile</button>
                                </li>
                            @endif
                            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-change-password" aria-selected="false" tabindex="-1" role="tab">Change Password</button>
                                </li>
                            @endif
                            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-two-factor" aria-selected="false" tabindex="-1" role="tab">Two Factor</button>
                                </li>
                            @endif
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-browser-sessions" aria-selected="false" tabindex="-1" role="tab">Browser Sessions</button>
                            </li>
                        </ul>

                        <div class="tab-content pt-3">
                            <div class="p-2 tab-pane fade show active" id="profile-overview" role="tabpanel">
                                <h6 class="ui-section-title mb-3">{{ __('Profile Details') }}</h6>
                                <div class="d-flex flex-wrap gap-1.5">
                                    @foreach(Auth::user()->roles as $role)
                                        <span class="badge bg-warning-subtle text-warning">{{ $role->name }} Role: Yes</span>
                                    @endforeach 

                                    @if(Auth::user()->hasRole('Super Admin'))
                                        @foreach (\Spatie\Permission\Models\Permission::pluck('name') as $permission)
                                            <span class="badge bg-secondary-subtle text-secondary">{{ $permission }}: Yes</span>
                                        @endforeach
                                    @else
                                        @foreach(Auth::user()->getPermissionsViaRoles()->pluck('name') as $permission)
                                            <span class="badge bg-secondary-subtle text-secondary">{{ $permission }}: Yes</span>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                            @if (Laravel\Fortify\Features::canUpdateProfileInformation() || Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                                <div class="p-2 tab-pane fade" id="profile-edit" role="tabpanel">
                                    @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                                        @livewire('profile.update-profile-information-form')
                                        <x-section-border />
                                    @endif
                                    @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                                        @livewire('profile.delete-user-form')
                                    @endif
                                </div>
                            @endif
                            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                                <div class="p-2 tab-pane fade" id="profile-change-password" role="tabpanel">
                                    @livewire('profile.update-password-form')
                                </div>
                            @endif
                            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                                <div class="p-2 tab-pane fade" id="profile-two-factor" role="tabpanel">
                                    @livewire('profile.two-factor-authentication-form')
                                </div>
                            @endif
                            <div class="p-2 tab-pane fade" id="profile-browser-sessions" role="tabpanel">
                                @livewire('profile.logout-other-browser-sessions-form')
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

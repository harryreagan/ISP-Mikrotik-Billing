<div class="ui-page-wrap">
    <x-slot name="header">
        {{ __('Manage Users') }}
    </x-slot>

    <x-ui.page-header :title="__('User Directory')" :subtitle="__('Manage staff accounts, roles, and access permissions')">
        <x-slot:actions>
            @can('create-user')
                <button wire:click="newUser" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Add New User') }}</button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3 p-2.5 bg-slate-50 rounded-xl">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">Show</span>
                    <select class="form-select form-select-sm" style="width: 85px;" wire:model.live="perPage">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="30">30</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="*">All</option>
                    </select>
                    <span class="text-muted small">entries</span>
                </div>

                <div class="position-relative" style="width: 280px;">
                    <input type="text" class="form-control form-control-sm ps-3 pe-5" wire:model.live="search" placeholder="Search user details...">
                    <i class="bi bi-search position-absolute end-0 top-50 translate-middle-y me-3 text-muted"></i>
                </div>
            </div>

            <div class="row g-3">
                @forelse ($users as $user)
                    @php
                        $initials = mb_strtoupper(mb_substr($user->name ?? '', 0, 1, 'UTF-8'), 'UTF-8');
                        $colors   = ['#4f46e5','#0ea5e9','#16a34a','#ea580c','#dc2626','#7c3aed','#0891b2','#ca8a04'];
                        $bgColor  = $colors[abs(crc32($user->email)) % count($colors)];
                    @endphp
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 position-relative">
                            
                            {{-- Top Action Corner / Badges --}}
                            <div class="position-absolute top-0 end-0 p-3 d-flex gap-1 align-items-center">
                                @if($user->id === Auth::id())
                                    <span class="badge bg-success-subtle text-success">You</span>
                                @endif
                            </div>

                            <div class="card-body p-4 d-flex flex-column h-100">
                                {{-- Avatar + Identity Header --}}
                                <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                                    @if($user->profile_photo_path)
                                        <img src="{{ Storage::url($user->profile_photo_path) }}"
                                             alt="{{ $user->name }}"
                                             class="avatar rounded-circle border" style="width: 44px; height: 44px; object-fit: cover;">
                                    @else
                                        <div class="avatar rounded-circle d-flex align-items-center justify-content-center fw-semibold text-white" 
                                             style="background:{{ $bgColor }}; width: 44px; height: 44px; font-size:15px;">
                                            {{ $initials }}
                                        </div>
                                    @endif
                                    <div class="overflow-hidden">
                                        <h6 class="ui-section-title mb-0 text-truncate" title="{{ $user->name }}">{{ $user->name }}</h6>
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            @foreach ($user->getRoleNames() as $role)
                                                <span class="badge {{ $role === 'Super Admin' ? 'bg-success-subtle text-success' : 'bg-info-subtle text-info' }}">{{ $role }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                {{-- User Details --}}
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="d-inline-flex align-items-center justify-content-center rounded-lg bg-slate-50 text-secondary p-1" style="width: 24px; height: 24px;">
                                            <i class="bi bi-envelope" style="font-size: 0.75rem;"></i>
                                        </span>
                                        <span class="text-secondary text-truncate small" title="{{ $user->email }}">{{ $user->email }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="d-inline-flex align-items-center justify-content-center rounded-lg bg-slate-50 text-secondary p-1" style="width: 24px; height: 24px;">
                                            <i class="bi bi-telephone" style="font-size: 0.75rem;"></i>
                                        </span>
                                        <span class="text-secondary small">{{ $user->mobile ?? 'N/A' }}</span>
                                    </div>
                                    <div class="d-flex align-items-start gap-2 mb-2">
                                        <span class="d-inline-flex align-items-center justify-content-center rounded-lg bg-slate-50 text-secondary p-1 mt-0.5" style="width: 24px; height: 24px; flex-shrink: 0;">
                                            <i class="bi bi-geo-alt" style="font-size: 0.75rem;"></i>
                                        </span>
                                        <span class="text-secondary small">{{ $user->address ?? 'No Address' }}</span>
                                    </div>

                                    {{-- Permissions List --}}
                                    <div class="mt-3 pt-2 border-top">
                                        <div class="text-dark fw-semibold mb-1.5 small"><i class="bi bi-key me-1 text-orange-500"></i>Permissions:</div>
                                        <div class="d-flex flex-wrap gap-1">
                                            @if (in_array('Super Admin', $user->getRoleNames()->toArray() ?? []))
                                                <span class="badge bg-success-subtle text-success"><i class="bi bi-star-fill me-1"></i>All Permissions</span>
                                            @else
                                                @php
                                                    $userPermissions = $user->getAllPermissions();
                                                    $permCount = $userPermissions->count();
                                                @endphp
                                                @forelse ($userPermissions->take(6) as $permission)
                                                    <span class="badge bg-info-subtle text-info">{{ $permission->name }}</span>
                                                @empty
                                                    <span class="badge bg-secondary-subtle text-secondary">No Permissions</span>
                                                @endforelse
                                                @if ($permCount > 6)
                                                    <span class="badge bg-secondary-subtle text-secondary">+{{ $permCount - 6 }} more</span>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Actions Footer --}}
                                <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                                    @if (in_array('Super Admin', $user->getRoleNames()->toArray() ?? []) )
                                        @if (Auth::user()->hasRole('Super Admin'))
                                            <button wire:click="editUser({{ $user->id }})" class="btn btn-sm btn-secondary" title="Edit"><i class="bi bi-pencil-square me-1"></i>Edit</button>
                                            @if (Auth::user()->id == $user->id)
                                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteUser({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ addslashes($user->email) }}')" title="Delete"><i class="bi bi-trash me-1"></i>Delete</button>
                                            @endif
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-lock-fill me-1"></i>System Protected</span>
                                        @endif
                                    @else
                                        @can('edit-user')
                                            <button wire:click="editUser({{ $user->id }})" class="btn btn-sm btn-secondary" title="Edit"><i class="bi bi-pencil-square me-1"></i>Edit</button>
                                        @endcan

                                        @can('delete-user')
                                            @if (Auth::user()->id != $user->id)
                                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteUser({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ addslashes($user->email) }}')" title="Delete"><i class="bi bi-trash me-1"></i>Delete</button>
                                            @endif
                                        @endcan
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <x-ui.empty-state icon="bi-people" title="No Users Found!" description="Try adjusting your search or add a new user." />
                    </div>
                @endforelse
            </div>

            <div class="mt-3">
                {{ $users->links() }}
            </div>
        </div>
    </div>

    @include('livewire.admin.user.user-form')
</div>

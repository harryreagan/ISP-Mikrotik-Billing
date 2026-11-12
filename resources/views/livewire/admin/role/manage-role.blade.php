<div class="ui-page-wrap">
    <x-slot name="header">
        {{ __('Manage Roles') }}
    </x-slot>

    <x-ui.page-header :title="__('Role Management')" :subtitle="__('Define user roles and assign granular system permissions')">
        <x-slot:actions>
            @can('create-user-role')
                <button wire:click="newRole" wire:loading.attr="disabled" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>
                    {{ __('Add New Role') }}
                </button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3 p-2.5 bg-slate-50 rounded-xl">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">Show</span>
                    <select class="form-select form-select-sm" style="width: 85px;"
                        wire:model.live="perPage" aria-label="Default select example">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="30">30</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="*">All</option>
                    </select>
                    <span class="text-muted small">entries</span>
                </div>
            </div>

            <div class="row g-3">
                @forelse ($roles as $role)
                    @php
                        $permissionsCount = $role->permissions->count();
                    @endphp
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body p-4 d-flex flex-column h-100">

                                {{-- Card Header: Role Name & Icon --}}
                                <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
                                    <div class="d-flex align-items-center gap-2.5 overflow-hidden">
                                        <span class="d-inline-flex align-items-center justify-content-center rounded-xl bg-orange-50 text-orange-500 p-2"
                                            style="width: 38px; height: 38px; flex-shrink: 0;">
                                            <i class="bi bi-shield-lock"></i>
                                        </span>
                                        <div class="overflow-hidden">
                                            <h6 class="ui-section-title mb-0 text-truncate"
                                                title="{{ $role->name }}">{{ $role->name }}</h6>
                                            <span class="text-muted small">
                                                @if ($role->name === 'Super Admin')
                                                    All System Privileges
                                                @else
                                                    {{ $permissionsCount }}
                                                    {{ Str::plural('Permission', $permissionsCount) }}
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Card Body: Permissions List --}}
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap gap-1">
                                        @if ($role->name === 'Super Admin')
                                            <span class="badge bg-success-subtle text-success"><i
                                                    class="bi bi-star-fill me-1"></i>All Permissions</span>
                                        @else
                                            @forelse ($role->permissions->take(8) as $permission)
                                                <span class="badge bg-info-subtle text-info">{{ $permission->name }}</span>
                                            @empty
                                                <span class="badge bg-secondary-subtle text-secondary">No Permissions Assigned</span>
                                            @endforelse

                                            @if ($permissionsCount > 8)
                                                <span class="badge bg-secondary-subtle text-secondary">+ {{ $permissionsCount - 8 }} more</span>
                                            @endif
                                        @endif
                                    </div>
                                </div>

                                {{-- Card Footer: Action Buttons --}}
                                <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                                    @if ($role->name !== 'Super Admin')
                                        @can('edit-user-role')
                                            <button class="btn btn-sm btn-secondary"
                                                wire:click="editRole({{ $role->id }})" title="Edit"><i
                                                    class="bi bi-pencil-square me-1"></i>Edit</button>
                                        @endcan
                                        @can('delete-user-role')
                                            <button class="btn btn-sm btn-outline-danger"
                                                wire:click="deleteRole({{ $role->id }}, '{{ $role->name }}')"
                                                title="Delete"><i
                                                    class="bi bi-trash me-1"></i>Delete</button>
                                        @endcan
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary"><i
                                                class="bi bi-lock-fill me-1"></i>System Protected</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <x-ui.empty-state icon="bi-shield-lock" title="No Roles Found!" description="Create a role to assign permissions." />
                    </div>
                @endforelse
            </div>

            <div class="mt-3">
                {{ $roles->links() }}
            </div>
        </div>
    </div>

    @include('livewire.admin.role.role-form')
</div>

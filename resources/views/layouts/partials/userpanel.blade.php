{{-- theme, alerts and settings --}}
<ul class="navbar-nav navbar-nav-icons ms-auto flex-row align-items-center gap-1">
    {{-- SMS Balance Indicator --}}
    <li class="nav-item d-flex align-items-center me-1">
        <x-sms-balance />
    </li>

    {{-- Live Telemetry Clock / Date Pill (Hidden on mobile) --}}
    <li class="nav-item d-none d-md-flex align-items-center me-1" wire:ignore
        x-data="{
            current: new Date('{{ now()->format('Y-m-d H:i:s') }}'),
            timeString: '',
            dateString: '',
            init() {
                this.updateDisplay();
                setInterval(() => {
                    this.current.setSeconds(this.current.getSeconds() + 1);
                    this.updateDisplay();
                }, 1000);
            },
            updateDisplay() {
                const pad = n => n.toString().padStart(2, '0');
                const hours = pad(this.current.getHours());
                const minutes = pad(this.current.getMinutes());
                const seconds = pad(this.current.getSeconds());
                this.timeString = `${hours}:${minutes}:${seconds}`;
                this.dateString = this.current.toLocaleDateString('en-US', {
                    weekday: 'short', day: 'numeric', month: 'short'
                });
            }
        }"
        x-init="init()"
    >
        <div class="d-flex align-items-center gap-2 px-3 rounded-pill bg-light" 
             style="height: 36px; background-color: #f8fafc !important; border: 1px solid #e2e8f0 !important; font-size: 12.5px;">
            <span class="rounded-circle bg-success" style="width: 7px; height: 7px; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);"></span>
            <span class="fw-semibold text-dark font-monospace" x-text="timeString" style="font-size: 12px; font-variant-numeric: tabular-nums;"></span>
            <span style="color: #cbd5e1;">|</span>
            <span class="text-secondary fw-medium" x-text="dateString" style="font-size: 12px;"></span>
            <span class="badge bg-secondary-subtle text-secondary-emphasis px-1.5 py-0.5 rounded" style="font-size: 9px; font-weight: 700; letter-spacing: 0.5px;">EAT</span>
        </div>
    </li>

    <li class="nav-item px-1">
        <div x-data="themeToggle()" x-init="init()" class="dropdown theme-control-dropdown">
            <a class="nav-link btn-toolbar-icon dropdown-toggle dropdown-caret-none"
                href="#" role="button" id="themeSwitchDropdown"
                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="{{ __('Toggle theme') }}">
                <template x-if="theme === 'light'">
                    <i class="bi bi-sun fs-8 text-secondary"></i>
                </template>
                <template x-if="theme === 'dark'">
                    <i class="bi bi-moon-stars-fill fs-8 text-secondary"></i>
                </template>
                <template x-if="theme === 'auto'">
                    <i class="bi bi-circle-half fs-8 text-secondary"></i>
                </template>
            </a>

            <div class="dropdown-menu dropdown-menu-end dropdown-caret border py-0 mt-3" aria-labelledby="themeSwitchDropdown">
                <div class="bg-white dark__bg-1000 rounded-2 py-2">
                    <button @click="setTheme('light')" class="dropdown-item d-flex align-items-center gap-2" type="button"
                        value="light" data-theme-control="theme">
                        <i class="bi bi-sun fs-8"></i>
                        {{ __('Light') }}
                    </button>
                    <button @click="setTheme('dark')" class="dropdown-item d-flex align-items-center gap-2" type="button"
                        value="dark" data-theme-control="theme">
                        <i class="bi bi-moon-stars-fill fs-8"></i>
                        {{ __('Dark') }}
                    </button>
                    <button @click="setTheme('auto')" class="dropdown-item d-flex align-items-center gap-2" type="button"
                        value="auto" data-theme-control="theme">
                        <i class="bi bi-circle-half fs-8"></i>
                        {{ __('Auto') }}
                    </button>
                </div>
            </div>
        </div>
    </li>
    
    @if((auth()->user()->hasAnyRole(['Super Admin', 'Admin']) || in_array(strtolower(auth()->user()->role ?? ''), ['admin', 'superadmin', 'super_admin'])) && !auth()->user()->hasRole('Reseller'))
        @livewire('NotificationList')
    @endif

    <li class="nav-item px-1">
        <a class="nav-link btn-toolbar-icon" href="#settings-offcanvas" data-bs-toggle="offcanvas" role="button" title="{{ __('Settings') }}">
            <i class="bi bi-gear-fill fs-8 text-secondary"></i>
        </a>
    </li>

    <li class="nav-item dropdown px-1">
        <a class="nav-link btn-toolbar-icon" id="navbarDropdownMenu" role="button" data-hide-on-body-scroll="data-hide-on-body-scroll" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="{{ __('Quick Links') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" class="text-secondary">
                <circle cx="2.5" cy="2.5" r="1.5"></circle>
                <circle cx="2.5" cy="8" r="1.5"></circle>
                <circle cx="2.5" cy="13.5" r="1.5"></circle>
                <circle cx="8" cy="2.5" r="1.5"></circle>
                <circle cx="8" cy="8" r="1.5"></circle>
                <circle cx="8" cy="13.5" r="1.5"></circle>
                <circle cx="13.5" cy="2.5" r="1.5"></circle>
                <circle cx="13.5" cy="8" r="1.5"></circle>
                <circle cx="13.5" cy="13.5" r="1.5"></circle>
            </svg>
        </a>
        <div class="dropdown-menu dropdown-caret dropdown-menu-end dropdown-menu-card dropdown-caret-bg" aria-labelledby="navbarDropdownMenu">
            <div class="card shadow-none">
                <div class="scrollbar-overlay nine-dots-dropdown">
                    <div class="card-body px-3">
                        <div class="row gx-0 gy-0">
                            <div class="col-4">
                                <a class="d-block hover-bg-200 px-2 py-3 rounded-3 text-decoration-none" wire:navigate.hover href="{{route('profile.show')}}">
                                    <div class="avatar avatar-2xl"> <img class="rounded-circle" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" /></div>
                                    <p class="mb-0 fw-medium text-800 text-truncate fs-11">{{ Auth::user()->name }}</p>
                                </a>
                            </div>
                            <div class="col-4">
                                <a class="d-block hover-bg-200 px-2 py-3 rounded-3 text-decoration-none" href="mailto:support@hsmart.co.ke" target="_blank">
                                    <div class="avatar avatar-2xl d-flex align-items-center justify-content-center bg-primary text-dark rounded-circle mx-auto">
                                        <i class="bi bi-envelope-fill fs-6"></i>
                                    </div>
                                    <p class="mb-0 fw-medium text-800 text-truncate fs-11 pt-1">Email Support</p>
                                </a>
                            </div>
                            <div class="col-4">
                                <a class="d-block hover-bg-200 px-2 py-3 rounded-3 text-decoration-none" href="https://wa.me/254743606108?text=Hello%20HSmart%20Support" target="_blank">
                                    <div class="avatar avatar-2xl d-flex align-items-center justify-content-center bg-success text-white rounded-circle mx-auto">
                                        <i class="bi bi-whatsapp fs-6"></i>
                                    </div>
                                    <p class="mb-0 fw-medium text-800 text-truncate fs-11 pt-1">WhatsApp</p>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </li>

    <li class="nav-item dropdown ps-1">
        <a class="nav-link p-0 d-flex align-items-center" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            @if (Laravel\Jetstream\Jetstream::managesProfilePhotos() && Auth::user()->profile_photo_url)
                <div style="width: 36px; height: 36px; border-radius: 9999px; overflow: hidden; display: grid; place-items: center; border: 2px solid #FED7AA; flex-shrink: 0;">
                    <img class="rounded-circle" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" style="width: 100%; height: 100%; object-fit: cover;" />
                </div>
            @else
                @php
                    $names = explode(' ', trim(Auth::user()->name ?: 'User'));
                    $first = substr($names[0] ?? 'U', 0, 1);
                    $second = isset($names[1]) ? substr($names[1], 0, 1) : substr($names[0], 1, 1);
                    $initials = strtoupper($first . $second);
                @endphp
                <div style="width: 36px; height: 36px; border-radius: 9999px; overflow: hidden; display: grid; place-items: center; background-color: #F57C00; color: #FFFFFF; font-size: 12px; font-weight: 700; flex-shrink: 0; user-select: none;">
                    {{ $initials }}
                </div>
            @endif
        </a>

        <div class="dropdown-menu dropdown-caret dropdown-caret dropdown-menu-end py-0" aria-labelledby="navbarDropdownUser">
            <div class="bg-white dark__bg-1000 rounded-2 py-2">
                <a wire:navigate.hover wire:current="active" class="dropdown-item" href="{{route('profile.show')}}">{{ __('Profile & account') }}</a>
                <div class="dropdown-divider"></div>
                @if (hasAccess(['Super Admin'], ['site-settings']))
                    <a class="dropdown-item" wire:navigate.hover wire:current="active" href="{{route('site-settings')}}">{{ __('Master Setup') }}</a>
                @endif
                @if (hasAccess(['Super Admin'], ['create-user', 'edit-user', 'delete-user']))
                    <a class="dropdown-item" wire:navigate.hover wire:current="active" href="{{route('admin-users')}}">{{ __('Manage Users') }}</a>
                @endif
                @if (hasAccess(['Super Admin'], ['create-user-role', 'edit-user-role', 'view-user-role','delete-user-role']))
                    <a class="dropdown-item" wire:navigate.hover wire:current="active" href="{{route('admin-roles')}}">{{ __('Manage Roles') }}</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" x-data>
                    @csrf
                    <a class="dropdown-item" href="{{ route('logout') }}" @click.prevent="$root.submit();">
                        {{ __('Log Out') }}
                    </a>
                </form>
            </div>
        </div>
    </li>
</ul>

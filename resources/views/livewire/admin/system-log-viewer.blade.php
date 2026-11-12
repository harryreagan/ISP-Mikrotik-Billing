<div>
    <div class="ui-page-wrap">
        
        {{-- Header --}}
        <x-ui.page-header :title="__('Laravel System Log Explorer')" :subtitle="__('Native backward streaming log viewer - zero memory exhaust')">
            <x-slot:actions>
                <button wire:click="clearLog('{{ $selectedFile }}')" 
                    onclick="confirm('{{ __('Are you sure you want to clear all logs in :file?', ['file' => $selectedFile]) }}') || event.stopImmediatePropagation()"
                    class="btn btn-sm btn-secondary">
                    <i class="bi bi-eraser me-1"></i> {{ __('Clear Current File') }}
                </button>
                @if($selectedFile !== 'laravel.log')
                    <button wire:click="deleteLog('{{ $selectedFile }}')" 
                        onclick="confirm('{{ __('Are you sure you want to delete :file permanently?', ['file' => $selectedFile]) }}') || event.stopImmediatePropagation()"
                        class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash3 me-1"></i> {{ __('Delete Current File') }}
                    </button>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        <div class="row g-4">
            {{-- Left Sidebar: Log Files list --}}
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="ui-section-title mb-0"><i class="bi bi-folder me-2 text-orange-500"></i>{{ __('Log Files') }}</h6>
                    </div>
                    <div class="list-group list-group-flush border-top">
                        @foreach($files as $file)
                            <div class="list-group-item d-flex align-items-center justify-content-between py-3 border-0 border-bottom {{ $selectedFile === $file['name'] ? 'bg-orange-50 border-start border-warning border-3' : '' }}">
                                <div class="cursor-pointer flex-grow-1" wire:click="$set('selectedFile', '{{ $file['name'] }}')" style="cursor: pointer;">
                                    <div class="fw-semibold text-dark small">{{ $file['name'] }}</div>
                                    <div class="text-muted small">{{ __('Size:') }} {{ $file['size'] }}</div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button wire:click="clearLog('{{ $file['name'] }}')" 
                                        title="{{ __('Clear File Content') }}"
                                        onclick="confirm('{{ __('Clear content of :file?', ['file' => $file['name']]) }}') || event.stopImmediatePropagation()"
                                        class="btn btn-link text-warning p-0">
                                        <i class="bi bi-eraser"></i>
                                    </button>
                                    @if($file['name'] !== 'laravel.log')
                                        <button wire:click="deleteLog('{{ $file['name'] }}')" 
                                            title="{{ __('Delete Log File') }}"
                                            onclick="confirm('{{ __('Delete log file :file permanently?', ['file' => $file['name']]) }}') || event.stopImmediatePropagation()"
                                            class="btn btn-link text-danger p-0">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Right Main Panel: Log Entries Viewer --}}
            <div class="col-lg-9">
                {{-- Search & Filters --}}
                <div class="card mb-4">
                    <div class="card-body py-3">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label mb-1">{{ __('Search log content') }}</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white border-end-0 text-muted">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input wire:model.live.debounce.400ms="searchQuery" type="text" 
                                        placeholder="{{ __('Search message, stack trace...') }}" 
                                        class="form-control form-control-sm border-start-0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1">{{ __('Filter Level') }}</label>
                                <select wire:model.live="selectedLevel" class="form-select form-select-sm">
                                    <option value="ALL">{{ __('All Levels') }}</option>
                                    @foreach($levels as $level)
                                        <option value="{{ $level }}">{{ $level }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1">{{ __('Limit') }}</label>
                                <select wire:model.live="logsLimit" class="form-select form-select-sm">
                                    <option value="50">{{ __('Last 50 logs') }}</option>
                                    <option value="100">{{ __('Last 100 logs') }}</option>
                                    <option value="250">{{ __('Last 250 logs') }}</option>
                                    <option value="500">{{ __('Last 500 logs') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Logs Stream --}}
                <div class="card">
                    <div class="card-header py-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <h6 class="ui-section-title mb-0"><i class="bi bi-terminal me-2 text-orange-500"></i>{{ __('Log Entries (:count)', ['count' => count($logs)]) }}</h6>
                            <span class="badge bg-secondary-subtle text-secondary">{{ __('Showing newest first') }}</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="table-layout: fixed; width: 100%;">
                                <thead>
                                    <tr>
                                        <th class="ps-4 py-3" style="width: 100px;">{{ __('Level') }}</th>
                                        <th style="width: 170px;">{{ __('Date & Time') }}</th>
                                        <th style="width: 80px;">{{ __('Env') }}</th>
                                        <th>{{ __('Message') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($logs as $log)
                                        @php
                                            $lvl = strtoupper($log['level']);
                                            $badgeClass = match($lvl) {
                                                'DEBUG' => 'secondary',
                                                'INFO' => 'success',
                                                'NOTICE' => 'info',
                                                'WARNING' => 'warning',
                                                'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY' => 'danger',
                                                default => 'secondary'
                                            };
                                        @endphp
                                        <tr x-data="{ open: false }" class="border-bottom">
                                            <td colspan="4" class="p-0">
                                                <div class="d-flex align-items-center ps-4 pe-3 py-3" @click="open = !open" style="cursor: pointer;">
                                                    <div style="width: 100px;">
                                                        <span class="badge bg-{{ $badgeClass }}-subtle text-{{ $badgeClass }} font-monospace">
                                                            {{ $lvl }}
                                                        </span>
                                                    </div>
                                                    <div class="text-muted small font-monospace" style="width: 170px;">
                                                        {{ $log['date'] }}
                                                    </div>
                                                    <div class="small fw-semibold text-orange-600" style="width: 80px;">
                                                        {{ $log['env'] }}
                                                    </div>
                                                    <div class="flex-grow-1 text-dark fw-medium text-truncate small" title="{{ $log['message'] }}">
                                                        {{ $log['message'] }}
                                                    </div>
                                                    <div>
                                                        <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                                    </div>
                                                </div>

                                                {{-- Expanded stack trace --}}
                                                <div x-show="open" x-cloak class="bg-slate-50 border-top p-4 font-monospace small text-dark" style="max-height: 400px; overflow-y: auto; font-size: 0.8rem;">
                                                    <div class="fw-semibold mb-2 text-danger">{{ __('Message:') }}</div>
                                                    <div class="bg-white border rounded-xl p-3 mb-3 text-break">{{ $log['message'] }}</div>
                                                    
                                                    @if(!empty($log['stack_trace']))
                                                        <div class="fw-semibold mb-2 text-orange-600">{{ __('Stack Trace:') }}</div>
                                                        <pre class="bg-white border rounded-xl p-3 text-wrap text-break" style="white-space: pre-wrap; font-size: 0.75rem;">{{ $log['stack_trace'] }}</pre>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="p-0">
                                                <x-ui.empty-state icon="bi-patch-check" :title="__('No log entries found matching criteria.')" :description="__('Select another log file or adjust the level filter.')" />
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

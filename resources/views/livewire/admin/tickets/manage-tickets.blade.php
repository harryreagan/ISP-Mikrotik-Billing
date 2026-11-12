<div>
    <x-slot name="header">
        {{ __('Support tickets') }}
    </x-slot>

    <x-ui.page-header :title="__('Support tickets')" :subtitle="__('Track customer inquiries, troubleshoot connection issues, and manage ticket resolution status.')" />

    <!-- Stats Overview Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-md-3">
            <x-ui.stat-card :label="__('Total tickets')" :value="$stats['total']" color="purple">
                <x-slot:icon><i class="bi bi-ticket-perforated"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <x-ui.stat-card :label="__('Open')" :value="$stats['open']" color="orange">
                <x-slot:icon><i class="bi bi-folder2-open"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <x-ui.stat-card :label="__('In progress')" :value="$stats['in_progress']" color="blue">
                <x-slot:icon><i class="bi bi-clock-history"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <x-ui.stat-card :label="__('Resolved')" :value="$stats['resolved']" color="green">
                <x-slot:icon><i class="bi bi-check-circle"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>
    </div>

    <!-- Filters & Main Card -->
    <x-ui.card :padding="false" class="overflow-hidden">
        <div class="p-3 border-bottom">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="d-flex align-items-center gap-1.5">
                        <label class="form-label text-nowrap mb-0">{{ __('Show') }}:</label>
                        <select class="form-select form-select-sm" wire:model.live="perPage" style="width: 80px;">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="30">30</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>

                    <div class="d-flex align-items-center gap-1.5">
                        <label class="form-label text-nowrap mb-0 ms-md-2">{{ __('Status') }}:</label>
                        <select class="form-select form-select-sm" wire:model.live="statusFilter" style="width: 140px;">
                            <option value="">{{ __('All statuses') }}</option>
                            <option value="open">{{ __('Open') }}</option>
                            <option value="in_progress">{{ __('In progress') }}</option>
                            <option value="resolved">{{ __('Resolved') }}</option>
                            <option value="closed">{{ __('Closed') }}</option>
                        </select>
                    </div>

                    <div class="d-flex align-items-center gap-1.5">
                        <label class="form-label text-nowrap mb-0 ms-md-2">{{ __('Priority') }}:</label>
                        <select class="form-select form-select-sm" wire:model.live="priorityFilter" style="width: 140px;">
                            <option value="">{{ __('All priorities') }}</option>
                            <option value="low">{{ __('Low') }}</option>
                            <option value="medium">{{ __('Medium') }}</option>
                            <option value="high">{{ __('High') }}</option>
                        </select>
                    </div>
                </div>

                <div class="position-relative" style="min-width: 250px;">
                    <input type="text" class="form-control form-control-sm ps-4" 
                        wire:model.live="search" placeholder="{{ __('Search tickets...') }}">
                    <i class="bi bi-search position-absolute text-muted" style="left: 10px; top: 50%; transform: translateY(-50%); font-size: 0.8125rem;"></i>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 110px;">{{ __('Ticket no') }}</th>
                        <th>{{ __('Customer') }}</th>
                        <th>{{ __('Subject & details') }}</th>
                        <th style="width: 120px;">{{ __('Category') }}</th>
                        <th style="width: 100px;">{{ __('Priority') }}</th>
                        <th style="width: 110px;">{{ __('Status') }}</th>
                        <th style="width: 120px;">{{ __('Submitted') }}</th>
                        <th class="text-end pe-4" style="width: 140px;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                    <tr>
                        <td class="font-monospace fw-semibold text-orange-600">#{{ $ticket->ticket_no }}</td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-semibold text-dark">{{ $ticket->customer->customer_name ?? 'N/A' }}</span>
                                <small class="text-muted" style="font-size: 0.75rem;">ID: {{ $ticket->customer_unique_id }} ({{ $ticket->ppp_username }})</small>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-medium text-dark">{{ $ticket->subject }}</span>
                                <small class="text-muted text-truncate d-inline-block" style="max-width: 320px;">{{ $ticket->description }}</small>
                            </div>
                        </td>
                        <td class="text-capitalize text-secondary">{{ __($ticket->category) }}</td>
                        <td>
                            <x-ui.badge :status="$ticket->priority">
                                {{ __(ucfirst($ticket->priority)) }}
                            </x-ui.badge>
                        </td>
                        <td>
                            <x-ui.badge :status="$ticket->status">
                                {{ __(ucfirst(str_replace('_', ' ', $ticket->status))) }}
                            </x-ui.badge>
                        </td>
                        <td>
                            <span class="text-muted small" title="{{ $ticket->created_at }}">{{ $ticket->created_at->diffForHumans() }}</span>
                        </td>
                        <td class="text-end pe-4">
                            <x-ui.button variant="secondary" wire:click="showReplyModal({{ $ticket->id }})">
                                <i class="bi bi-chat-dots"></i> {{ __('Manage') }}
                            </x-ui.button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="p-0">
                            <x-ui.empty-state icon="bi-ticket-perforated" :title="__('No tickets found')" :description="__('There are no support tickets matching your current filter criteria.')" />
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 border-top">
            {{ $tickets->links() }}
        </div>
    </x-ui.card>

    {{-- Reply Modal --}}
    @if($confirmingReply && $selectedTicket)
    <x-dialog-modal wire:model.live="confirmingReply" maxWidth="2xl">
        <x-slot name="title">
            <div class="d-flex align-items-center justify-content-between pb-2 border-bottom">
                <span class="ui-section-title mb-0"><i class="bi bi-chat-text text-orange-500 me-2"></i>{{ __('Manage ticket') }}</span>
                <x-ui.badge tone="info" class="font-monospace">#{{ $selectedTicket->ticket_no }}</x-ui.badge>
            </div>
        </x-slot>

        <x-slot name="content">
            <div class="row g-3 mb-4 mt-1 bg-light p-3 rounded-3 mx-0">
                <div class="col-12 col-md-6">
                    <small class="text-muted d-block fw-medium" style="font-size: 0.75rem;">{{ __('Customer name') }}</small>
                    <span class="text-dark fw-semibold">{{ $selectedTicket->customer->customer_name ?? 'N/A' }}</span>
                </div>
                <div class="col-12 col-md-6">
                    <small class="text-muted d-block fw-medium" style="font-size: 0.75rem;">{{ __('PPP username / ID') }}</small>
                    <span class="text-dark font-monospace">{{ $selectedTicket->ppp_username }} ({{ $selectedTicket->customer_unique_id }})</span>
                </div>
                @if($selectedTicket->customer && $selectedTicket->customer->mobile)
                <div class="col-12 col-md-6">
                    <small class="text-muted d-block fw-medium" style="font-size: 0.75rem;">{{ __('Contact mobile') }}</small>
                    <span class="text-dark">{{ $selectedTicket->customer->mobile }}</span>
                </div>
                @endif
                <div class="col-12 col-md-6">
                    <small class="text-muted d-block fw-medium" style="font-size: 0.75rem;">{{ __('Priority & category') }}</small>
                    <x-ui.badge tone="neutral" class="me-1">{{ __($selectedTicket->category) }}</x-ui.badge>
                    <x-ui.badge :status="$selectedTicket->priority">{{ __(ucfirst($selectedTicket->priority)) }}</x-ui.badge>
                </div>
            </div>

            <!-- Subject & Description Box -->
            <div class="mb-4">
                <h6 class="ui-section-title mb-1.5">{{ $selectedTicket->subject }}</h6>
                <div class="p-3 bg-light rounded-3 text-secondary" style="font-size: 0.875rem; white-space: pre-line; border-left: 3px solid #f97316;">
                    {{ $selectedTicket->description }}
                </div>
            </div>

            <form wire:submit.prevent="submitReply">
                <div class="mb-4">
                    <label for="adminReply" class="form-label">{{ __('Admin reply / response') }}</label>
                    <textarea id="adminReply" class="form-control @error('adminReply') is-invalid @enderror" 
                        wire:model="adminReply" rows="5" placeholder="{{ __('Type your reply here to send to the customer...') }}"></textarea>
                    <x-error name="adminReply" />
                </div>

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-top pt-3">
                    <div class="d-flex align-items-center gap-2">
                        <label for="status" class="form-label mb-0">{{ __('Update ticket status:') }}</label>
                        <select id="status" class="form-select form-select-sm @error('status') is-invalid @enderror" 
                            wire:model="status" style="width: 140px;">
                            <option value="open">{{ __('Open') }}</option>
                            <option value="in_progress">{{ __('In progress') }}</option>
                            <option value="resolved">{{ __('Resolved') }}</option>
                            <option value="closed">{{ __('Closed') }}</option>
                        </select>
                        <x-error name="status" />
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <x-ui.button variant="secondary" wire:click="$set('confirmingReply', false)">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit" variant="primary"><i class="bi bi-send"></i> {{ __('Save response') }}</x-ui.button>
                    </div>
                </div>
            </form>
        </x-slot>

        <x-slot name="footer">
            {{-- blank --}}
        </x-slot>
    </x-dialog-modal>
    @endif
</div>

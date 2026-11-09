@php
    $suspended = \Illuminate\Support\Facades\Cache::get('hub_isp_suspended', false);
    $notices = \Illuminate\Support\Facades\Cache::get('hub_active_notices', []);
    $commandBanner = \Illuminate\Support\Facades\Cache::get('hub_command_banner');
@endphp

@if($suspended)
    <div class="bg-rose-600 text-white px-4 py-3 shadow-md flex items-center justify-between z-50">
        <div class="flex items-center space-x-3">
            <svg class="h-6 w-6 text-rose-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div>
                <span class="font-bold">ACCOUNT SUSPENDED:</span>
                <span>Your ISP instance is suspended by HSmart Platforms Super Admin. Please contact platform support.</span>
            </div>
        </div>
    </div>
@elseif($commandBanner)
    <div x-data="{ show: true }" x-show="show" class="bg-indigo-700 text-white px-4 py-2.5 shadow flex items-center justify-between text-sm z-40">
        <div class="flex items-center space-x-2">
            <span class="font-semibold">{{ $commandBanner['title'] ?? 'Platform Notice:' }}</span>
            <span>{{ $commandBanner['message'] ?? ($commandBanner['body'] ?? '') }}</span>
        </div>
        <button @click="show = false" class="text-indigo-200 hover:text-white">&times;</button>
    </div>
@elseif(!empty($notices))
    @foreach($notices as $notice)
        <div x-data="{ show: true }" x-show="show" class="bg-slate-900 border-b border-slate-700 text-slate-100 px-4 py-2 shadow-sm flex items-center justify-between text-xs z-30">
            <div class="flex items-center space-x-2">
                <span class="px-1.5 py-0.5 rounded bg-indigo-500/30 text-indigo-300 font-semibold uppercase tracking-wider text-[10px]">HSmart Hub</span>
                <span class="font-bold">{{ $notice['title'] }}:</span>
                <span>{{ $notice['body'] }}</span>
            </div>
            <button @click="show = false" class="text-slate-400 hover:text-white">&times;</button>
        </div>
    @endforeach
@endif


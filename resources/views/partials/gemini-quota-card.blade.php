@php
    $sidebarGeminiQuota = $geminiQuota ?? app(\App\Services\GeminiRateLimitService::class)->chatSnapshot();
    $sidebarQuotaBlocked = ($sidebarGeminiQuota['enabled'] ?? false) && ! ($sidebarGeminiQuota['can_ask'] ?? true);
    $sidebarQuotaItems = collect([$sidebarGeminiQuota['chat'] ?? null, $sidebarGeminiQuota['embedding'] ?? null])
        ->filter(fn ($quota) => ($quota['limited'] ?? false) === true);
@endphp

@if ($sidebarGeminiQuota['enabled'] ?? false)
    <div class="m-4 rounded-lg border {{ $sidebarQuotaBlocked ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50' }} p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-slate-950">Gemini limits</p>
                <p class="mt-1 text-xs leading-5 {{ $sidebarQuotaBlocked ? 'text-amber-800' : 'text-slate-500' }}">
                    {{ $sidebarQuotaBlocked ? ($sidebarGeminiQuota['blocked_message'] ?? 'Limit reached. Try again after reset.') : 'Free tier usage for this app.' }}
                </p>
            </div>
            <span class="shrink-0 rounded-full px-2 py-1 text-[11px] font-semibold {{ $sidebarQuotaBlocked ? 'bg-amber-100 text-amber-800' : 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' }}">
                {{ $sidebarQuotaBlocked ? 'Wait' : 'Ready' }}
            </span>
        </div>

        <div class="mt-4 space-y-4">
            @foreach ($sidebarQuotaItems as $quota)
                @php
                    $minuteRequests = $quota['minute']['requests'];
                    $minuteTokens = $quota['minute']['tokens'];
                    $dayRequests = $quota['day']['requests'];
                    $remainingPercent = max(0, 100 - $dayRequests['percent']);
                @endphp
                <section>
                    <div class="flex items-center justify-between gap-2">
                        <p class="truncate text-xs font-semibold text-slate-800">{{ $quota['label'] === 'Gemini chat' ? 'Chat answers' : 'Embeddings' }}</p>
                        <p class="shrink-0 text-xs font-semibold text-slate-900">{{ number_format($dayRequests['remaining']) }} left</p>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full {{ $sidebarQuotaBlocked ? 'bg-amber-500' : 'bg-teal-600' }}" style="width: {{ $remainingPercent }}%"></div>
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-[11px] leading-4 text-slate-500">
                        <span>Minute</span>
                        <span class="text-right font-medium text-slate-700">{{ number_format($minuteRequests['remaining']) }} req</span>
                        <span>Tokens</span>
                        <span class="text-right font-medium text-slate-700">{{ number_format($minuteTokens['remaining']) }}</span>
                        <span>Daily</span>
                        <span class="text-right font-medium text-slate-700">{{ number_format($dayRequests['remaining']) }} / {{ number_format($dayRequests['limit']) }}</span>
                    </div>
                </section>
            @endforeach
        </div>
    </div>
@endif

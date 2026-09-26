<div class="flex items-center gap-2 px-3 py-1 {{ ($between ?? false) ? 'mb-4' : '' }}" aria-label="Today" @if ($calendar->isRunning()) x-init="$nextTick(() => $el.scrollIntoView({ block: 'center' }))" @endif>
    <span class="h-px flex-1 bg-primary-500/60"></span>
    <span class="text-[11px] font-bold uppercase tracking-wide text-primary-600 dark:text-primary-300">Today &middot; {{ $today->format('D, M j') }}</span>
    <span class="h-px flex-1 bg-primary-500/60"></span>
</div>

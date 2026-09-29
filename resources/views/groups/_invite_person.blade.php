{{-- One person in the invite box. Rendered inside an Alpine x-for with `p`. --}}
<button type="button" @click="toggle(p)"
    class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left transition"
    :class="p.can_invite ? (isSelected(p) ? 'bg-primary-500/10' : 'hover:bg-ink-900/[0.04] dark:hover:bg-white/[0.04]') : 'cursor-default opacity-60'"
    :aria-pressed="isSelected(p)">
    <template x-if="p.avatar_url">
        <img :src="p.avatar_url" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover">
    </template>
    <template x-if="!p.avatar_url">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary-500/15 text-xs font-semibold text-primary-700 dark:text-primary-300" x-text="p.initials"></span>
    </template>
    <span class="min-w-0 flex-1">
        <span class="block truncate text-sm font-semibold" x-text="p.name"></span>
        <span class="block truncate text-xs text-ink-500 dark:text-ink-400"
              x-text="p.state === 'member' ? 'Already a member' : (p.state === 'invited' ? 'Already invited' : (p.reason || p.subtitle))"></span>
    </span>
    <span x-show="p.can_invite"
        class="grid h-5 w-5 shrink-0 place-items-center rounded-md border transition"
        :class="isSelected(p) ? 'border-primary-600 bg-primary-600 text-white' : 'border-ink-300 dark:border-ink-600'">
        <svg x-show="isSelected(p)" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
    </span>
</button>

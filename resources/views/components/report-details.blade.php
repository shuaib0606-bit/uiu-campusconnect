{{--
    The "describe the problem" box of a report form. Shown, required and
    submitted only when the reason is "Something else" - the other reasons
    already say what is wrong.

    The surrounding form needs x-data with `reason` and `details`, and its
    reason <select> bound with x-model="reason".
--}}
@php($other = \App\Support\ReportReasons::OTHER)
@php($max = \App\Support\ReportReasons::DETAILS_MAX)
<div x-show="reason === @js($other)" x-cloak class="mt-2">
    <label class="block text-xs font-medium text-ink-600 dark:text-ink-300" :for="$id('report-details')">What's wrong?</label>
    <textarea name="details" rows="3" maxlength="{{ $max }}" x-model="details"
        x-effect="if (reason === @js($other)) $nextTick(() => setTimeout(() => $el.focus(), 30))" :id="$id('report-details')"
        :required="reason === @js($other)" :disabled="reason !== @js($other)"
        placeholder="Describe the problem so the moderators know what to look for."
        class="field mt-1.5 !py-1.5 text-xs leading-relaxed"></textarea>
    <p class="mt-0.5 text-right text-[11px] text-ink-400" x-text="details.length + ' / {{ $max }}'"></p>
</div>

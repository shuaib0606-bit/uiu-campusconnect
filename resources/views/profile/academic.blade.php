{{--
    Editing the Academic tab. Everything is one form: the list below is kept
    in Alpine and written out as sections[i][...] fields, in order, when the
    form is saved. Text is Markdown under the hood; the toolbar writes it so
    nobody has to know the syntax, and the preview is rendered by the server
    exactly as the profile page will show it.
--}}
<x-app-layout title="Academic profile - UIU CampusConnect">
    <div class="mx-auto max-w-3xl" x-data="academicEditor(@js(['sections' => $sections, 'removed' => $removed, 'presets' => $presets, 'levels' => $levels, 'previewUrl' => route('academic.preview')]))">
        <div class="surface mb-5 flex flex-wrap items-center justify-between gap-3 p-6">
            <div>
                <h1 class="text-[19px] font-semibold">Academic profile</h1>
                <p class="section-sub">What appears on the Academic tab of your profile. Choose who sees each section.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('profile.show', [$user, 'tab' => 'academic']) }}" class="btn-secondary">View on profile</a>
                <a href="{{ route('profile.edit') }}" class="btn-secondary">Edit profile</a>
            </div>
        </div>

        @if ($errors->any())
            <div class="surface-flat mb-4 border-l-4 border-l-red-500 px-4 py-3 text-sm">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <form action="{{ route('academic.update') }}" method="POST" class="space-y-4">
            @csrf @method('PUT')

            <template x-for="(s, i) in sections" :key="s.key">
                <div class="surface p-5">
                    <input type="hidden" :name="`sections[${i}][id]`" :value="s.id ?? ''">
                    <input type="hidden" :name="`sections[${i}][restore]`" :value="s.restore ? 1 : 0">

                    <div class="flex flex-wrap items-center gap-2">
                        <input :name="`sections[${i}][title]`" x-model="s.title" required maxlength="150" placeholder="Section title" class="field min-w-0 flex-1 !py-2 font-semibold">
                        <select :name="`sections[${i}][visibility]`" x-model="s.visibility" class="field w-auto !py-2 text-sm" aria-label="Who can see this section">
                            <template x-for="(label, value) in levels" :key="value">
                                <option :value="value" x-text="label" :selected="value === s.visibility"></option>
                            </template>
                        </select>
                        <div class="flex gap-1">
                            <button type="button" class="btn-icon" @click="move(i, -1)" :disabled="i === 0" title="Move up"><x-ui-icon name="chevron-down" class="h-4 w-4 rotate-180" /></button>
                            <button type="button" class="btn-icon" @click="move(i, 1)" :disabled="i === sections.length - 1" title="Move down"><x-ui-icon name="chevron-down" class="h-4 w-4" /></button>
                            <button type="button" class="btn-icon" @click="remove(i)" title="Remove section"><x-ui-icon name="x" class="h-4 w-4" /></button>
                        </div>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-medium text-ink-400">
                        <template x-if="s.website && !s.locked && !edited(s)"><span class="inline-flex items-center gap-1"><x-ui-icon name="globe" class="h-3.5 w-3.5" />From the department website - keeps updating from it</span></template>
                        <template x-if="s.website && (s.locked || edited(s))"><span class="inline-flex items-center gap-1"><x-ui-icon name="edit" class="h-3.5 w-3.5" />Your version - the website will no longer change it</span></template>
                        <template x-if="s.website && s.websiteBody !== null && s.body !== s.websiteBody">
                            <button type="button" class="font-bold text-primary-600 hover:underline dark:text-primary-300" @click="restoreWebsite(s)">Restore website version</button>
                        </template>
                    </div>

                    {{-- Toolbar. Each button writes the Markdown for the selection. --}}
                    <div class="mt-3 flex flex-wrap items-center gap-1 rounded-t-xl border border-b-0 px-2 py-1.5" style="border-color: var(--hairline)">
                        <button type="button" class="rounded-lg px-2.5 py-1 text-sm font-black hover:bg-ink-900/[0.05] dark:hover:bg-white/[0.06]" @click="wrap(i, '**')" title="Bold">B</button>
                        <button type="button" class="rounded-lg px-2.5 py-1 text-sm italic hover:bg-ink-900/[0.05] dark:hover:bg-white/[0.06]" @click="wrap(i, '*')" title="Italic">I</button>
                        <button type="button" class="rounded-lg px-2.5 py-1 text-sm hover:bg-ink-900/[0.05] dark:hover:bg-white/[0.06]" @click="prefix(i, '- ')" title="Bullet list">&bull; List</button>
                        <button type="button" class="rounded-lg px-2.5 py-1 text-sm hover:bg-ink-900/[0.05] dark:hover:bg-white/[0.06]" @click="prefix(i, 'n')" title="Numbered list">1. List</button>
                        <button type="button" class="rounded-lg px-2.5 py-1 text-sm hover:bg-ink-900/[0.05] dark:hover:bg-white/[0.06]" @click="link(i)" title="Link">Link</button>
                        <span class="flex-1"></span>
                        <button type="button" class="rounded-lg px-2.5 py-1 text-sm font-semibold hover:bg-ink-900/[0.05] dark:hover:bg-white/[0.06]" :class="s.preview ? 'text-primary-600 dark:text-primary-300' : ''" @click="togglePreview(s)" x-text="s.preview ? 'Edit' : 'Preview'"></button>
                    </div>
                    <textarea :name="`sections[${i}][body]`" x-model="s.body" x-show="!s.preview" :id="`body-${s.key}`" rows="8" maxlength="300000" class="field !rounded-t-none font-mono text-[13px] leading-relaxed"></textarea>
                    <div x-show="s.preview" class="academic-text max-h-[32rem] overflow-y-auto rounded-b-xl border p-4 text-sm leading-relaxed" style="border-color: var(--hairline)" x-html="s.previewHtml || '<p class=&quot;text-ink-400&quot;>Loading preview...</p>'"></div>
                </div>
            </template>

            <div class="surface flex flex-wrap items-center gap-2 p-4">
                <span class="text-sm font-semibold">Add a section:</span>
                <template x-for="preset in availablePresets()" :key="preset">
                    <button type="button" class="chip hover:!bg-primary-500/10" @click="add(preset)" x-text="preset"></button>
                </template>
                <button type="button" class="chip hover:!bg-primary-500/10" @click="add('')">Custom...</button>
            </div>

            <template x-if="removed.length">
                <div class="surface p-4">
                    <p class="mb-2 text-sm font-semibold">Removed website sections <span class="font-normal text-ink-400">- hidden from your profile and not brought back by the website</span></p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="(r, j) in removed" :key="r.id">
                            <button type="button" class="chip inline-flex items-center gap-1 hover:!bg-primary-500/10" @click="putBack(j)"><x-ui-icon name="plus" class="h-3.5 w-3.5" /><span x-text="r.title"></span></button>
                        </template>
                    </div>
                </div>
            </template>

            <div class="sticky bottom-4 z-10 flex gap-2">
                <button type="submit" class="btn-primary flex-1 py-2.5 shadow-lg">Save academic profile</button>
                <a href="{{ route('profile.show', [$user, 'tab' => 'academic']) }}" class="btn-secondary py-2.5 shadow-lg">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            window.Alpine.data('academicEditor', (initial) => ({
                sections: initial.sections.map((s, n) => ({ ...s, key: 's' + (s.id ?? n), original: s.body, restore: false, preview: false, previewHtml: '' })),
                removed: initial.removed,
                presets: initial.presets,
                levels: initial.levels,
                counter: 0,

                edited(s) { return s.body !== s.original; },

                availablePresets() {
                    const have = this.sections.map((s) => s.title.trim().toLowerCase());
                    return this.presets.filter((p) => !have.includes(p.toLowerCase()));
                },

                add(title) {
                    this.sections.push({ id: null, key: 'new' + (++this.counter), title, body: '', visibility: 'everyone', website: false, locked: false, websiteBody: null, original: '', restore: false, preview: false, previewHtml: '' });
                    this.$nextTick(() => document.getElementById('body-new' + this.counter)?.focus());
                },

                move(i, step) {
                    const j = i + step;
                    if (j < 0 || j >= this.sections.length) return;
                    [this.sections[i], this.sections[j]] = [this.sections[j], this.sections[i]];
                },

                remove(i) {
                    const [s] = this.sections.splice(i, 1);
                    // Website sections go to the "removed" list so they can be put back.
                    if (s.website && s.id) this.removed.push(s);
                },

                putBack(j) {
                    const [s] = this.removed.splice(j, 1);
                    this.sections.push({ ...s, key: 's' + s.id, original: s.original ?? s.body, restore: false, preview: false, previewHtml: '' });
                },

                restoreWebsite(s) {
                    s.body = s.websiteBody;
                    s.restore = true;
                    s.locked = false;
                    s.original = s.websiteBody;
                    if (s.preview) this.renderPreview(s);
                },

                textarea(i) { return document.getElementById('body-' + this.sections[i].key); },

                wrap(i, mark) {
                    const t = this.textarea(i); const s = this.sections[i];
                    const [a, b] = [t.selectionStart, t.selectionEnd];
                    const picked = s.body.slice(a, b) || 'text';
                    s.body = s.body.slice(0, a) + mark + picked + mark + s.body.slice(b);
                    this.$nextTick(() => { t.focus(); t.setSelectionRange(a + mark.length, a + mark.length + picked.length); });
                },

                prefix(i, kind) {
                    const t = this.textarea(i); const s = this.sections[i];
                    const start = s.body.lastIndexOf('\n', t.selectionStart - 1) + 1;
                    const end = t.selectionEnd;
                    let n = 0;
                    const lines = s.body.slice(start, end || start).split('\n').map((line) => (kind === 'n' ? (++n) + '. ' : kind) + line);
                    s.body = s.body.slice(0, start) + lines.join('\n') + s.body.slice(end || start);
                    this.$nextTick(() => t.focus());
                },

                link(i) {
                    const t = this.textarea(i); const s = this.sections[i];
                    const [a, b] = [t.selectionStart, t.selectionEnd];
                    const label = s.body.slice(a, b) || 'link text';
                    const snippet = '[' + label + '](https://)';
                    s.body = s.body.slice(0, a) + snippet + s.body.slice(b);
                    // Select the address so it can be typed over straight away.
                    this.$nextTick(() => { t.focus(); const at = a + label.length + 3; t.setSelectionRange(at, at + 8); });
                },

                togglePreview(s) {
                    s.preview = !s.preview;
                    if (s.preview) this.renderPreview(s);
                },

                async renderPreview(s) {
                    s.previewHtml = '';
                    try {
                        const res = await window.axios.post(initial.previewUrl, { body: s.body });
                        s.previewHtml = res.data.html || '<p class="text-ink-400">Nothing to show yet.</p>';
                    } catch (e) {
                        s.previewHtml = '<p class="text-red-600">Preview failed - your text is not affected.</p>';
                    }
                },
            }));
        });
    </script>
</x-app-layout>

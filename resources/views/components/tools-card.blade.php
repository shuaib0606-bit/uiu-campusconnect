{{--
    Student tools for the feed rail: CGPA calculator, retake estimator and
    tuition estimator. All client-side — the student types their own numbers,
    nothing is sent to the server or stored on it.

    The grade scale and waiver tiers come from config/grading.php, so this
    file never hard-codes a grade point or a percentage.
--}}
@php
    $scale = config('grading.scale');
    $grades = array_map(fn ($r) => ['grade' => $r['grade'], 'point' => $r['point']], $scale);
    $waiver = config('grading.waiver');
    $minCgpa = config('grading.degree_min_cgpa');
@endphp

<div class="rail-card" x-data="ccTools(@js($grades), @js($waiver), @js($minCgpa))">
    <h3 class="mb-1">Student tools</h3>
    <p class="section-sub mb-3">UIU grading. Your numbers stay on your device.</p>

    {{-- Tool switcher --}}
    <div class="mb-4 grid grid-cols-3 gap-1 rounded-xl bg-ink-900/[0.04] p-1 dark:bg-white/[0.05]">
        <template x-for="t in tools" :key="t.id">
            <button type="button" @click="tool = t.id"
                :class="tool === t.id ? 'bg-white text-ink-900 shadow-sm dark:bg-ink-700 dark:text-white' : 'text-ink-500 dark:text-ink-400'"
                class="rounded-lg px-2 py-1.5 text-xs font-semibold transition" x-text="t.label"></button>
        </template>
    </div>

    {{-- ============ CGPA ============ --}}
    <div x-show="tool === 'cgpa'" class="space-y-3">
        <div class="space-y-2">
            <template x-for="(c, i) in courses" :key="i">
                <div class="flex items-center gap-2">
                    <input type="number" min="0" max="6" step="0.5" x-model.number="c.credit" @input="save()"
                        class="field w-16 !py-1.5 text-center text-sm" aria-label="Credits">
                    <select x-model="c.grade" @change="save()" class="field flex-1 !py-1.5 text-sm">
                        <template x-for="g in grades" :key="g.grade">
                            <option :value="g.grade" x-text="g.grade + '  (' + g.point.toFixed(2) + ')'"></option>
                        </template>
                    </select>
                    <button type="button" @click="courses.splice(i, 1); save()" class="btn-icon shrink-0" aria-label="Remove course">
                        <x-ui-icon name="x" class="h-4 w-4" />
                    </button>
                </div>
            </template>
        </div>
        <button type="button" @click="courses.push({ credit: 3, grade: 'A' }); save()"
            class="w-full rounded-xl border border-dashed border-ink-300 py-2 text-xs font-semibold text-ink-500 hover:border-primary-400 hover:text-primary-600 dark:border-ink-700">
            + Add course
        </button>

        <div class="rounded-xl bg-primary-500/10 px-4 py-3 text-center">
            <div class="text-xs font-medium uppercase tracking-wider text-primary-700 dark:text-primary-300">This trimester</div>
            <div class="text-3xl font-bold text-primary-700 dark:text-primary-200" x-text="termGpa"></div>
            <div class="text-xs text-ink-500 dark:text-ink-400"><span x-text="termCredits"></span> credits</div>
        </div>

        {{-- Cumulative --}}
        <details class="group rounded-xl border border-ink-200 dark:border-ink-700">
            <summary class="cursor-pointer list-none px-4 py-2.5 text-sm font-semibold">Add my past record →</summary>
            <div class="space-y-2 px-4 pb-3">
                <label class="block text-xs font-medium text-ink-500">Credits completed so far
                    <input type="number" min="0" x-model.number="priorCredits" @input="save()" class="field mt-1 w-full !py-1.5 text-sm"></label>
                <label class="block text-xs font-medium text-ink-500">Current CGPA
                    <input type="number" min="0" max="4" step="0.01" x-model.number="priorCgpa" @input="save()" class="field mt-1 w-full !py-1.5 text-sm"></label>
                <div class="rounded-lg bg-ink-900/[0.04] px-3 py-2 text-center dark:bg-white/[0.05]" x-show="priorCredits > 0">
                    <span class="text-xs text-ink-500">New CGPA</span>
                    <div class="text-xl font-bold" x-text="newCgpa"></div>
                </div>
            </div>
        </details>

        {{-- Target --}}
        <details class="group rounded-xl border border-ink-200 dark:border-ink-700">
            <summary class="cursor-pointer list-none px-4 py-2.5 text-sm font-semibold">Reach a target CGPA →</summary>
            <div class="space-y-2 px-4 pb-3">
                <label class="block text-xs font-medium text-ink-500">Target CGPA
                    <input type="number" min="0" max="4" step="0.01" x-model.number="targetCgpa" class="field mt-1 w-full !py-1.5 text-sm"></label>
                <p class="text-sm" x-html="targetMessage"></p>
            </div>
        </details>
    </div>

    {{-- ============ RETAKE ============ --}}
    <div x-show="tool === 'retake'" class="space-y-3">
        <p class="section-sub">See your CGPA if you retake a course and improve the grade. UIU marks a retake <b>(R)</b> and counts the improved grade.</p>
        <label class="block text-xs font-medium text-ink-500">Current CGPA
            <input type="number" min="0" max="4" step="0.01" x-model.number="rCgpa" class="field mt-1 w-full !py-1.5 text-sm"></label>
        <label class="block text-xs font-medium text-ink-500">Total credits graded so far
            <input type="number" min="0" x-model.number="rCredits" class="field mt-1 w-full !py-1.5 text-sm"></label>
        <div class="grid grid-cols-3 gap-2">
            <label class="block text-xs font-medium text-ink-500">Credit
                <input type="number" min="0" max="6" step="0.5" x-model.number="rCourseCredit" class="field mt-1 w-full !py-1.5 text-center text-sm"></label>
            <label class="block text-xs font-medium text-ink-500">Old grade
                <select x-model="rOld" class="field mt-1 w-full !py-1.5 text-sm">
                    <template x-for="g in grades" :key="g.grade"><option :value="g.grade" x-text="g.grade"></option></template>
                </select></label>
            <label class="block text-xs font-medium text-ink-500">New grade
                <select x-model="rNew" class="field mt-1 w-full !py-1.5 text-sm">
                    <template x-for="g in grades" :key="g.grade"><option :value="g.grade" x-text="g.grade"></option></template>
                </select></label>
        </div>
        <div class="rounded-xl bg-emerald-500/10 px-4 py-3 text-center">
            <div class="text-xs font-medium uppercase tracking-wider text-emerald-700 dark:text-emerald-300">CGPA after retake</div>
            <div class="text-3xl font-bold text-emerald-700 dark:text-emerald-200" x-text="retakeCgpa"></div>
            <div class="text-xs text-ink-500 dark:text-ink-400" x-html="retakeDelta"></div>
        </div>
    </div>

    {{-- ============ TUITION ============ --}}
    <div x-show="tool === 'tuition'" class="space-y-3">
        <p class="section-sub">Estimate a trimester's cost. Enter the per-credit tuition from your own fee sheet — it changes over time and by programme.</p>
        <div class="grid grid-cols-2 gap-2">
            <label class="block text-xs font-medium text-ink-500">Credits
                <input type="number" min="0" x-model.number="tCredits" class="field mt-1 w-full !py-1.5 text-sm"></label>
            <label class="block text-xs font-medium text-ink-500">Tk / credit
                <input type="number" min="0" x-model.number="tPerCredit" class="field mt-1 w-full !py-1.5 text-sm"></label>
        </div>
        <label class="block text-xs font-medium text-ink-500">Other fees this trimester (registration, etc.)
            <input type="number" min="0" x-model.number="tOther" class="field mt-1 w-full !py-1.5 text-sm"></label>
        <label class="block text-xs font-medium text-ink-500">Result-based waiver
            <select x-model.number="tWaiver" class="field mt-1 w-full !py-1.5 text-sm">
                <option :value="0">No waiver</option>
                <template x-for="w in waiver.tiers" :key="w.percent">
                    <option :value="w.percent" x-text="w.label + ' — ' + w.percent + '%'"></option>
                </template>
            </select></label>

        <div class="space-y-1 rounded-xl bg-ink-900/[0.04] px-4 py-3 text-sm dark:bg-white/[0.05]">
            <div class="flex justify-between"><span class="text-ink-500">Tuition</span><span x-text="money(tuition)"></span></div>
            <div class="flex justify-between text-emerald-600 dark:text-emerald-400" x-show="tWaiver > 0"><span>Waiver (<span x-text="tWaiver"></span>%)</span><span x-text="'− ' + money(waiverAmount)"></span></div>
            <div class="flex justify-between"><span class="text-ink-500">Other fees</span><span x-text="money(tOther || 0)"></span></div>
            <div class="mt-1 flex justify-between border-t border-ink-200 pt-1.5 text-base font-bold dark:border-ink-700"><span>You pay</span><span x-text="money(netCost)"></span></div>
        </div>
        <p class="flex items-start gap-1.5 text-xs text-ink-400" x-show="tWaiver > 0">
            <x-ui-icon name="lock" class="mt-px h-3.5 w-3.5 shrink-0" />
            <span x-text="waiver.note"></span>
        </p>
    </div>

    <p class="mt-4 border-t border-ink-200 pt-3 text-[11px] text-ink-400 dark:border-ink-800">
        A study aid, not an official record. Check UCAM for your real results and fees.
    </p>
</div>

@once
    @push('scripts')
    <script>
        window.ccTools = (grades, waiver, minCgpa) => ({
            grades, waiver, minCgpa,
            tools: [{ id: 'cgpa', label: 'CGPA' }, { id: 'retake', label: 'Retake' }, { id: 'tuition', label: 'Tuition' }],
            tool: 'cgpa',
            courses: [{ credit: 3, grade: 'A' }, { credit: 3, grade: 'A-' }, { credit: 3, grade: 'B+' }],
            priorCredits: 0, priorCgpa: 0, targetCgpa: 3.50,
            rCgpa: 0, rCredits: 0, rCourseCredit: 3, rOld: 'F', rNew: 'B+',
            tCredits: 12, tPerCredit: 0, tOther: 0, tWaiver: 0,

            init() {
                try {
                    const s = JSON.parse(localStorage.getItem('cc.tools') || '{}');
                    if (Array.isArray(s.courses) && s.courses.length) this.courses = s.courses;
                    if (typeof s.priorCredits === 'number') this.priorCredits = s.priorCredits;
                    if (typeof s.priorCgpa === 'number') this.priorCgpa = s.priorCgpa;
                } catch (e) { /* storage unavailable — start fresh */ }
            },
            save() {
                try {
                    localStorage.setItem('cc.tools', JSON.stringify({ courses: this.courses, priorCredits: this.priorCredits, priorCgpa: this.priorCgpa }));
                } catch (e) { /* ignore */ }
            },

            point(g) { const r = this.grades.find((x) => x.grade === g); return r ? r.point : 0; },
            get termCredits() { return this.courses.reduce((s, c) => s + (Number(c.credit) || 0), 0); },
            get termPoints() { return this.courses.reduce((s, c) => s + (Number(c.credit) || 0) * this.point(c.grade), 0); },
            get termGpa() { return this.termCredits ? (this.termPoints / this.termCredits).toFixed(2) : '—'; },
            get newCgpa() {
                const pc = Number(this.priorCredits) || 0, tc = this.termCredits;
                if (pc + tc === 0) return '—';
                return (((Number(this.priorCgpa) || 0) * pc + this.termPoints) / (pc + tc)).toFixed(2);
            },
            get targetMessage() {
                const pc = Number(this.priorCredits) || 0, tc = this.termCredits, tgt = Number(this.targetCgpa) || 0;
                if (!tc) return 'Add this trimester\'s courses first.';
                const need = (tgt * (pc + tc) - (Number(this.priorCgpa) || 0) * pc) / tc;
                if (need > 4.0001) return `Not reachable this trimester — you\'d need a <b>${need.toFixed(2)}</b> GPA (max is 4.00).`;
                if (need < 0) return `You\'re already there — even a <b>0.00</b> this trimester keeps you above ${tgt.toFixed(2)}.`;
                return `You need a <b>${need.toFixed(2)}</b> GPA across these <b>${tc}</b> credits this trimester.`;
            },

            get retakeCgpa() {
                const c = Number(this.rCredits) || 0; if (!c) return '—';
                const total = (Number(this.rCgpa) || 0) * c;
                const adjusted = total - this.point(this.rOld) * (Number(this.rCourseCredit) || 0) + this.point(this.rNew) * (Number(this.rCourseCredit) || 0);
                return (adjusted / c).toFixed(2);
            },
            get retakeDelta() {
                if (this.retakeCgpa === '—') return 'Enter your totals above.';
                const d = (Number(this.retakeCgpa) - (Number(this.rCgpa) || 0));
                if (Math.abs(d) < 0.005) return 'No change.';
                const up = d > 0;
                return `<span class="${up ? 'text-emerald-600' : 'text-red-500'}">${up ? '▲' : '▼'} ${Math.abs(d).toFixed(2)}</span> from ${(Number(this.rCgpa) || 0).toFixed(2)}`;
            },

            get tuition() { return (Number(this.tCredits) || 0) * (Number(this.tPerCredit) || 0); },
            get waiverAmount() { return this.tuition * (Number(this.tWaiver) || 0) / 100; },
            get netCost() { return this.tuition - this.waiverAmount + (Number(this.tOther) || 0); },
            money(n) { return 'Tk ' + Math.round(n).toLocaleString('en-US'); },
        });
    </script>
    @endpush
@endonce

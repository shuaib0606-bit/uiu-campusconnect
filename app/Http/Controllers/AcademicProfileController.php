<?php

namespace App\Http\Controllers;

use App\Models\FacultyProfileSection;
use App\Support\Visibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Where a faculty member edits the Academic tab of their profile.
 *
 * The whole list is saved at once - titles, text, order and visibility -
 * because reordering and editing happen together on one page. Rules the
 * website sync depends on:
 *
 *  - changing the title or text of a website section marks it edited, and
 *    the sync leaves it alone from then on; changing only its position or
 *    visibility does not
 *  - "Restore website version" puts the website text back and clears that
 *    mark, so the section follows the website again
 *  - deleting a website section hides it (removed_at) rather than deleting
 *    the row, so the next sync does not bring it back; deleting a section
 *    the person added removes it for good
 */
class AcademicProfileController extends Controller
{
    public const PRESETS = [
        'Biography', 'Education', 'Research interests', 'Experience', 'Publications',
        'Courses taught', 'Supervision', 'Awards & honours', 'Professional service', 'Links',
    ];

    public function edit(Request $request)
    {
        $user = $this->academicUser($request);

        $all = FacultyProfileSection::where('user_id', $user->id)->orderBy('position')->get();
        $shape = fn (FacultyProfileSection $s) => [
            'id' => $s->id,
            'title' => $s->title,
            'body' => $s->body,
            'visibility' => $s->visibility,
            'website' => $s->isFromWebsite(),
            'locked' => $s->isLocked(),
            'websiteBody' => $s->isFromWebsite() ? $s->website_body : null,
        ];

        return view('profile.academic', [
            'user' => $user->load('profile'),
            'sections' => $all->whereNull('removed_at')->values()->map($shape),
            'removed' => $all->whereNotNull('removed_at')->values()->map($shape),
            'presets' => self::PRESETS,
            'levels' => Visibility::LABELS,
        ]);
    }

    public function update(Request $request)
    {
        $user = $this->academicUser($request);

        $data = $request->validate([
            'sections' => ['nullable', 'array', 'max:40'],
            'sections.*.id' => ['nullable', 'integer'],
            'sections.*.title' => ['required', 'string', 'max:150'],
            'sections.*.body' => ['nullable', 'string', 'max:300000'],
            'sections.*.visibility' => ['required', Rule::in(Visibility::ALL)],
            'sections.*.restore' => ['nullable', 'boolean'],
        ], [
            'sections.*.title.required' => 'Every section needs a title.',
        ]);

        // Only this person's own rows can be touched, whatever ids are posted.
        $existing = FacultyProfileSection::where('user_id', $user->id)->get()->keyBy('id');
        $kept = [];

        DB::transaction(function () use ($data, $existing, $user, &$kept) {
            foreach (array_values($data['sections'] ?? []) as $position => $input) {
                $title = trim($input['title']);
                $body = (string) ($input['body'] ?? '');
                $row = isset($input['id']) ? $existing->get((int) $input['id']) : null;

                if (! $row) {
                    FacultyProfileSection::create([
                        'user_id' => $user->id,
                        'title' => $title,
                        'body' => $body,
                        'position' => $position,
                        'visibility' => $input['visibility'],
                        'source' => FacultyProfileSection::CUSTOM,
                    ]);

                    continue;
                }

                $kept[$row->id] = true;
                $changed = $row->title !== $title || $row->body !== $body;
                $restored = ! empty($input['restore']) && $row->isFromWebsite() && $body === (string) $row->website_body;

                $row->fill([
                    'title' => $title,
                    'body' => $body,
                    'position' => $position,
                    'visibility' => $input['visibility'],
                    'removed_at' => null,
                ]);

                if ($row->isFromWebsite()) {
                    if ($restored) {
                        $row->edited_at = null;
                    } elseif ($changed) {
                        $row->edited_at = now();
                    }
                }

                $row->save();
            }

            foreach ($existing as $id => $row) {
                if (isset($kept[$id]) || $row->removed_at !== null) {
                    continue;
                }

                $row->isFromWebsite()
                    ? $row->forceFill(['removed_at' => now()])->save()
                    : $row->delete();
            }
        });

        return redirect()->route('academic.edit')->with('success', 'Academic profile saved.');
    }

    /** The same rendering the profile page uses, so the preview is exact. */
    public function preview(Request $request)
    {
        $this->academicUser($request);

        $data = $request->validate(['body' => ['nullable', 'string', 'max:300000']]);

        return response()->json(['html' => FacultyProfileSection::render($data['body'] ?? '')]);
    }

    private function academicUser(Request $request)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, ['faculty', 'official'], true), 403, 'The academic profile is for faculty and university staff.');

        return $user;
    }
}

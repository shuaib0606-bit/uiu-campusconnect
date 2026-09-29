<?php

namespace App\Support;

/**
 * Shared validation for the report forms (posts, comments, profiles).
 *
 * The reason is picked from a list. When it is "Something else", the list
 * says nothing about the problem, so the reporter has to describe it.
 */
class ReportReasons
{
    public const OTHER = 'Something else';

    public const DETAILS_MAX = 700;

    public static function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:'.self::DETAILS_MAX, 'required_if:reason,'.self::OTHER],
        ];
    }

    public static function messages(): array
    {
        return [
            'details.required_if' => 'Please describe what is wrong so the moderators know what to look for.',
            'details.max' => 'Please keep the description under '.self::DETAILS_MAX.' characters.',
        ];
    }

    /** Validated reason and details, with blank details stored as null. */
    public static function validate(\Illuminate\Http\Request $request): array
    {
        if ($request->has('details')) {
            $request->merge(['details' => trim((string) $request->input('details'))]);
        }

        $data = $request->validate(self::rules(), self::messages());
        $data['details'] = ($data['details'] ?? '') !== '' ? $data['details'] : null;

        return $data;
    }
}

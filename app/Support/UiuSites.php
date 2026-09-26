<?php

namespace App\Support;

use App\Models\User;
use Throwable;

/**
 * The university's own websites that students keep needing, in one place:
 * what each is for, and - where the academic calendar knows - when it
 * will matter next. Shown from the "UIU Official Sites" menu item in both
 * sidebars (Blade and React), so it is defined once here.
 */
class UiuSites
{
    /** @return list<array{key: string, name: string, host: string, url: string, tagline: string, uses: list<string>, icon: string, tone: string, hint: ?string}> */
    public static function all(?User $user): array
    {
        $sites = [
            [
                'key' => 'advising',
                'name' => 'UCAM Cloud',
                'host' => 'uiu.ucamcloud.com',
                'url' => 'https://uiu.ucamcloud.com/login',
                'tagline' => 'Course advising and registration',
                'uses' => ['Choose and register your courses each term', 'See which sections are offered and their times', 'Add or drop courses before the deadline'],
                'icon' => 'edit',
                'tone' => 'sky',
                'hint' => self::next($user, ['registration']),
            ],
            [
                'key' => 'ems',
                'name' => 'UCAM EMS',
                'host' => 'ucam.uiu.ac.bd',
                'url' => 'https://ucam.uiu.ac.bd/Security/LogIn.aspx',
                'tagline' => 'Your student record',
                'uses' => ['Results and grade history', 'Tuition payments and dues', 'Your academic records'],
                'icon' => 'chart-bar',
                'tone' => 'emerald',
                'hint' => self::next($user, ['payment']),
            ],
            [
                'key' => 'exams',
                'name' => 'Exam Controller',
                'host' => 'examcon.uiu.ac.bd',
                'url' => 'https://examcon.uiu.ac.bd/',
                'tagline' => 'Exam schedule and rooms',
                'uses' => ['When each of your course exams is', 'Which room to sit each exam in', 'Mid-term and final schedules'],
                'icon' => 'file-text',
                'tone' => 'rose',
                'hint' => self::next($user, ['exam']),
            ],
        ];

        return $sites;
    }

    /** "Final Exam · Oct 10 - Oct 17" from the person's own calendar, if anything is coming. */
    private static function next(?User $user, array $types): ?string
    {
        try {
            $row = AcademicCalendar::upcoming(1, $user, $types)[0] ?? null;
        } catch (Throwable) {
            return null;
        }

        if (! $row) {
            return null;
        }

        $when = $row['start']->format('M j').($row['is_range'] ? ' - '.$row['end']->format('M j') : '');

        return $row['label'].' · '.($row['in_progress'] ? 'on now, until '.$row['end']->format('M j') : $when);
    }
}

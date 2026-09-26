<?php

/**
 * Fallback academic calendar.
 *
 * The real calendars - undergraduate, graduate and B.Pharm, every term - come
 * from uiu.ac.bd via `php artisan uiu:import-calendars` and live in the
 * academic_calendars table. This hand-typed list (Summer 2026 undergraduate)
 * is only used until that command has run once.
 *
 * Each milestone:
 *   date        Y-m-d, the day it happens (or the first day of a range)
 *   end_date    optional Y-m-d, for multi-day periods like exam weeks
 *   label       short, human-facing
 *   type        exam | payment | academic | holiday  (drives icon + colour)
 */

return [

    'trimester' => 'Summer 2026',

    'source_url' => 'https://www.uiu.ac.bd/academics/calendar/summer-2026-trimester-undergraduate-programs/',

    'milestones' => [
        ['date' => '2026-07-07', 'label' => 'Classes Begin', 'type' => 'academic'],
        ['date' => '2026-08-11', 'label' => '1st Instalment Due (40%)', 'type' => 'payment'],
        ['date' => '2026-08-22', 'end_date' => '2026-08-29', 'label' => 'Mid-Term Examinations', 'type' => 'exam'],
        ['date' => '2026-08-26', 'label' => 'Holiday — Eid-e-Miladunnabi', 'type' => 'holiday'],
        ['date' => '2026-09-04', 'label' => 'Holiday — Janmashtami', 'type' => 'holiday'],
        ['date' => '2026-09-09', 'label' => 'Last Day of Course Withdrawal', 'type' => 'academic'],
        ['date' => '2026-09-15', 'label' => '2nd Instalment Due (70%)', 'type' => 'payment'],
        ['date' => '2026-10-06', 'label' => '3rd Instalment Due (100%)', 'type' => 'payment'],
        ['date' => '2026-10-10', 'end_date' => '2026-10-17', 'label' => 'Final Examinations', 'type' => 'exam'],
        ['date' => '2026-10-20', 'end_date' => '2026-10-21', 'label' => 'Holiday — Durga Puja', 'type' => 'holiday'],
        ['date' => '2026-10-22', 'label' => 'Last Day of Grade Submission', 'type' => 'academic'],
        ['date' => '2026-10-27', 'label' => 'Fall 2026 Trimester Begins', 'type' => 'academic'],
    ],

];

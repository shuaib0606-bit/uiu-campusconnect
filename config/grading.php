<?php

/**
 * UIU grading and fee reference — the single source of truth for the
 * student tools (CGPA calculator, retake estimator, tuition estimator).
 *
 * UIU publishes ONE university-wide letter-grade scale, so `scale` below is
 * used for every department. If a department ever diverges, this is the only
 * place to change — nothing else hard-codes a grade point.
 *
 * Source: https://www.uiu.ac.bd/academics/grading-performance-evaluation/
 * Waiver: https://www.uiu.ac.bd/admission/tuition-fees-payment-policies/scholarship-tuition-fee-and-other-fees-waiver-policy/
 * Verified 27 Sep 2026. If UIU updates either page, update this file.
 */

return [

    // High to low. `min`/`max` are inclusive percentage marks.
    'scale' => [
        ['grade' => 'A',  'point' => 4.00, 'min' => 90, 'max' => 100],
        ['grade' => 'A-', 'point' => 3.67, 'min' => 86, 'max' => 89],
        ['grade' => 'B+', 'point' => 3.33, 'min' => 82, 'max' => 85],
        ['grade' => 'B',  'point' => 3.00, 'min' => 78, 'max' => 81],
        ['grade' => 'B-', 'point' => 2.67, 'min' => 74, 'max' => 77],
        ['grade' => 'C+', 'point' => 2.33, 'min' => 70, 'max' => 73],
        ['grade' => 'C',  'point' => 2.00, 'min' => 66, 'max' => 69],
        ['grade' => 'C-', 'point' => 1.67, 'min' => 62, 'max' => 65],
        ['grade' => 'D+', 'point' => 1.33, 'min' => 58, 'max' => 61],
        ['grade' => 'D',  'point' => 1.00, 'min' => 55, 'max' => 57],
        ['grade' => 'F',  'point' => 0.00, 'min' => 0,  'max' => 54],
    ],

    // The lowest passing grade. F earns no credit until the course is retaken.
    'pass_point' => 1.00,
    'degree_min_cgpa' => 2.00,

    // Result-based tuition waiver ("scholarship on trimester result"). This is
    // RANK-based, not a fixed GPA cut-off: a student qualifies by their rank
    // within their programme, and every tier needs at least GPA 3.50. The tool
    // shows these as reference and lets the student pick a tier to model cost.
    'waiver' => [
        'min_gpa' => 3.50,
        'tiers' => [
            ['label' => 'Top 2% of programme',   'percent' => 100],
            ['label' => 'Next 4% (rank 3–6%)',   'percent' => 50],
            ['label' => 'Next 4% (rank 7–10%)',  'percent' => 25],
        ],
        // Minimum credits that must be registered in the qualifying trimester.
        'min_credits' => ['default' => 9, 'B.Pharm' => 14],
        'excludes' => 'Thesis, Project, Internship, Retake and Repeat courses do not count and are not waived.',
        'note' => 'Result-based waivers are awarded by rank within your programme (not by a fixed GPA), and need at least GPA 3.50. Treat the figure above as an estimate for the tier you pick.',
    ],

];

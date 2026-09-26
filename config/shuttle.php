<?php

/**
 * UIU student transport timetable.
 *
 * Everything here is transcribed from what UIU has published (checked 26 Sep
 * 2026). Nothing is invented: where UIU has not given a time, there is no
 * time here.
 *
 *   notun-bazar   "Time Schedule of Shuttle Services" page - the regular
 *                 free shuttle.
 *   ewu-gate      Notice "Timings for New Shuttle Service (UIU-EWU-UIU)",
 *                 27 Sep 2025 - the Aftab Nagar route. Two of its slots are
 *                 continuous (buses shuttle back and forth for the whole
 *                 window), recorded as depart + window_until + continuous.
 *   ac-*          The AC bus routes (registration required): stops from the
 *                 "UIU Transportation Service" page, return times from the
 *                 notice of 23 Aug 2026. UIU calls both return times
 *                 tentative and has not published the morning pick-up times,
 *                 so only the trips from campus are listed. From Fall 2026
 *                 the AC buses move to pay-per-trip; UIU says a separate
 *                 notice will follow - update this file then.
 *
 * UIU also lists Kuril as a new shuttle route but has published no times
 * for it, so it is only mentioned under other_services.
 *
 * This is a published timetable, not live tracking. Anything built on it can
 * say "next scheduled departure in N minutes"; it cannot say where a bus is.
 *
 * Times are 24-hour "H:i" in the timezone below. Where UIU gives a window
 * ("07.30 AM - 8.45 AM") the start is the departure and the end is kept in
 * window_until.
 */

return [

    'timezone' => 'Asia/Dhaka',

    'routes' => [

        'notun-bazar' => [
            'name' => 'Notun Bazar — UIU',
            'kind' => 'shuttle',
            'stops' => ['Notun Bazar', 'Family Bazar', 'Sayed Nagar', '10 Tala', 'UIU Campus'],

            // From Notun Bazar.
            'inbound' => [
                ['depart' => '07:30', 'window_until' => '08:45'],
                ['depart' => '09:25', 'window_until' => '09:35'],
                ['depart' => '10:45', 'window_until' => '10:55'],
                ['depart' => '12:05', 'window_until' => '12:15'],
                ['depart' => '13:25', 'window_until' => '13:35'],
                ['depart' => '14:45', 'window_until' => '14:55'],
                ['depart' => '18:10'],
            ],

            // From UIU.
            'outbound' => [
                ['depart' => '10:05'],
                ['depart' => '11:25'],
                ['depart' => '12:45'],
                ['depart' => '14:05'],
                ['depart' => '15:25'],
                ['depart' => '16:40'],
                ['depart' => '17:45'],
                ['depart' => '19:00'],
                ['depart' => '21:40'],
            ],
        ],

        'ewu-gate' => [
            'name' => 'Aftab Nagar (EWU Gate) — UIU',
            'kind' => 'shuttle',
            'stops' => ['East West University Gate', 'Lohar Bridge', 'Bottola', 'UIU Campus'],
            'note' => 'Runs via the Swadesh Property road.',

            // From EWU Gate.
            'inbound' => [
                ['depart' => '06:50', 'window_until' => '09:00', 'continuous' => true],
                ['depart' => '10:30'],
                ['depart' => '11:50'],
                ['depart' => '13:10'],
            ],

            // From UIU.
            'outbound' => [
                ['depart' => '14:00'],
                ['depart' => '15:20'],
                ['depart' => '16:40', 'window_until' => '18:00', 'continuous' => true],
            ],
        ],

        'ac-dhanmondi' => [
            'name' => 'AC bus: Dhanmondi',
            'kind' => 'ac',
            'stops' => ['Zigatola', 'Dhanmondi Keari Plaza', 'Shankar', 'Mohammadpur BRTC', 'Manik Mia Avenue', 'BARC', 'Farmgate', 'Kakoli', 'Gulshan 2', 'Notun Bazar', 'UIU Campus'],
            'note' => 'No stops between Farmgate and Kakoli. Returns by the Elevated Expressway.',
            'inbound' => [],
            'outbound' => [['depart' => '14:20', 'tentative' => true], ['depart' => '16:40', 'tentative' => true]],
        ],

        'ac-palashi' => [
            'name' => 'AC bus: Palashi',
            'kind' => 'ac',
            'stops' => ['Palashi', 'Azimpur', 'Dhaka College', 'City College', 'West Kalabagan', 'Panthapath', 'BARC', 'Farmgate', 'Kakoli', 'Gulshan 2', 'Notun Bazar', 'UIU Campus'],
            'note' => 'No stops between Farmgate and Kakoli. Returns by the Elevated Expressway.',
            'inbound' => [],
            'outbound' => [['depart' => '14:20', 'tentative' => true], ['depart' => '16:40', 'tentative' => true]],
        ],

        'ac-mirpur' => [
            'name' => 'AC bus: Mirpur',
            'kind' => 'ac',
            'stops' => ['Technical', 'Mirpur 1', 'Mirpur 2', 'Mirpur 10', 'Mirpur 11', 'Mirpur 12', 'ECB Chattar (Kalshi)', 'Kuril Flyover', 'Notun Bazar', 'UIU Campus'],
            'inbound' => [],
            'outbound' => [['depart' => '14:20', 'tentative' => true], ['depart' => '16:40', 'tentative' => true]],
        ],

        'ac-signboard' => [
            'name' => 'AC bus: Signboard',
            'kind' => 'ac',
            'stops' => ['Signboard Mor', 'Hanif Flyover', 'Manik Nagar', 'Mugdapara', 'Bashabo', 'Khilgaon Police Fari', 'Abul Hotel', 'Rampura Bridge', 'Aftab Nagar', 'Sunvally (Swadesh Property)', 'UIU Campus'],
            'inbound' => [],
            'outbound' => [['depart' => '14:20', 'tentative' => true], ['depart' => '16:40', 'tentative' => true]],
        ],

        'ac-jatrabari' => [
            'name' => 'AC bus: Jatrabari',
            'kind' => 'ac',
            'stops' => ['Jatrabari Mor', 'Manik Nagar', 'Mugdapara', 'Bashabo', 'Khilgaon Police Fari', 'Abul Hotel', 'Rampura Bridge', 'Aftab Nagar', 'Sunvally (Swadesh Property)', 'UIU Campus'],
            'inbound' => [],
            'outbound' => [['depart' => '14:20', 'tentative' => true], ['depart' => '16:40', 'tentative' => true]],
        ],

        'ac-uttara' => [
            'name' => 'AC bus: Uttara',
            'kind' => 'ac',
            'stops' => ['Abdullahpur', 'House Building', 'Azampur', 'Jashimuddin', 'Airport', 'Khilkhet', 'Kuril Flyover', '300 ft', 'Bashundhara', 'UIU Campus'],
            'inbound' => [],
            'outbound' => [['depart' => '14:20', 'tentative' => true], ['depart' => '16:40', 'tentative' => true]],
        ],
    ],

    // Given to the assistant with the AC bus routes (not shown on the card).
    'ac_note' => 'For students registered for UIU transport. Morning pick-up times are not published. UIU calls both return times tentative.',

    'other_services' => [
        ['name' => 'Kuril', 'note' => 'Listed by UIU as a new shuttle route; no times published yet.'],
    ],

];

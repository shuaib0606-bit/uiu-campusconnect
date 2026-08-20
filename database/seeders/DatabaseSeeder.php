<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Event;
use App\Models\Notice;
use App\Models\Post;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['full_name' => 'UIU CampusConnect Master Admin', 'email' => 'admin@uiu.ac.bd', 'role' => 'admin', 'authority_level' => 'developer', 'designation' => 'Master Administrator', 'is_verified' => true],
            ['full_name' => 'Vice Chancellor Office', 'email' => 'vc@uiu.ac.bd', 'role' => 'faculty', 'authority_level' => 'vc', 'designation' => 'Vice Chancellor', 'is_verified' => true],
            ['full_name' => 'Nahid Hossain', 'email' => 'nahid.hossain@cse.uiu.ac.bd', 'role' => 'faculty', 'authority_level' => 'club_moderator', 'department' => 'CSE', 'designation' => 'Lecturer', 'is_verified' => true],
            ['full_name' => 'Registrar Office', 'email' => 'registrar@uiu.ac.bd', 'role' => 'official', 'authority_level' => 'registrar', 'designation' => 'Registrar Official', 'is_verified' => true],
            ['full_name' => 'Student One', 'email' => '011233001@bscse.uiu.ac.bd', 'role' => 'student', 'department' => 'CSE', 'student_id' => '011233001', 'year' => '3rd Year'],
            ['full_name' => 'Student Two', 'email' => '011233002@bscse.uiu.ac.bd', 'role' => 'student', 'department' => 'CSE', 'student_id' => '011233002', 'year' => '2nd Year'],
        ];

        $users = [];
        foreach ($accounts as $a) {
            $user = User::create(array_merge([
                'password' => Hash::make('demo123'),
                'status' => 'active',
                'avatar_initials' => strtoupper(substr($a['full_name'], 0, 2)),
            ], $a));
            UserProfile::create(['user_id' => $user->id, 'bio' => 'Demo account for UIU CampusConnect.']);
            $users[$a['email']] = $user;
        }

        $admin = $users['admin@uiu.ac.bd'];
        $registrar = $users['registrar@uiu.ac.bd'];
        $moderator = $users['nahid.hossain@cse.uiu.ac.bd'];

        $club = Club::create([
            'name' => 'Computer Club',
            'slug' => 'computer-club',
            'category' => 'Technology',
            'description' => 'Programming contests, workshops, and tech talks for UIU students.',
            'moderator_id' => $moderator->id,
            'status' => 'active',
        ]);
        $club->members()->attach($moderator->id, ['club_role' => 'moderator', 'can_post_events' => true]);

        Event::create([
            'title' => 'Intra-University Programming Contest',
            'description' => 'Annual competitive programming contest open to all departments.',
            'category' => 'Competition',
            'start_datetime' => now()->addDays(10)->setTime(10, 0),
            'end_datetime' => now()->addDays(10)->setTime(16, 0),
            'location' => 'Auditorium, UIU',
            'capacity' => 200,
            'organizer_user_id' => $moderator->id,
            'club_id' => $club->id,
        ]);

        Notice::create([
            'title' => 'Semester Registration Opens Monday',
            'body' => 'Online registration for the upcoming semester opens Monday at 9:00 AM through the student portal.',
            'notice_type' => 'academic',
            'priority' => 'high',
            'audience' => 'all',
            'issuer_id' => $registrar->id,
        ]);

        Post::create([
            'user_id' => $users['011233001@bscse.uiu.ac.bd']->id,
            'body' => 'Excited for the programming contest next week! Who else is joining from CSE?',
        ]);
    }
}

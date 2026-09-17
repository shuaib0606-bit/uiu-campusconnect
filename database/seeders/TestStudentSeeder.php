<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;

/**
 * A handful of student accounts for testing the student side of the site.
 * Not real people. Remove them before the platform is used in earnest.
 */
class TestStudentSeeder extends Seeder
{
    private const PASSWORD = 'student123';

    private const STUDENTS = [
        ['Arpon Saha', '011233001@bscse.uiu.ac.bd', '011233001', 'Computer Science and Engineering', '4th Year'],
        ['Nusrat Jahan', '011233002@bscse.uiu.ac.bd', '011233002', 'Computer Science and Engineering', '3rd Year'],
        ['Tanvir Alam', '011233003@bscse.uiu.ac.bd', '011233003', 'Computer Science and Engineering', '2nd Year'],
        ['Farhana Yasmin', '021231045@bba.uiu.ac.bd', '021231045', 'Business Administration', '3rd Year'],
    ];

    public function run(): void
    {
        foreach (self::STUDENTS as [$name, $email, $studentId, $department, $year]) {
            $user = User::firstOrCreate(['email' => $email], [
                'full_name' => $name,
                'role' => 'student',
                'authority_level' => 'member',
                'department' => $department,
                'designation' => 'Student',
                'student_id' => $studentId,
                'year' => $year,
                'status' => 'active',
                'is_verified' => false,
                'avatar_initials' => $this->initials($name),
                'password' => self::PASSWORD,
                'password_changed_at' => now(),
            ]);

            UserProfile::firstOrCreate(['user_id' => $user->id]);
        }

        $this->command?->info(count(self::STUDENTS).' test students available, password '.self::PASSWORD);
    }

    private function initials(string $name): string
    {
        return collect(explode(' ', $name))
            ->filter()
            ->take(2)
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('') ?: 'UI';
    }
}

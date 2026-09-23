<?php

namespace App\Services\Ai\Tools;

use Illuminate\Support\Str;

/**
 * How the site works, so "how do I..." questions get this site's answer
 * rather than a plausible guess at how websites usually work.
 *
 * Kept as data in code rather than in the system prompt: sent only when
 * asked for, it costs nothing on the questions that do not need it.
 * Every entry describes behaviour that exists in the code; when a feature
 * changes, its entry here must change with it.
 */
class HowToTool extends Tool
{
    private const GUIDE = [
        [
            'title' => 'Signing in and forgotten passwords',
            'keywords' => 'sign in login log in password forgot reset locked account',
            'text' => 'Accounts are issued by the university; there is no public sign-up. If you are locked out, use "Forgot your password?" on the sign-in page and give your university email or ID, with anything that helps verify you. An administrator checks who you are and gives you a temporary password, which you must change the first time you sign in with it. An administrator will never ask for your password.',
        ],
        [
            'title' => 'Changing your password',
            'keywords' => 'change password new password settings security',
            'text' => 'Open Settings, then Password. You need your current password to set a new one.',
        ],
        [
            'title' => 'Your profile and privacy settings',
            'keywords' => 'profile edit bio photo cover privacy hide email phone student id friend list office hours skills',
            'text' => 'Open your profile and choose Edit (Settings, then Profile). You can set a bio, room, phone, skills and interests, and for teachers office hours, courses taught and research interests. Switches decide who sees your email, your student or employee ID, your phone number and your friend list. Email and ID are shown by default; phone is hidden by default. The assistant follows these same switches when anyone asks about you.',
        ],
        [
            'title' => 'Connecting with people',
            'keywords' => 'connect friend request follow unfollow network accept decline block mute',
            'text' => 'Open Network to find people. Connect sends a request the other person accepts or declines; Follow shows you their posts without needing their approval. From someone\'s profile menu you can block them (each of you disappears from the other) or mute them for a while (their posts stop appearing in your feed).',
        ],
        [
            'title' => 'Messaging',
            'keywords' => 'message dm chat inbox send text contact seller',
            'text' => 'Open Messages, or Message on someone\'s profile. Students can message people they are connected with, and anyone whose marketplace listing or lost-and-found report they are responding to. Teachers can message anyone. Nobody can message someone they have blocked or who has blocked them, and administrator accounts do not use messaging.',
        ],
        [
            'title' => 'Contacting a teacher you are not connected with',
            'keywords' => 'faculty teacher contact apply application connect request permission',
            'text' => 'Open Applications and apply to connect with a faculty member, saying what it is about. An administrator reviews the application first; once it is approved, the teacher sees it with your details and can get in touch.',
        ],
        [
            'title' => 'Posting on the feed',
            'keywords' => 'post share write photo document code snippet poll link audience visibility',
            'text' => 'Use the box at the top of the Feed. A post can carry photos, a document, a code snippet (coloured like a code editor), a poll or a link. Choose who sees it: everyone, your department, or only your connections. You can edit or delete your own posts, and save, hide or report anyone\'s.',
        ],
        [
            'title' => 'The feed tabs',
            'keywords' => 'feed tabs for you following trending department batch clubs order ranking',
            'text' => 'For You ranks posts by how recent they are, how close you are to the author, and what people you know are engaging with, and pushes down posts you have already seen. Following shows only people you follow, newest first. Trending is what the whole campus is engaging with over the last two days. Department, My Batch and My Clubs filter to those people.',
        ],
        [
            'title' => 'Joining a club',
            'keywords' => 'club join membership request leave member',
            'text' => 'Open Clubs, open the club, and request membership. The club\'s moderator or officers approve it. You can leave a club from its page.',
        ],
        [
            'title' => 'Club panels and officers',
            'keywords' => 'club panel president handover executive officer designation moderator nominate',
            'text' => 'A club\'s president proposes a new executive panel from the club page, and the club\'s faculty moderator approves it; the whole panel changes in one step. Officers can nominate members for roles. Only an administrator changes a club\'s moderator.',
        ],
        [
            'title' => 'Events',
            'keywords' => 'event register registration interested check in seats capacity cancel',
            'text' => 'Open Events. Register to take a seat - registration stops when the event is full - or mark yourself Interested. You can cancel your registration. On the day, check in from the event page.',
        ],
        [
            'title' => 'Notices',
            'keywords' => 'notice notices announcement board unread exam fee holiday',
            'text' => 'Open Notices for university announcements. A notice a teacher posts in a class reaches only the students in that class - it appears on their notice board and in the class group, and the count beside Groups in the sidebar shows class notices you have not opened.',
        ],
        [
            'title' => 'Class groups',
            'keywords' => 'class group course section enrolment materials slides archive',
            'text' => 'Every course section you are enrolled in has a class group, created for you - nobody joins or leaves one by hand. Inside are the wall, Materials (slides and files from the teacher), Notices, Assignments, and the class chat when the teacher has opened it. Twenty days after the trimester ends a class group becomes read-only; nothing is deleted.',
        ],
        [
            'title' => 'Handing in an assignment',
            'keywords' => 'assignment submit hand in upload deadline due late withdraw replace handout template',
            'text' => 'Open the class group, then Assignments. Download any handouts first. Upload your file with Hand in; you can replace it or withdraw it until the assignment closes. If the deadline has passed and the teacher allows late work, it is accepted and marked late. Only you and the class\'s staff can open what you hand in.',
        ],
        [
            'title' => 'Class chat',
            'keywords' => 'chat class chat conversation live group talk',
            'text' => 'Class chat appears in a class group when the teacher turns on Student communication. While it is off, students do not see the chat at all. A green dot means messages arrive instantly; amber means it is checking every few seconds - it keeps working either way.',
        ],
        [
            'title' => 'For teachers: assignments, materials and the chat',
            'keywords' => 'teacher faculty set assignment create new materials upload notice publish open chat communication submissions who submitted close reopen',
            'text' => 'In a class group: Assignments, then New assignment - title, instructions, optional deadline, accepted file types, size limit, whether late work is allowed, and up to five handouts. View submissions shows who has handed in, with late ones flagged; you can close or reopen submissions. Materials and Notices publish files and announcements to the class. Manage, then Student communication, opens the wall and the class chat to students.',
        ],
        [
            'title' => 'Marketplace',
            'keywords' => 'marketplace sell buy listing price sold book calculator',
            'text' => 'Open Marketplace to browse items students are selling, or create a listing with a photo, price, category and condition. Message the seller to arrange it. Mark your listing as sold when it is gone.',
        ],
        [
            'title' => 'Lost and found',
            'keywords' => 'lost found claim missing id card report reunited',
            'text' => 'Open Lost & Found to report something lost or found. If you see your item, claim it with a message; the person who posted it reviews claims and marks the case reunited when it is settled.',
        ],
        [
            'title' => 'Open groups',
            'keywords' => 'group create join leave open community private',
            'text' => 'Anyone can create an open group from Groups. A private open group needs its owner to approve each person who asks to join. Class and club groups are different: they follow enrolment and club membership automatically.',
        ],
        [
            'title' => 'Notifications, search and dark mode',
            'keywords' => 'notification bell search find dark mode theme light',
            'text' => 'The bell at the top shows notifications; you can mark them all read. The search bar finds people, posts, groups and notices. The moon icon switches between light and dark.',
        ],
        [
            'title' => 'What CampusConnect does not handle',
            'keywords' => 'grades results cgpa payment fee pay registration advising ucam transcript',
            'text' => 'Grades, fee payments and course registration are handled by UCAM, the university\'s official system - there is a UCAM EMS link in the sidebar. CampusConnect shows the academic calendar and fee due dates, but does not take payments or show results.',
        ],
        [
            'title' => 'This assistant',
            'keywords' => 'assistant ai what can you do help privacy data',
            'text' => 'The assistant answers from what you could already see on the site - your own classes, assignments, notices, messages and groups, and public information about people, clubs and events - plus general knowledge. It follows everyone\'s privacy settings and cannot see anything you could not open yourself.',
        ],
    ];

    public function name(): string
    {
        return 'how_to';
    }

    public function description(): string
    {
        return 'How to do something on CampusConnect, or how a feature works.';
    }

    public function parameters(): array
    {
        return self::object(['topic' => self::text('What the person wants to do, in a few words.')], ['topic']);
    }

    public function run(array $arguments): array
    {
        $words = collect(preg_split('/[^a-z0-9]+/', Str::lower($this->arg($arguments, 'topic'))))
            ->filter(fn ($w) => mb_strlen($w) > 2)
            ->unique();

        $scored = collect(self::GUIDE)->map(function ($entry) use ($words) {
            $haystack = ' '.Str::lower($entry['title'].' '.$entry['keywords']).' ';
            $score = $words->sum(fn ($w) => str_contains($haystack, ' '.$w) ? 1 : 0);

            return $entry + ['score' => $score];
        })->where('score', '>', 0)->sortByDesc('score')->take(2);

        if ($scored->isEmpty()) {
            return [
                'note' => 'No guide entry matches. Topics covered: '.collect(self::GUIDE)->pluck('title')->implode('; ').'.',
            ];
        }

        return ['guide' => $scored->map(fn ($e) => ['topic' => $e['title'], 'answer' => $e['text']])->values()->all()];
    }
}

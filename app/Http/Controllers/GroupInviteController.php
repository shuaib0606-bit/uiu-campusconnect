<?php

namespace App\Http\Controllers;

use App\Models\CommunityGroup;
use App\Models\GroupInvite;
use App\Support\GroupInvites;
use Illuminate\Http\Request;

/**
 * Inviting people to a group, and answering an invitation.
 *
 * The group's creator sends invitations from the group page; the person
 * invited answers on their Applications page. Nobody becomes a member until
 * they accept. The rules for who may be invited live in GroupInvites.
 */
class GroupInviteController extends Controller
{
    /** People matching a name, ID or email, for the invite box. */
    public function search(Request $request, CommunityGroup $group)
    {
        $inviter = $request->user();
        abort_unless(GroupInvites::canInviteTo($inviter, $group), 403);

        $data = $request->validate(['q' => ['nullable', 'string', 'max:60']]);

        return response()->json([
            'results' => GroupInvites::search($inviter, $group, (string) ($data['q'] ?? '')),
        ]);
    }

    /** The inviter's own classes this trimester. */
    public function courses(Request $request, CommunityGroup $group)
    {
        $inviter = $request->user();
        abort_unless(GroupInvites::canInviteTo($inviter, $group), 403);

        return response()->json(['courses' => GroupInvites::courses($inviter)]);
    }

    /** Classmates in one of those classes. */
    public function classmates(Request $request, CommunityGroup $group, int $section)
    {
        $inviter = $request->user();
        abort_unless(GroupInvites::canInviteTo($inviter, $group), 403);

        $people = GroupInvites::classmates($inviter, $group, $section);
        abort_if($people === null, 403, 'You can only pick classmates from a class you are taking.');

        return response()->json(['results' => $people]);
    }

    public function store(Request $request, CommunityGroup $group)
    {
        $inviter = $request->user();
        abort_unless(GroupInvites::canInviteTo($inviter, $group), 403, 'Only the person who made this group can invite people.');

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:'.GroupInvites::MAX_PER_REQUEST],
            'user_ids.*' => ['integer'],
        ]);

        $result = GroupInvites::send($inviter, $group, $data['user_ids']);

        $message = match (true) {
            $result['sent'] === 0 => 'No invitations were sent.',
            $result['sent'] === 1 => '1 invitation sent. They will join once they accept.',
            default => $result['sent'].' invitations sent. People join once they accept.',
        };

        if ($request->expectsJson()) {
            return response()->json([
                'sent' => $result['sent'],
                'skipped' => array_values($result['skipped']),
                'message' => $message,
            ]);
        }

        $flash = back()->with($result['sent'] > 0 ? 'success' : 'warning', $message);

        return $result['skipped']
            ? $flash->with('warning', 'Skipped: '.implode(' ', array_slice(array_values($result['skipped']), 0, 5)))
            : $flash;
    }

    /** The creator takes back an invitation nobody has answered yet. */
    public function cancel(Request $request, CommunityGroup $group, GroupInvite $invite)
    {
        abort_unless((int) $invite->group_id === (int) $group->id, 404);
        abort_unless($group->isOwnedBy($request->user()), 403);

        if ($invite->isPending()) {
            $invite->update(['status' => GroupInvite::CANCELLED, 'responded_at' => now()]);
        }

        return back()->with('success', 'Invitation withdrawn.');
    }

    public function accept(Request $request, GroupInvite $invite)
    {
        $this->authorizeAnswer($request, $invite);

        $group = $invite->group;

        if ($group->isArchived()) {
            $invite->update(['status' => GroupInvite::CANCELLED, 'responded_at' => now()]);

            return back()->with('error', 'That group has been archived, so the invitation no longer applies.');
        }

        GroupInvites::accept($invite);

        return redirect()->route('groups.show', $group)->with('success', 'You joined '.$group->name.'.');
    }

    public function decline(Request $request, GroupInvite $invite)
    {
        $this->authorizeAnswer($request, $invite);

        GroupInvites::decline($invite);

        return back()->with('success', 'Invitation declined.');
    }

    /** Only the person invited answers, and only while it is still open. */
    private function authorizeAnswer(Request $request, GroupInvite $invite): void
    {
        abort_unless((int) $invite->user_id === (int) $request->user()->id, 403);
        abort_unless($invite->isPending(), 410, 'This invitation has already been answered or withdrawn.');
    }
}

<?php

use App\Models\CommunityGroup;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * Who may listen to what.
 *
 * A private channel's name is not a secret - it is `group.7`, and anyone
 * can guess `group.8`. What stops them listening is this file: before a
 * browser is allowed to subscribe, Laravel asks the callback below, and
 * the callback asks the same membership question every page in the group
 * already asks.
 *
 * So the socket inherits the site's permissions rather than having its
 * own, weaker set. A student who cannot open a group's page cannot hear
 * its chat either.
 */
Broadcast::channel('group.{groupId}', function (User $user, int $groupId) {
    $group = CommunityGroup::find($groupId);

    // Pending members are not members yet, and hasMember() already knows
    // that - which is exactly why the check lives there and not here.
    return $group !== null && $group->hasMember($user);
});

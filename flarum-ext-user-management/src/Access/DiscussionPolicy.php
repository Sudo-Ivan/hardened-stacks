<?php

namespace HardenedStacks\UserManagement\Access;

use Flarum\Discussion\Discussion;
use Flarum\User\User;

class DiscussionPolicy
{
    public function delete(User $actor, Discussion $discussion)
    {
        if ($actor->hasPermission('pmg.purgeContent')) {
            return true;
        }
    }
}

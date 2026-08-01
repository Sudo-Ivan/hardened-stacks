<?php

namespace HardenedStacks\UserManagement\Access;

use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\User\User;

class PostPolicy
{
    public function delete(User $actor, Post $post)
    {
        if ($actor->hasPermission('pmg.purgeContent')) {
            return true;
        }
    }

    public function hide(User $actor, Post $post)
    {
        if ($actor->hasPermission('pmg.purgeContent')) {
            return true;
        }
    }
}

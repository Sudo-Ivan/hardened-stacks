<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

return Migration::addPermissions([
    'pmg.moderateUsers' => Group::MODERATOR_ID,
    'pmg.purgeContent' => Group::ADMINISTRATOR_ID,
    'pmg.deleteUsers' => Group::ADMINISTRATOR_ID,
]);

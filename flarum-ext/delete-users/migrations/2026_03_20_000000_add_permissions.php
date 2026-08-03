<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

return Migration::addPermissions([
    'pmg.deleteUsers' => Group::ADMINISTRATOR_ID,
]);

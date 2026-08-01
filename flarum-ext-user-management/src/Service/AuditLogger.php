<?php

namespace HardenedStacks\UserManagement\Service;

use Carbon\Carbon;
use Flarum\User\User;
use HardenedStacks\UserManagement\Model\ModerationLog;

class AuditLogger
{
    public function log(User $actor, ?User $target, string $action, array $details = []): void
    {
        ModerationLog::create([
            'actor_id' => $actor->id,
            'target_user_id' => $target?->id,
            'action' => $action,
            'details' => $details ? json_encode($details) : null,
            'created_at' => Carbon::now(),
        ]);
    }
}

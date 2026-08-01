<?php

namespace HardenedStacks\UserManagement\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;

class ModerationLog extends AbstractModel
{
    protected $table = 'pmg_moderation_log';

    public $timestamps = false;

    protected $dates = ['created_at'];

    protected $fillable = [
        'actor_id',
        'target_user_id',
        'action',
        'details',
        'created_at',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}

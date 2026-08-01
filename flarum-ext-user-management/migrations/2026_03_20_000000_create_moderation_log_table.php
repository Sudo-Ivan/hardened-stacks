<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('pmg_moderation_log')) {
            return;
        }

        $schema->create('pmg_moderation_log', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('actor_id');
            $table->unsignedInteger('target_user_id')->nullable();
            $table->string('action', 64);
            $table->text('details')->nullable();
            $table->timestamp('created_at');

            $table->index('actor_id');
            $table->index('target_user_id');
            $table->index('created_at');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('pmg_moderation_log');
    },
];

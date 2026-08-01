<?php

return [
    'up' => function ($schema) {
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
    'down' => function ($schema) {
        $schema->drop('pmg_moderation_log');
    },
];

<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('pmg_spam_audits')) {
            return;
        }

        $schema->create('pmg_spam_audits', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kind', 32);
            $table->unsignedInteger('post_id')->nullable()->index();
            $table->unsignedInteger('discussion_id')->nullable()->index();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->boolean('is_spam')->default(false);
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->string('actions', 255)->default('');
            $table->string('reason', 512)->default('');
            $table->string('status', 32)->default('ok');
            $table->text('error')->nullable();
            $table->dateTime('created_at');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('pmg_spam_audits');
    },
];

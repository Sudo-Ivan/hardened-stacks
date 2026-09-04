<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasColumn('users', 'lxmf_address')) {
            return;
        }

        $schema->table('users', function (Blueprint $table) {
            $table->string('lxmf_address', 64)->nullable()->unique();
        });
    },
    'down' => function (Builder $schema) {
        if (! $schema->hasColumn('users', 'lxmf_address')) {
            return;
        }

        $schema->table('users', function (Blueprint $table) {
            $table->dropColumn('lxmf_address');
        });
    },
];

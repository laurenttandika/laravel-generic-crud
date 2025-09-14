<?php

return [
    'auth_middleware' => ['auth:sanctum'],

    'csv' => [
        'delimiter' => ',',
        'enclosure' => '"',
        'escape'    => '\\',
        'chunk'     => 1000,
    ],

    'audit' => [
        'enabled' => true,
        'user_resolver' => fn () => auth()->id(),
        'log_changes' => true,
        'changes_table' => 'model_change_logs',
    ],
];

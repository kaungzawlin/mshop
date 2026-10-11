<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auth settings (env defaults; later admin-tunable)
    |--------------------------------------------------------------------------
    */

    'auth' => [
        'token_ttl_days' => (int) env('AUTH_TOKEN_TTL_DAYS', 30),
        'require_email_verification' => filter_var(
            env('AUTH_REQUIRE_EMAIL_VERIFICATION', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'login_max_attempts' => (int) env('AUTH_LOGIN_MAX_ATTEMPTS', 5),
        'login_decay_minutes' => (int) env('AUTH_LOGIN_DECAY_MINUTES', 15),
    ],

];

<?php

return [
    'ftth_isolation' => [
        'default_redirect_url' => env(
            'FTTH_ISOLATION_REDIRECT_URL',
            rtrim((string) env('APP_URL', 'http://localhost'), '/').'/isolir/ftth'
        ),
    ],
];

<?php

return [
    'logging' => [
        'channel' => env('AUTOMATION_LOG_CHANNEL', 'automation'),
    ],

    'queues' => [
        'billing' => env('AUTOMATION_QUEUE_BILLING', 'billing'),
        'provisioning' => env('AUTOMATION_QUEUE_PROVISIONING', 'provisioning'),
        'network' => env('AUTOMATION_QUEUE_NETWORK', 'network'),
        'monitoring' => env('AUTOMATION_QUEUE_MONITORING', 'monitoring'),
        'notifications' => env('AUTOMATION_QUEUE_NOTIFICATIONS', 'notifications'),
    ],

    'billing' => [
        'monthly_invoice' => [
            'due_in_days' => (int) env('BILLING_MONTHLY_INVOICE_DUE_IN_DAYS', 10),
            'penalty_amount' => env('BILLING_MONTHLY_INVOICE_PENALTY_AMOUNT', 0),
        ],
        'overdue' => [
            'chunk' => (int) env('BILLING_OVERDUE_SYNC_CHUNK', 200),
        ],
        'service_isolation' => [
            'chunk' => (int) env('BILLING_OVERDUE_ISOLATION_CHUNK', 100),
            'notes' => env(
                'BILLING_OVERDUE_ISOLATION_NOTES',
                'Generated automatically from overdue invoice scheduler.'
            ),
        ],
    ],

    'monitoring' => [
        'pppoe' => [
            'chunk' => (int) env('MONITOR_PPPPOE_SYNC_CHUNK', 200),
        ],
        'genieacs' => [
            'chunk' => (int) env('GENIEACS_SYNC_CHUNK', 100),
        ],
    ],

    'notifications' => [
        'telegram' => [
            'batch_limit' => (int) env('TELEGRAM_PROCESS_BATCH_LIMIT', 100),
        ],
    ],

    'schedule' => [
        'timezone' => env('AUTOMATION_SCHEDULE_TIMEZONE', env('APP_TIMEZONE', 'Asia/Jakarta')),
        'generate_monthly_invoices_at' => env('AUTOMATION_SCHEDULE_GENERATE_INVOICES_AT', '00:05'),
        'check_overdue_at' => env('AUTOMATION_SCHEDULE_CHECK_OVERDUE_AT', '00:20'),
        'create_overdue_isolations_at' => env('AUTOMATION_SCHEDULE_CREATE_OVERDUE_ISOLATIONS_AT', '00:30'),
        'sync_mikrotik_vid_cron' => env('AUTOMATION_SCHEDULE_SYNC_MIKROTIK_VID_CRON', '*/15 * * * *'),
        'sync_pppoe_cron' => env('AUTOMATION_SCHEDULE_SYNC_PPPOE_CRON', '* * * * *'),
        'sync_genieacs_cron' => env('AUTOMATION_SCHEDULE_SYNC_GENIEACS_CRON', '*/5 * * * *'),
        'process_telegram_cron' => env('AUTOMATION_SCHEDULE_PROCESS_TELEGRAM_CRON', '* * * * *'),
    ],
];

<?php

/*
 * Ready-made accounts for reviewers, created by DemoAccountsSeeder on setup and deploy, and listed
 * on the login page with a button that fills the form. Turn DEMO_ACCOUNTS off to hide them.
 */
return [
    'enabled' => (bool) env('DEMO_ACCOUNTS', true),

    'accounts' => [
        [
            'name' => 'Demo User',
            'email' => env('DEMO_USER_EMAIL', 'user@promptedit.test'),
            'password' => env('DEMO_USER_PASSWORD', 'demo-user-password'),
            'admin' => false,
        ],
        [
            'name' => 'Demo Admin',
            'email' => env('DEMO_ADMIN_EMAIL', 'admin@promptedit.test'),
            'password' => env('DEMO_ADMIN_PASSWORD', 'demo-admin-password'),
            'admin' => true,
        ],
    ],
];

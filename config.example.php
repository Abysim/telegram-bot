<?php

/**
 * This file contains all the configuration options for the PHP Telegram Bot.
 *
 * It is based on the configuration array of the PHP Telegram Bot Manager project.
 *
 * Simply adjust all the values that you need and extend where necessary.
 *
 * Options marked as [Manager Only] are only required if you use `manager.php`.
 *
 * For a full list of all options, check the Manager Readme:
 * https://github.com/php-telegram-bot/telegram-bot-manager#set-extra-bot-parameters
 */

return [
    // Add you bot's API key and name
    'api_key'      => 'your:bot_api_key',
    'bot_username' => 'username_bot', // Without "@"

    // [Manager Only] Secret key required to access the webhook
    'secret'       => 'super_secret',

    // When using the getUpdates method, this can be commented out
    'webhook'      => [
        'url' => 'https://your-domain/path/to/hook-or-manager.php',
        // Use self-signed certificate
        // 'certificate'     => __DIR__ . '/path/to/your/certificate.crt',
        // Limit maximum number of connections
        // 'max_connections' => 5,
    ],

    // All command related configs go here
    'commands'     => [
        // Define all paths for your custom commands
        'paths'   => [
            __DIR__ . '/Commands/Group',
            __DIR__ . '/Commands/Admin',
            __DIR__ . '/Commands/User',
        ],
        // Here you can set any command-specific parameters
        'configs' => [
            'genericmessage' => [
                'proxy' => [
                    ''/*chat_id*/ => [
                        'name' => '',
                        'admin_id' => '',
                    ],
                ],
            ],
            'chat' => ['chat_id' => ''],
            'fishing' => require __DIR__ . '/fishing_config.php',
            'chatter' => require __DIR__ . '/chatter_config.php',
            // - Google geocode/timezone API key for /date command (see DateCommand.php)
            // 'date'    => ['google_api_key' => 'your_google_api_key_here'],
            // - OpenWeatherMap.org API key for /weather command (see WeatherCommand.php)
            // 'weather' => ['owm_api_key' => 'your_owm_api_key_here'],
            // - Payment Provider Token for /payment command (see Payments/PaymentCommand.php)
            // 'payment' => ['payment_provider_token' => 'your_payment_provider_token_here'],
            'cleanup' => [
                'tables_to_clean' => [
                    'callback_query',
                    'chat',
                    'chosen_inline_result',
                    'conversation',
                    'edited_message',
                    'inline_query',
                    'message',
                    'poll',
                    'request_limiter',
                    'shipping_query',
                    'telegram_update',
                    'user_chat',
                ],
                'clean_older_than' => [
                    'callback_query'       => '1 days',
                    'chat'                 => '30 days',
                    'chosen_inline_result' => '1 days',
                    'conversation'         => '3 days',
                    'edited_message'       => '1 days',
                    'inline_query'         => '1 days',
                    'message'              => '1 days',
                    'poll'                 => '7 days',
                    'request_limiter'      => '1 minute',
                    'shipping_query'       => '3 days',
                    'telegram_update'      => '1 days',
                    'user_chat'            => '30 days',
                ]
            ],

            'customcleanup' => [
                'tables_to_clean' => [
                    'callback_query',
                    'chat',
                    'chosen_inline_result',
                    'conversation',
                    'edited_message',
                    'inline_query',
                    'message',
                    'poll',
                    'request_limiter',
                    'shipping_query',
                    'telegram_update',
                    'user_chat',
                ],
                'clean_older_than' => [
                    'callback_query'       => '1 days',
                    'chat'                 => '30 days',
                    'chosen_inline_result' => '1 days',
                    'conversation'         => '3 days',
                    'edited_message'       => '1 days',
                    'inline_query'         => '1 days',
                    'message'              => '1 days',
                    'poll'                 => '7 days',
                    'request_limiter'      => '1 minute',
                    'shipping_query'       => '3 days',
                    'telegram_update'      => '1 days',
                    'user_chat'            => '30 days',
                ]
            ],
        ],
    ],

    // Define all IDs of admin users
    'admins'       => [
        // 123,
    ],

    // Enter your MySQL database credentials
     'mysql'        => [
         'host'     => '127.0.0.1',
         'user'     => 'root',
         'password' => 'root',
         'database' => 'telegram_bot',
     ],

    // Logging (Debug, Error and Raw Updates)
     'logging'  => [
         'debug'  => __DIR__ . '/php-telegram-bot-debug.log',
         'error'  => __DIR__ . '/php-telegram-bot-error.log',
         'update' => __DIR__ . '/php-telegram-bot-update.log',
     ],

    // Set custom Upload and Download paths
    'paths'        => [
        'download' => __DIR__ . '/Download',
        'upload'   => __DIR__ . '/Upload',
    ],

    // Requests Limiter (tries to prevent reaching Telegram API limits)
    'limiter'      => [
        'enabled' => true,
    ],
];

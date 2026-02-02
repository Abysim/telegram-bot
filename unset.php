<?php

/**
 * This file is used to unset / delete the webhook.
 */

// Load composer
require_once __DIR__ . '/vendor/autoload.php';

// Load all configuration options
/** @var array $config */
$config = require __DIR__ . '/config.php';

// Secret check - must pass ?secret=xxx
if (!isset($_GET['secret']) || $_GET['secret'] !== $config['secret']) {
    http_response_code(403);
    die('Forbidden');
}

try {
    // Create Telegram API object
    $telegram = new Longman\TelegramBot\Telegram($config['api_key'], $config['bot_username']);

    // Unset / delete the webhook
    $result = $telegram->deleteWebhook();

    echo $result->getDescription();
} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    echo $e->getMessage();
}

<?php

/**
 * This file is used to run a command in a background.
 */


// Load composer
require_once __DIR__ . '/vendor/autoload.php';

// Load all configuration options
/** @var array $config */
$config = require __DIR__ . '/config.php';

try {
    Longman\TelegramBot\TelegramLog::initialize(
        new Monolog\Logger('telegram_bot', [
            (new Monolog\Handler\StreamHandler($config['logging']['debug'], Monolog\Logger::DEBUG))->setFormatter(new Monolog\Formatter\LineFormatter(null, null, true)),
            (new Monolog\Handler\StreamHandler($config['logging']['error'], Monolog\Logger::ERROR))->setFormatter(new Monolog\Formatter\LineFormatter(null, null, true)),
        ]),
        new Monolog\Logger('telegram_bot_updates', [
            (new Monolog\Handler\StreamHandler($config['logging']['update'], Monolog\Logger::INFO))->setFormatter(new Monolog\Formatter\LineFormatter('%message%' . PHP_EOL)),
        ])
    );

    if ($argv[1] != $config['secret']) {
        die();
    }

    // Create Telegram API object
    $telegram = new Longman\TelegramBot\Telegram($config['api_key'], $config['bot_username']);

    // Add commands paths containing your custom commands
    $telegram->addCommandsPaths($config['commands']['paths']);

    // Enable MySQL if required
    $telegram->enableMySql($config['mysql']);

    // Set custom Download and Upload paths
    $telegram->setDownloadPath($config['paths']['download']);
    $telegram->setUploadPath($config['paths']['upload']);

    // Load all command-specific configurations
    foreach ($config['commands']['configs'] as $command_name => $command_config) {
        $telegram->setCommandConfig($command_name, $command_config);
    }

    $command = '/' . implode(' ', array_slice($argv, 2));
    //Longman\TelegramBot\TelegramLog::debug($command);
    // Run user selected commands
    $telegram->runCommands([$command]);

} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    // Log telegram errors
    Longman\TelegramBot\TelegramLog::error($e);

    // Uncomment this to output any errors (ONLY FOR DEVELOPMENT!)
    // echo $e;
} catch (Longman\TelegramBot\Exception\TelegramLogException $e) {
    // Uncomment this to output log initialisation errors (ONLY FOR DEVELOPMENT!)
    // echo $e;
}

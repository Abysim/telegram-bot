<?php

/**
 * This file is part of the PHP Telegram Bot example-bot package.
 * https://github.com/php-telegram-bot/example-bot/
 *
 * (c) PHP Telegram Bot Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Admin "/teach" command
 *
 * Simply teach the input phrase to chatter
 */

namespace Longman\TelegramBot\Commands\AdminCommands;

use http\Env\Response;
use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

class TeachCommand extends AdminCommand
{
    /**
     * @var string
     */
    protected $name = 'teach';

    /**
     * @var string
     */
    protected $description = 'Teach';

    /**
     * @var string
     */
    protected $usage = '/teach <text>';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * Main command execution
     *
     * @return ServerResponse
     * @throws TelegramException
     */
    public function execute(): ServerResponse
    {
        return $this->getTelegram()->executeCommand('chatter');
    }
}

<?php

/**
 * Fishing "/hunt" command
 *
 * Simply echo the input back to the user.
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;

class HuntCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'hunt';

    /**
     * @var string
     */
    protected $description = 'Полювання';

    /**
     * @var string
     */
    protected $usage = '/hunt';

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
        return $this->getTelegram()->executeCommand('fishing');
    }
}

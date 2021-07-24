<?php

/**
 * Fishing "/cast" command
 *
 * Simply echo the input back to the user.
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;

class TrophiesCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'trophies';

    /**
     * @var string
     */
    protected $description = 'Переглянути трофеї';

    /**
     * @var string
     */
    protected $usage = '/trophies';

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

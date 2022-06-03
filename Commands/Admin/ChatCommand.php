<?php

/**
 * Admin "/chat" command
 *
 * Simply echo the input to the specific chat.
 */

namespace Longman\TelegramBot\Commands\AdminCommands;

use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

class ChatCommand extends AdminCommand
{
    /**
     * @var string
     */
    protected $name = 'chat';

    /**
     * @var string
     */
    protected $description = 'Chat';

    /**
     * @var string
     */
    protected $usage = '/chat <text>';

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
        $message = $this->getMessage();
        $text    = $message->getText(true);

        if ($text === '') {
            return $this->replyToChat('Command usage: ' . $this->getUsage());
        }

        $array = explode(' ', $text);
        $id = array_shift($array);
        if (is_numeric($id)) {
            return Request::sendMessage(array_merge([
                'chat_id' => $id,
                'text'    => implode(' ', $array),
            ]));
        }

        return Request::sendMessage(array_merge([
            'chat_id' => $this->getConfig('chat_id'),
            'text'    => $text,
        ]));
    }
}

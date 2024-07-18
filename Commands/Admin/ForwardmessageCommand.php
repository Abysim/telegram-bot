<?php


/**
 * Admin "/deletemessages" command
 *
 * Deletes specific messages after timeout.
 */

namespace Longman\TelegramBot\Commands\AdminCommands;

use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\TelegramLog;
use PDO;

class ForwardmessageCommand extends AdminCommand
{
    /**
     * @var string
     */
    protected $name = 'forwardmessage';

    /**
     * @var string
     */
    protected $description = 'Forward Message';

    /**
     * @var string
     */
    protected $usage = '/forwardmessage <timeout> <chatID> <fromChatID> <messadeID>';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * Main command execution
     *
     * @return ServerResponse
     */
    public function execute(): ServerResponse
    {
        $message = $this->getMessage();
        $args = explode(' ', $message->getText(true));

        if (count($args) < 4) {
            TelegramLog::error($message->getText());

            return Request::emptyResponse();
        }

        sleep($args[0]);

        return Request::forwardMessage([
            'chat_id' => $args[1],
            'from_chat_id' => $args[2],
            'message_id' => $args[3],
        ]);
    }
}

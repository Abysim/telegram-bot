<?php


/**
 * Admin "/deletemessages" command
 *
 * Deletes specific messages after timeout.
 */

namespace Longman\TelegramBot\Commands\AdminCommands;

use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\TelegramLog;

class DeletemessagesCommand extends AdminCommand
{
    /**
     * @var string
     */
    protected $name = 'deletemessages';

    /**
     * @var string
     */
    protected $description = 'Delete Messages';

    /**
     * @var string
     */
    protected $usage = '/deletemessages <timeout> <chatID> <messadeID1>...';

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
        $args = explode(' ', $message->getText(true));

        if (count($args) < 3) {
            TelegramLog::error($message->getText());

            return Request::emptyResponse();
        }

        sleep($args[0]);

        foreach (array_slice($args, 2) as $messageId) {
            Request::deleteMessage([
                'chat_id' => $args[1],
                'message_id' => $messageId,
            ]);
        }

        return Request::emptyResponse();
    }
}

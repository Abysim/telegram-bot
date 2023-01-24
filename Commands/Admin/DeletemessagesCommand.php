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

        $messageIds = [];
        foreach (array_slice($args, 2) as $messageId) {
            $messageIds[] = (int) $messageId;
        }

        if ($this->getTelegram()->isDbEnabled()) {
            $pdo = DB::getPdo();
            $ids = implode(',', $messageIds);
            $sql = '
                        SELECT `id`
                        FROM `message`
                        WHERE `id` NOT IN (' . $ids . ') AND `chat_id` = :chat_id AND `reply_to_message` IN (' . $ids . ')
                        ORDER BY `id` DESC
                        LIMIT 1';
            $sth = $pdo->prepare($sql);
            $sth->bindValue(':chat_id', $args[1]);
            $sth->execute();
            $result = $sth->fetchAll(PDO::FETCH_ASSOC);
            if (isset($result[0])) {
                return Request::emptyResponse();
            }
        }

        foreach ($messageIds as $messageId) {
            Request::deleteMessage([
                'chat_id' => $args[1],
                'message_id' => $messageId,
            ]);
        }

        return Request::emptyResponse();
    }
}

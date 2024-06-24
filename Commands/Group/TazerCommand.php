<?php

/**
 * "/tazer" command
 *
 * Simply echo the input back to the user.
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use PDO;

class TazerCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'tazer';

    /**
     * @var string
     */
    protected $description = 'Tazer';

    /**
     * @var string
     */
    protected $usage = '/tazer';

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

        if (!in_array($message->getFrom()->getId(), $this->telegram->getAdminList())) {
            return Request::deleteMessage([
                'chat_id' => $message->getChat()->getId(),
                'message_id' => $message->getMessageId(),
            ]);
        }

        $parts = explode(' ', $message->getText(), 3);
        $username = $parts[1] ?? null;

        if (empty($username)) {
            return Request::deleteMessage([
                'chat_id' => $message->getChat()->getId(),
                'message_id' => $message->getMessageId(),
            ]);
        }

        $username = trim(trim($username), "@");
        $pdo = DB::getPdo();
        $sql = "
                    SELECT `id`
                    FROM `user`
                    WHERE `username` = :username
                ";
        $sth = $pdo->prepare($sql);
        $sth->bindValue(':username', $username);
        $sth->execute();
        $id = $sth->fetchAll(PDO::FETCH_ASSOC)[0]['id'] ?? null;

        if (empty($id)) {
            return Request::deleteMessage([
                'chat_id' => $message->getChat()->getId(),
                'message_id' => $message->getMessageId(),
            ]);
        }

        $times = [
            1 => '1 хвилини',
            2 => '2 хвилин',
            3 => '3 хвилин',
        ];

        $time = $parts[2] ?? null;
        $time = (int) $time;
        if ($time < 1 || $time > 3) {
            $time = rand(1, 3);
        }
        Request::restrictChatMember([
            'chat_id' => $message->getChat()->getId(),
            'user_id' => $id,
            'until_date' => time() + 60 * $time,
        ]);

        return Request::sendMessage([
            'chat_id' => $message->getChat()->getId(),
            'text' => '@' . $username . ', представник влади вдарив вас шокером. Ваші м\'язи вас не слухаються і ви нездатні говорити впродовж ' . $times[$time],
        ]);
    }
}

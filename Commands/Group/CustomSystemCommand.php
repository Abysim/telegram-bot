<?php
/**
 * @package Longman\TelegramBot\Commands\UserCommands
 * @author Andrii Kalmus <andrii.kalmus@abysim.com>
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\Message;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\TelegramLog;
use PDOException;

class CustomSystemCommand extends SystemCommand
{
    /**
     * @var array
     */
    protected array $messageIds = [];

    /**
     * @param string $text
     * @param array $data
     *
     * @return ServerResponse
     * @throws TelegramException
     */
    public function replyToChat(string $text, array $data = []): ServerResponse
    {
        if ($message = $this->getMessage() ?: $this->getEditedMessage() ?: $this->getChannelPost() ?: $this->getEditedChannelPost()) {
            $result = Request::sendMessage(array_merge([
                'chat_id' => $message->getChat()->getId(),
                'text'    => $text,
            ], $data));

            if ($result->isOk() && $this->telegram->isDbEnabled()) {
                try {
                    /* @var Message $sentMessage */
                    $sentMessage = $result->getResult();

                    $pdo = DB::getPdo();
                    $sql = "
                        INSERT IGNORE INTO `message` (`chat_id`,`id`,`user_id`,`date`,`reply_to_chat`,`reply_to_message`,`edit_date`,`text`,`entities`)
                        VALUES (:chat_id,:id,:user_id,:date,:reply_to_chat,:reply_to_message,:edit_date,:text,:entities)
                    ";
                    $sth = $pdo->prepare($sql);

                    $entities = [];
                    if (!empty($sentMessage->getEntities())) {
                        foreach ($sentMessage->getEntities() as $entity) {
                            $entities[] = $entity->getRawData();
                        }
                    }
                    $replyToMessage = $sentMessage->getReplyToMessage();
                    $params = [
                        ':chat_id' => $sentMessage->getChat()->getId(),
                        ':id' => $sentMessage->getMessageId(),
                        ':user_id' => $sentMessage->getFrom()->getId(),
                        ':date' => date('Y-m-d H:i:s', $sentMessage->getDate()),
                        ':reply_to_chat' => $replyToMessage ? ($replyToMessage->getChat()->getId() ?? null) : null,
                        ':reply_to_message' => $replyToMessage ? ($replyToMessage->getMessageId() ?? null) : null,
                        ':edit_date' => date('Y-m-d H:i:s'),
                        ':text' => $sentMessage->getText(),
                        ':entities'  => empty($entities) ? null : json_encode($entities),
                    ];

                    $sth->execute($params);
                } catch (PDOException $e) {
                    TelegramLog::error($e->getMessage());
                }
            }

            return $result;
        }

        return Request::emptyResponse();
    }

    /**
     * @param int|null $timeout
     *
     * @return ServerResponse
     */
    protected function deleteMessages(?int $timeout = null): ServerResponse
    {
        $message = $this->getMessage();
        $chat = $message->getChat();

        if (!$chat->isPrivateChat()) {
            TelegramLog::error('php '
                . $this->getConfig('exe') . ' '
                . $this->getConfig('secret') . ' '
                . 'deletemessages '
                . ($timeout ?? $this->getConfig('delete_time')) . ' '
                . $chat->getId() . ' '
                . implode(' ', $this->messageIds)
                . ' > /dev/null 2>&1&');

            shell_exec('php '
                . $this->getConfig('exe') . ' '
                . $this->getConfig('secret') . ' '
                . 'deletemessages '
                . ($timeout ?? $this->getConfig('delete_time')) . ' '
                . $chat->getId() . ' '
                . implode(' ', $this->messageIds)
                . ' > /dev/null 2>&1&');
        }

        return Request::emptyResponse();
    }
}
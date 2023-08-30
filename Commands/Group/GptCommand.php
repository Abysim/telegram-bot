<?php

/**
 * GPT "/gpt" command
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use Exception;
use Longman\TelegramBot\Commands\SystemCommands\CustomSystemCommand;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\TelegramLog;
use OpenAI;
use PDO;

class GptCommand extends CustomSystemCommand
{
    /**
     * @var string
     */
    protected $name = 'gpt';

    /**
     * @var string
     */
    protected $description = 'GPT';

    /**
     * @var string
     */
    protected $usage = '/gpt';

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
        $chat = $message->getChat();
        $config = $this->getConfig();
        $text = trim($message->getText(true));

        if (in_array($chat->getId(), $config['chats']) && !empty($text)) {
            $length = strlen($config['role']) + strlen($text);
            $messages = [['role' => 'user', 'content' => $text]];
            if ($message->getReplyToMessage()) {
                $content = str_replace('GPT: ', '', $message->getReplyToMessage()->getText(true));
                $length += strlen($content);
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $content,
                ];
                $isAssistant = false;

                $pdo = DB::getPdo();
                $sql = '
                        SELECT `reply_to_message`
                        FROM `message`
                        WHERE `chat_id` = :chat_id AND `id` = :id
                        ORDER BY `id` DESC
                        LIMIT 1';
                $sth = $pdo->prepare($sql);
                $sth->bindValue(':chat_id', $message->getChat()->getId());
                $sth->bindValue(':id', $message->getReplyToMessage()->getMessageId());
                $sth->execute();
                $request = $sth->fetchAll(PDO::FETCH_ASSOC);
                $replyId = $request[0]['reply_to_message'] ?? null;

                while ($replyId) {
                    $sql = '
                        SELECT `reply_to_message`, `text`
                        FROM `message`
                        WHERE `chat_id` = :chat_id AND `id` = :id
                        ORDER BY `id` DESC
                        LIMIT 1';
                    $sth = $pdo->prepare($sql);
                    $sth->bindValue(':chat_id', $message->getChat()->getId());
                    $sth->bindValue(':id', $replyId);
                    $sth->execute();
                    $request = $sth->fetchAll(PDO::FETCH_ASSOC);
                    $replyId = $request[0]['reply_to_message'] ?? null;
                    if (!empty($request[0]['text'])) {
                        $content = str_ireplace(['GPT: ', '/gpt '], '', $request[0]['text']);
                        $length += strlen($content);

                        if ($length > 11111) {
                            break;
                        }

                        $messages[] = [
                            'role' => $isAssistant ? 'assistant' : 'user',
                            'content' => $content,
                        ];
                        $isAssistant = !$isAssistant;
                    }
                }
            }

            $messages[] = ['role' => 'system', 'content' => $config['role']];

            try {
                $client = OpenAI::client($config['key']);

                $response = $client->chat()->create([
                    'model' => 'gpt-3.5-turbo',
                    'messages' => array_reverse($messages),
                    'n' => 1,
                ]);
            } catch (Exception $e) {
                TelegramLog::error($length . ' ' . $e->getMessage());

                return Request::emptyResponse();
            }

            foreach ($response->choices as $result) {
                return $this->replyToChat('GPT: ' . $result->message->content, [
                    'reply_to_message_id' => $message->getMessageId()
                ]);
            }
        }

        return Request::emptyResponse();
    }
}

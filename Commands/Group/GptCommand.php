<?php

/**
 * GPT "/gpt" command
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use DeepL\Translator;
use Exception;
use Longman\TelegramBot\Commands\SystemCommands\CustomSystemCommand;
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

        if (in_array($chat->getId(), array_keys($config['chats'])) && !empty($text)) {
            if (!empty($config['chats'][$chat->getId()]['translate'])) {
                try {
                    $translator = new Translator($config['chats'][$chat->getId()]['translate']);
                    $text = (string) $translator->translateText($text, null, 'en-US');
                } catch (Exception $e) {
                    TelegramLog::error($e->getMessage());
                }
            }

            $role = $config['chats'][$chat->getId()]['role'] ?? $config['role'];
            $length = strlen($role) + strlen($text);
            $messages = [['role' => 'user', 'content' => $text]];
            if ($message->getReplyToMessage()) {
                $content = str_replace('GPT: ', '', $message->getReplyToMessage()->getText(true));
                if (isset($translator)) {
                    try {
                        $content = (string) $translator->translateText($content, null, 'en-US');
                    } catch (Exception $e) {
                        TelegramLog::error($e->getMessage());
                    }
                }
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
                        if (isset($translator)) {
                            try {
                                $content = (string) $translator->translateText($content, null, 'en-US');
                            } catch (Exception $e) {
                                TelegramLog::error($e->getMessage());
                            }
                        }

                        $length += strlen($content);

                        if ($length > 88888) {
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

            if (!empty($role)) {
                $messages[] = ['role' => 'system', 'content' => $role];
            }

            try {
                $client = OpenAI::factory()
                    ->withApiKey($config['chats'][$chat->getId()]['key'] ?? $config['key']);
                if (!empty($config['chats'][$chat->getId()]['uri'] ?? $config['uri'])) {
                    $client = $client->withBaseUri($config['chats'][$chat->getId()]['uri'] ?? $config['uri']);
                }
                $client = $client->make();

                $response = $client->chat()->create([
                    'model' => $config['chats'][$chat->getId()]['model'] ?? $config['model'],
                    'messages' => array_reverse($messages),
                    'n' => 1,
                ]);
            } catch (Exception $e) {
                TelegramLog::error($length . ' ' . $e->getMessage());

                return Request::emptyResponse();
            }

            foreach ($response->choices as $result) {
                $content = $result->message->content;
                if (isset($translator)) {
                    try {
                        $content = (string) $translator->translateText($content, 'en', 'uk');
                    } catch (Exception $e) {
                        TelegramLog::error($e->getMessage());
                    }
                }

                if (strpos('</think>', $content) !== false) {
                    $parts = explode('</think>', $content);

                    $this->replyToChat('GPT: ' . $parts[0], [
                        'reply_to_message_id' => $message->getMessageId(), 'parse_mode' => 'Markdown'
                    ]);

                    $content = end($parts);
                }

                return $this->replyToChat('GPT: ' . $content, [
                    'reply_to_message_id' => $message->getMessageId(), 'parse_mode' => 'Markdown'
                ]);
            }
        }

        return Request::emptyResponse();
    }
}

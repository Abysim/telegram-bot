<?php

/**
 * GPT "/gpt" command
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use OpenAI;

class GptCommand extends UserCommand
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
            $client = OpenAI::client($config['key']);

            $response = $client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => $config['role']],
                    ['role' => 'user', 'content' => $text],
                ],
            ]);

            foreach ($response->choices as $result) {
                return Request::sendMessage([
                    'chat_id' => $message->getChat()->getId(),
                    'text' => 'GPT: ' . $result->message->content,
                    'reply_to_message_id' => $message->getMessageId()
                ]);
            }
        }
    }
}

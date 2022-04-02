<?php

/**
 * Chat join request command
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

class ChatJoinRequestCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'chatjoinrequest';

    /**
     * @var string
     */
    protected $description = 'Handle chat join request';

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
        $update = $this->getUpdate();
        $joinRequest = $update->getChatJoinRequest();

        $joinRequestConfig = $this->getConfig();
        if (isset($joinRequestConfig[$joinRequest->getChat()->getId()])) {
            $config = $joinRequestConfig[$joinRequest->getChat()->getId()];

            if ($joinRequest->getFrom()) {
                    $data = ['chat_id' => $config['admin_id'], 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
                    $username = $joinRequest->getFrom()->getUsername();
                    if (empty($username)) {
                        $fullName =
                            trim($joinRequest->getFrom()->getFirstName() . ' ' . $joinRequest->getFrom()->getLastName());
                        $username = "<a href=\"tg://user?id={$joinRequest->getFrom()->getId()}\">$fullName</a>";
                    } else {
                        $username = '@' . $username;
                    }

                    $data['text'] = '#Новий запит на вступ до ' . "<a href=\"{$config['link']}\">{$joinRequest->getChat()->getTitle()}</a>" . ': ' . $username;

                    Request::sendMessage(['chat_id' => $joinRequest->getFrom()->getId(), 'text' => $config['text'], 'parse_mode' => 'HTML', 'disable_web_page_preview' => true]);
                    return Request::sendMessage($data);
                }
        }

        return Request::emptyResponse();
    }
}

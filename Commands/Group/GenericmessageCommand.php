<?php

/**
 * This file is part of the PHP Telegram Bot example-bot package.
 * https://github.com/php-telegram-bot/example-bot/
 *
 * (c) PHP Telegram Bot Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Generic message command
 *
 * Gets executed when any type of message is sent.
 *
 * In this group-related context, we can handle new and left group members.
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

class GenericmessageCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'genericmessage';

    /**
     * @var string
     */
    protected $description = 'Handle generic message';

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

        $proxyConfig = $this->getConfig('proxy');
        if (isset($proxyConfig[$message->getChat()->getId()])) {
            $config = $proxyConfig[$message->getChat()->getId()];
            $data = ['chat_id' => $config['admin_id']];

            if ($message->getNewChatMembers()) {
                $username = $message->getFrom()->getUsername();
                if (empty($username)) {
                    $data['parse_mode'] = 'HTML';
                    $fullName = trim($message->getFrom()->getFirstName() . ' ' .$message->getFrom()->getLastName());
                    $username = "<a href=\"tg://user?id={$message->getFrom()->getId()}\">$fullName</a>";
                } else {
                    $username = '@' . $username;
                }

                $data['text'] = 'Новий член ' . $config['name'] . ': ' . $username;

                Request::sendMessage($data);
            } else {
                $data['from_chat_id'] = $message->getChat()->getId();
                $data['message_id'] = $message->getMessageId();

                Request::forwardMessage($data);
            }

            return Request::deleteMessage([
                'chat_id' => $message->getChat()->getId() ,
                'message_id' => $message->getMessageId(),
            ]);
        }

        // Handle new chat members
        if ($message->getNewChatMembers()) {
            //return $this->getTelegram()->executeCommand('newchatmembers');
            return Request::emptyResponse();
        }

        // Handle left chat members
        if ($message->getLeftChatMember()) {
            //return $this->getTelegram()->executeCommand('leftchatmember');
            return Request::emptyResponse();
        }

        // The chat photo was changed
        if ($new_chat_photo = $message->getNewChatPhoto()) {
            return Request::emptyResponse();
        }

        // The chat title was changed
        if ($new_chat_title = $message->getNewChatTitle()) {
            return Request::emptyResponse();
        }

        // A message has been pinned
        if ($pinned_message = $message->getPinnedMessage()) {
            return Request::emptyResponse();
        }

        $this->getTelegram()->executeCommand('fishing');

        $this->getTelegram()->executeCommand('chatter');

        return Request::emptyResponse();
    }
}

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
 * Channel post command
 *
 * Gets executed when a new post is created in a channel.
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;

class ChannelpostCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'channelpost';

    /**
     * @var string
     */
    protected $description = 'Handle channel post';

    /**
     * @var string
     */
    protected $version = '1.1.0';

    /**
     * Main command execution
     *
     * @return ServerResponse
     */
    public function execute(): ServerResponse
    {
        $message = $this->getChannelPost();

        $proxyConfig = $this->getConfig('proxy');
        if (isset($proxyConfig[$message->getChat()->getId()])) {
            $config = $proxyConfig[$message->getChat()->getId()];
            $adminIds = is_array($config['admin_id']) ? $config['admin_id'] : [$config['admin_id']];

            if (
                empty($config['text'])
                || strpos($message->getText() ?? $message->getCaption(), $config['text']) !== false
            ) {
                $i = 0;
                foreach ($adminIds as $adminId) {
                    $i++;

                    shell_exec('php '
                        . $this->getConfig('exe') . ' '
                        . $this->getConfig('secret') . ' '
                        . 'forwardmessage '
                        . (600 * $i) . ' '
                        . $adminId . ' '
                        . $message->getChat()->getId() . ' '
                        . $message->getMessageId() . ' '
                        . ' > /dev/null 2>/dev/null &');
                }
            }
        }

        return parent::execute();
    }
}

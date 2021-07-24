<?php

/**
 * Admin "/f" command
 *
 * Gets all forms of the specific word in Ukrainian and Russian.
 */

namespace Longman\TelegramBot\Commands\AdminCommands;

use cijic\phpMorphy\Morphy;
use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use PDO;

class FCommand extends AdminCommand
{
    /**
     * @var string
     */
    protected $name = 'f';

    /**
     * @var string
     */
    protected $description = 'Forms';

    /**
     * @var string
     */
    protected $usage = '/f <слово>';

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

        $text = trim($this->getMessage()->getText(true));
        $text = trim(explode(' ', $text)[0]);
        $result = [];
        $morphy = new Morphy('ru');
        $morphs = $morphy->getAllForms(mb_strtoupper($text));

        if (is_array($morphs)) {
            foreach ($morphs as $morph) {
                $result[] =  mb_strtolower($morph );
            }
        }

        $morphy = new Morphy('ua');
        $morphs = $morphy->getAllForms(mb_strtoupper($text));

        if (is_array($morphs)) {
            foreach ($morphs as $morph) {
                $result[] =  mb_strtolower($morph );
            }
        }

        return $this->replyToChat(
            implode(',',array_unique($result)),
            ['reply_to_message_id' => $this->getMessage()->getMessageId()]
        );
    }
}

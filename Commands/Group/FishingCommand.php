<?php


namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\ChatAction;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use PDO;

class FishingCommand extends CustomSystemCommand
{
    const FISH = 'fish';

    const DIG = 'dig';

    const HUNT = 'hunt';

    const TROPHY = 'trophy';

    /**
     * @var string
     */
    protected $name = 'fishing';

    /**
     * @var string
     */
    protected $description = 'Fishing';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * @var array
     */
    private array $messageIds = [];

    /**
     * @param int|null $timeout
     *
     * @return ServerResponse
     */
    private function deleteMessages(?int $timeout = null): ServerResponse
    {
        $message = $this->getMessage();
        $chat = $message->getChat();

        if (!$chat->isPrivateChat()) {
            shell_exec('php '
                . $this->getConfig('exe') . ' '
                . $this->getConfig('secret') . ' '
                . 'deletemessages '
                . ($timeout ?? $this->getConfig('delete_time')) . ' '
                . $chat->getId() . ' '
                . implode(' ', $this->messageIds)
                . ' > /dev/null 2>/dev/null &');
        }

        return Request::emptyResponse();
    }

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
        $telegram = $this->getTelegram();
        $config = $this->getConfig();
        $this->messageIds[] = $message->getMessageId();

        $parts = explode(' ', $message->getText(), 2);
        [$command] = explode('@', $parts[0]);
        $hash = $parts[1] ?? null;

        if (in_array(mb_substr($command, 0, 1), $config['command_chars'])) {
            $command = mb_substr(mb_strtolower($command), 1);
            if (in_array($command, $config['commands'][self::HUNT])) {
                $type = self::HUNT;
            } elseif (in_array($command, $config['commands'][self::FISH])) {
                $type = self::FISH;
            } elseif (in_array($command, $config['commands'][self::DIG])) {
                $type = self::DIG;
            } elseif (in_array($command, $config['commands'][self::TROPHY])) {
                $type = null;
            } else {
                return Request::emptyResponse();
            }
        } else {
            return Request::emptyResponse();
        }

        if (!$telegram->isDbEnabled()) {
            $this->messageIds[] = $this->replyToChat(
                'Немає доступу до джерела даних!', ['reply_to_message_id' => $message->getMessageId()]
            )->getResult()->getMessageId();

            return $this->deleteMessages(5);
        }

        if (!in_array($message->getChat()->getId(), $config['allowed_chats'])) {
            $this->messageIds[] = $this->replyToChat(
                'Гра працює лише в обраних групах!', ['reply_to_message_id' => $message->getMessageId()]
            )->getResult()->getMessageId();

            return $this->deleteMessages(5);
        }

        $user = $message->getFrom();

        if ($user->getIsBot()) {
            $this->messageIds[] = $this->replyToChat(
                'Боти не можуть грати!', ['reply_to_message_id' => $message->getMessageId()]
            )->getResult()->getMessageId();

            return $this->deleteMessages(5);
        }

        $pdo = DB::getPdo();

        $sql = '
                SELECT `user_id`, `reply_to_message`, `text`
                FROM `message`
                WHERE `chat_id` = :chat_id AND `date` > :date
            ';
        $sth = $pdo->prepare($sql);
        $sth->bindValue(':chat_id', $chat->getId());
        $sth->bindValue(':date', date('Y-m-d H:i:s', time() - $config['silence_time']));
        $sth->execute();
        $messages = $sth->fetchAll(PDO::FETCH_ASSOC);

        foreach ($messages as $chatMessage) {
            if (
                empty($chatMessage['reply_to_message'])
                && !empty($chatMessage['text'])
                && $chatMessage['user_id'] != $telegram->getBotId()
                && !in_array(mb_substr($chatMessage['text'], 0, 1), $config['command_chars'])
            ) {
                $this->messageIds[] = $this->replyToChat(
                    $config['silence'],
                    ['reply_to_message_id' => $message->getMessageId()]
                )->getResult()->getMessageId();
                return $this->deleteMessages(2);
            }
        }



        $sql = '
                SELECT `fishing_trophy`.*, `user`.`first_name`, `user`.`last_name`
                FROM `fishing_trophy`
                LEFT JOIN `user` ON `user`.`id` = `fishing_trophy`.`user_id`
            ';
        $sth = $pdo->prepare($sql);
        $sth->execute();
        $trophies = array_column($sth->fetchAll(PDO::FETCH_ASSOC), null, 'type');

        if (empty($type)) {
            $textLines = [];
            foreach ($trophies as $type => $trophy) {
                $fullName = $trophy['first_name'] . ($trophy['last_name'] ? ' ' . $trophy['last_name'] : '');

                $textLines[] = str_replace(
                    ['{{nick}}', '{{target}}', '{{size}}'],
                    [$fullName, $trophy['name'], $trophy['size']],
                    $config['trophy'][$type]
                );
            }

            $this->messageIds[] = $this->replyToChat(
                implode("\n", $textLines),
                ['reply_to_message_id' => $message->getMessageId(), 'parse_mode' => 'HTML']
            )->getResult()->getMessageId();

            return $this->deleteMessages();
        }

        $sql = '
                SELECT `time`
                FROM `fishing_time`
                WHERE `user_id` = :user_id
            ';
        $sth = $pdo->prepare($sql);
        $sth->bindValue(':user_id', $user->getId());
        $sth->execute();
        $time = $sth->fetchAll(PDO::FETCH_ASSOC)[0]['time'] ?? null;

        $timeDiff = time() - strtotime($time);
        if ($time && $timeDiff < $config['repeat']) {
            $waitMinutes = intval(gmdate("i",  $config['repeat'] - $timeDiff));
            $waitSeconds = intval(gmdate("s",  $config['repeat'] - $timeDiff));

            $waitTextLines = [];
            if ($waitMinutes > 0) {
                $waitTextLines[] = $waitMinutes . ' хв';
            }
            if ($waitSeconds > 0) {
                $waitTextLines[] = $waitSeconds . ' сек';
            }
            $waitText = implode(' ', $waitTextLines);
            $text = str_replace('{{time}}', $waitText, $config['wait'][$type]);

            $this->messageIds[] = $this->replyToChat(
                $text,
                ['reply_to_message_id' => $message->getMessageId()]
            )->getResult()->getMessageId();

            return $this->deleteMessages();
        }

        if ($hash) {
            srand(crc32($hash));
        }
        $place = $config['places'][$type][rand(0, count($config['places'][$type]) - 1)];
        $target = $config['targets'][$type][rand(0, count($config['targets'][$type]) - 1)];

        $trophiesCount = 0;
        foreach ($trophies as $trophy) {
            if ($trophy['user_id'] == $user->getId()) {
                $trophiesCount++;
            }
        }

        $size = rand(1, ($trophies[$type]['size'] ?? 5) + intval($config['increase'][$type] * (3 - $trophiesCount) / 3));
        $nick = "<a href=\"tg://user?id={$user->getId()}\">{$user->getFirstName()}</a>";

        $text = str_replace('{{place}}', $place, $config['action'][$type]);
        $this->messageIds[] = $this->replyToChat(
            $text,
            ['reply_to_message_id' => $message->getMessageId()]
        )->getResult()->getMessageId();

        Request::sendChatAction([
            'chat_id' => $message->getChat()->getId(),
            'action'  => ChatAction::TYPING,
        ]);

        $sth = $pdo->prepare('
                INSERT IGNORE INTO `fishing_time` (`user_id`, `time`)
                VALUES (:user_id, :time)
                ON DUPLICATE KEY UPDATE `time` = VALUES(`time`)
            ');

        $sth->bindValue(':user_id', $user->getId());
        $sth->bindValue(':time', date('Y-m-d H:i:s'));

        $sth->execute();

        $isFail = rand(0, 1);
        $isRecord = false;
        if (!$isFail && $size > ($trophies[$type]['size'] ?? 0)) {
            $isRecord = true;

            $sth = $pdo->prepare('
                INSERT IGNORE INTO `fishing_trophy` (`user_id`, `type`, `name`, `size`)
                VALUES (:user_id, :type, :name, :size)
                ON DUPLICATE KEY UPDATE `user_id` = VALUES(`user_id`), `name` = VALUES(`name`), `size` = VALUES(`size`)
            ');

            $sth->bindValue(':user_id', $user->getId());
            $sth->bindValue(':type', $type);
            $sth->bindValue(':name', $target);
            $sth->bindValue(':size', $size);

            $sth->execute();
        }

        sleep(rand(1, 10));

        if ($isFail) {
            $text = str_replace(
                ['{{nick}}', '{{place}}'],
                [$nick, $place],
                $config['fail'][$type][rand(0, count($config['fail'][$type]) - 1)]
            );

            $this->messageIds[] = $this->replyToChat(
                $text,
                ['reply_to_message_id' => $message->getMessageId(), 'parse_mode' => 'HTML']
            )->getResult()->getMessageId();

            return $this->deleteMessages();
        }

        $textLines = [];
        $textLines[] = str_replace(
            ['{{nick}}', '{{target}}', '{{size}}'],
            [$nick, $target, $size],
            $config['success'][$type]
        );
        if ($isRecord) {
            $textLines[] = str_replace('{{nick}}', $nick, $config['record'][$type]);
        } else {
            $textLines[] = str_replace('{{nick}}', $nick, $config['not_record'][$type]);
        }

        $this->messageIds[] = $this->replyToChat(
            implode("\n", $textLines),
            ['reply_to_message_id' => $message->getMessageId(), 'parse_mode' => 'HTML']
        )->getResult()->getMessageId();

        if (!$isRecord) {
            $this->deleteMessages();
        }

        return Request::emptyResponse();
    }
}

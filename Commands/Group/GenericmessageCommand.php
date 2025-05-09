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

use Aws\Comprehend\ComprehendClient;
use CLD2Detector;
use DeepL\Translator;
use Exception;
use LanguageDetector\LanguageDetector;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\TelegramLog;
use PDO;

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

        $forwardedChat = $message->getForwardFromChat();
        if ($forwardedChat) {
            $bannedChannels = $this->getConfig('banned_channels');
            if (in_array($message->getChat()->getId(), $bannedChannels['chats'])) {
                foreach ($bannedChannels['names'] as $bannedChannel) {
                    if (strpos($forwardedChat->getTitle(), $bannedChannel) !== false) {
                        return Request::deleteMessage([
                            'chat_id' => $message->getChat()->getId(),
                            'message_id' => $message->getMessageId(),
                        ]);
                    }
                }
            }
        }

        if ($message->getChat()->isPrivateChat() && $this->getTelegram()->isDbEnabled()) {
            $pdo = DB::getPdo();
            $configs = $this->getConfig('joinrequest');

            foreach ($configs as $chatId => $config) {
                $sql = '
                        SELECT `user_id`
                        FROM `user_chat`
                        WHERE `chat_id` = :chat_id AND `user_id` = :user_id
                        LIMIT 1';
                $sth = $pdo->prepare($sql);
                $sth->bindValue(':chat_id', $config['chat_id']);
                $sth->bindValue(':user_id', $message->getFrom()->getId());
                $sth->execute();
                $result = $sth->fetchAll(PDO::FETCH_ASSOC);

                if (isset($result[0]) && empty($config['exclude_messages'])) {
                    if (!empty($message->getText())) {
                        Request::sendMessage([
                            'chat_id' => $config['admin_id'],
                            'text' => '#Приватне повідомлення з ' . $config['chat_name'] . ':',
                            'parse_mode' => 'HTML',
                            'disable_web_page_preview' => true,
                        ]);
                    }

                    $data = ['chat_id' => $config['admin_id']];
                    $data['from_chat_id'] = $message->getChat()->getId();
                    $data['message_id'] = $message->getMessageId();

                    Request::forwardMessage($data);
                } else {
                    $sql = '
                        SELECT `id`
                        FROM `chat_join_request`
                        WHERE `chat_id` = :chat_id AND `user_id` = :user_id
                        LIMIT 1';
                    $sth = $pdo->prepare($sql);
                    $sth->bindValue(':chat_id', $chatId);
                    $sth->bindValue(':user_id', $message->getFrom()->getId());
                    $sth->execute();
                    $result = $sth->fetchAll(PDO::FETCH_ASSOC);

                    if (isset($result[0])) {
                        if (!empty($message->getText())) {
                            Request::sendMessage([
                                'chat_id' => $config['admin_id'],
                                'text' => '#Нове приватне повідомлення щодо ' . $config['chat_name'] . ':',
                                'parse_mode' => 'HTML',
                                'disable_web_page_preview' => true,
                            ]);
                        }

                        $data = ['chat_id' => $config['admin_id']];
                        $data['from_chat_id'] = $message->getChat()->getId();
                        $data['message_id'] = $message->getMessageId();

                        Request::forwardMessage($data);
                    } else {
                        $proxyConfigs = $this->getConfig('proxy');
                        foreach ($proxyConfigs as $proxyСhatId => $proxyConfig) {
                            if ($proxyConfig['admin_id'] == $config['admin_id']) {
                                $sql = '
                                    SELECT `user_id`
                                    FROM `user_chat`
                                    WHERE `chat_id` = :chat_id AND `user_id` = :user_id
                                    LIMIT 1';
                                $sth = $pdo->prepare($sql);
                                $sth->bindValue(':chat_id', $proxyСhatId);
                                $sth->bindValue(':user_id', $message->getFrom()->getId());
                                $sth->execute();
                                $result = $sth->fetchAll(PDO::FETCH_ASSOC);

                                if (isset($result[0])) {
                                    if (!empty($message->getText())) {
                                        Request::sendMessage([
                                            'chat_id' => $config['admin_id'],
                                            'text' => '#Нове приватне повідомлення з ' . $proxyConfig['name'] . ':',
                                        ]);
                                    }

                                    $data = ['chat_id' => $config['admin_id']];
                                    $data['from_chat_id'] = $message->getChat()->getId();
                                    $data['message_id'] = $message->getMessageId();

                                    Request::forwardMessage($data);
                                }
                            }
                        }
                    }
                }
            }
        }

        $proxyConfig = $this->getConfig('proxy');
        if (isset($proxyConfig[$message->getChat()->getId()])) {
            $config = $proxyConfig[$message->getChat()->getId()];

            if ($message->getNewChatMembers()) {
                if (!is_array($config['admin_id'])) {
                    $data = ['chat_id' => $config['admin_id']];
                    $username = $message->getFrom()->getUsername();
                    if (empty($username)) {
                        $data['parse_mode'] = 'HTML';
                        $fullName =
                            trim($message->getFrom()->getFirstName() . ' ' . $message->getFrom()->getLastName());
                        $username = "<a href=\"tg://user?id={$message->getFrom()->getId()}\">$fullName</a>";
                    } else {
                        $username = '@' . $username;
                    }

                    $data['text'] = '#Новий член ' . $config['name'] . ': ' . $username;

                    Request::sendMessage($data);
                }
            } else {
                $adminIds = is_array($config['admin_id']) ? $config['admin_id'] : [$config['admin_id']];

                foreach ($adminIds as $adminId) {
                    if (!empty($message->getText())) {
                        Request::sendMessage(['chat_id' => $config['admin_id'], 'text' => '#Нове повідомлення з ' . $config['name'] . ':']);
                    }

                    $data = ['chat_id' => $adminId];
                    $data['from_chat_id'] = $message->getChat()->getId();
                    $data['message_id'] = $message->getMessageId();

                    Request::forwardMessage($data);
                }
            }


            return is_array($config['admin_id']) ? Request::emptyResponse() : Request::deleteMessage([
                'chat_id' => $message->getChat()->getId(),
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

        $translateConfig = $this->getConfig('translate');
        if (in_array($message->getChat()->getId(), array_merge($translateConfig['chats'], $translateConfig['debug']))) {
            $text = $message->getText(true) ?? $message->getCaption();
            if (!empty($text) && !in_array($message->getFrom()->getId(), $translateConfig['exclude'])) {
                try {
                    $translate = false;
                    $sourceLang = null;
                    $cld2 = new CLD2Detector();
                    $cld2score = $cld2->detect($text);
                    if (in_array($message->getChat()->getId(), $translateConfig['debug']) && mb_substr($message->getReplyToMessage()->getText(), 0, 5) != 'GPT: ') {
                        Request::sendMessage([
                            'chat_id' => $message->getChat()->getId(),
                            'text' => json_encode($cld2score),
                            'reply_to_message_id' => $message->getMessageId()
                        ]);
                    }

                    if (
                        $cld2score['language_code'] == 'un'
                        || ($cld2score['language_code'] != 'uk' && $cld2score['language_probability'] < 99)
                    ) {
                        try {
                            $comprehend = new ComprehendClient([
                                'region' => 'us-east-1',
                                'version' => 'latest',
                                'credentials' => [
                                    'key' => $translateConfig['aws']['key'],
                                    'secret' => $translateConfig['aws']['secret'],
                                ]
                            ]);

                            $languages = $comprehend->detectDominantLanguage(['Text' => $text])->get('Languages');

                            if (in_array($message->getChat()->getId(), $translateConfig['debug']) && mb_substr($message->getReplyToMessage()->getText(), 0, 5) != 'GPT: ') {
                                Request::sendMessage([
                                    'chat_id' => $message->getChat()->getId(),
                                    'text' => json_encode($languages),
                                    'reply_to_message_id' => $message->getMessageId()
                                ]);
                            }

                            if (($languages[0]['Score'] ?? 0) < 0.8) {
                                throw new Exception('Score to low!');
                            }

                            $sourceLang = $languages[0]['LanguageCode'] ?? 'uk';
                            $translate = $sourceLang != 'uk';
                        } catch (Exception $e) {
                            $detector = new LanguageDetector(null, ['uk', 'ru']);
                            $scores = $detector->evaluate($text)->getScores();
                            if (in_array($message->getChat()->getId(), $translateConfig['debug']) && mb_substr($message->getReplyToMessage()->getText(), 0, 5) != 'GPT: ') {
                                Request::sendMessage([
                                    'chat_id' => $message->getChat()->getId(),
                                    'text' => ($scores['ru'] - $scores['uk']) . ' ' . json_encode($scores),
                                    'reply_to_message_id' => $message->getMessageId()
                                ]);
                            }

                            $translate = $scores['ru'] - $scores['uk'] > 0.02 && $scores['ru'] > 0.1;
                            $sourceLang = 'ru';
                        }
                    } elseif ($cld2score['language_code'] != 'uk') {
                        $translate = true;
                        $sourceLang = $cld2score['language_code'];
                    }

                    if ($translate) {
                        $translator = new Translator($translateConfig['key']);

                        $result = $translator->translateText($text, $sourceLang, 'uk');
                        $percent = 0;
                        $charsText = preg_replace("/[^а-яієїґё]+/u", "", mb_strtolower($text));
                        $charsResult = preg_replace("/[^а-яієїґё]+/u", "", mb_strtolower($result));
                        similar_text($charsText, $charsResult, $percent);
                        if (in_array($message->getChat()->getId(), $translateConfig['debug']) && mb_substr($message->getReplyToMessage()->getText(), 0, 5) != 'GPT: ') {
                            Request::sendMessage([
                                'chat_id' => $message->getChat()->getId(),
                                'text' =>  $percent . ' ' . $charsText . ' ' . $charsResult,
                                'reply_to_message_id' => $message->getMessageId()
                            ]);
                        } elseif (strlen($charsResult) > 8 && $percent < 80) {
                            if ($sourceLang == 'ru' && in_array($message->getChat()->getId(), $translateConfig['delete'])) {
                                $name = trim($message->getFrom()->getFirstName() . ' ' . $message->getFrom()->getLastName());
                                $data = [
                                    'chat_id' => $message->getChat()->getId(),
                                    'text' => 'ПЕРЕКЛАД: <' . $name . '> '. $result,
                                ];
                                if ($message->getReplyToMessage()) {
                                    $data['reply_to_message_id'] =  $message->getReplyToMessage()->getMessageId();
                                }
                                Request::sendMessage($data);

                                Request::deleteMessage([
                                    'chat_id' => $message->getChat()->getId(),
                                    'message_id' => $message->getMessageId(),
                                ]);
                            } else {
                                Request::sendMessage([
                                    'chat_id' => $message->getChat()->getId(),
                                    'text' => 'ПЕРЕКЛАД: ' . $result,
                                ]);
                            }
                        }
                    }
                } catch (Exception $e) {
                    TelegramLog::error($e->getMessage());
                }
            }
        }

        if (
            $message->getReplyToMessage()
            && mb_substr($message->getReplyToMessage()->getText(), 0, 5) == 'GPT: '
        ) {
            $this->getTelegram()->executeCommand('gpt');
        }

        $this->getTelegram()->executeCommand('fishing');

        $this->getTelegram()->executeCommand('chatter');

        return Request::emptyResponse();
    }
}

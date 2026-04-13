<?php

/**
 * Callback query command
 *
 * Handles inline keyboard button presses (e.g., report mute button).
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;

class CallbackqueryCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'callbackquery';

    /**
     * @var string
     */
    protected $description = 'Handle callback query';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * Main command execution
     *
     * @return ServerResponse
     */
    public function execute(): ServerResponse
    {
        $callbackQuery = $this->getCallbackQuery();
        $data = $callbackQuery->getData();

        // Report mute: rm:{chatId}:{userId}:{messageId}:{ruleNumber}
        if (str_starts_with($data, 'rm:')) {
            return $this->handleReportMute($callbackQuery, $data);
        }

        return $callbackQuery->answer();
    }

    private function handleReportMute(CallbackQuery $callbackQuery, string $data): ServerResponse
    {
        $parts = explode(':', $data);
        if (count($parts) < 4) {
            return $callbackQuery->answer([
                'text' => 'Невірні дані',
                'show_alert' => true,
            ]);
        }

        $chatId = (int) $parts[1];
        $userId = (int) $parts[2];
        $messageId = (int) $parts[3];
        $ruleNumber = $parts[4] ?? null;

        $muteResult = Request::restrictChatMember([
            'chat_id' => $chatId,
            'user_id' => $userId,
            'until_date' => time() + 3600,
        ]);

        if (!$muteResult->getOk()) {
            return $callbackQuery->answer([
                'text' => 'Не вдалося обмежити користувача',
                'show_alert' => true,
            ]);
        }

        $adminFrom = $callbackQuery->getFrom();
        $adminName = $adminFrom->getUsername()
            ? '@' . $adminFrom->getUsername()
            : trim($adminFrom->getFirstName() . ' ' . $adminFrom->getLastName());

        $chatText = !empty($ruleNumber)
            ? "{$adminName} обмежив автора на 1 годину за порушення правила №{$ruleNumber}."
            : "{$adminName} обмежив автора на 1 годину за порушення правил чату.";
        $chatText .= ' Будь ласка, дотримуйтесь правил.';

        Request::sendMessage([
            'chat_id' => $chatId,
            'text' => $chatText,
            'reply_to_message_id' => $messageId,
        ]);

        // Remove the button from the admin message
        $adminMessage = $callbackQuery->getMessage();
        Request::editMessageReplyMarkup([
            'chat_id' => $adminMessage->getChat()->getId(),
            'message_id' => $adminMessage->getMessageId(),
            'reply_markup' => json_encode(['inline_keyboard' => []]),
        ]);

        return $callbackQuery->answer([
            'text' => 'Користувача обмежено на 1 годину',
            'show_alert' => false,
        ]);
    }
}

<?php

/**
 * Admin "/dailytoppost" command
 *
 * Forwards the most reacted message of the day from configured groups to their target channels.
 * Runs daily via cron.
 *
 * Configuration:
 * $telegram->setCommandConfig('dailytoppost', [
 *     '<source_group_chat_id>' => '<target_channel_id>',
 * ]);
 */

namespace Longman\TelegramBot\Commands\AdminCommands;

use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\TelegramLog;
use PDO;
use PDOException;

class DailytoppostCommand extends AdminCommand
{
    /**
     * @var string
     */
    protected $name = 'dailytoppost';

    /**
     * @var string
     */
    protected $description = 'Forward the most reacted message of the day to a channel';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * @var bool
     */
    protected $need_mysql = true;

    /**
     * Command execute method
     *
     * @return ServerResponse
     */
    public function execute(): ServerResponse
    {
        $config = array_filter(
            $this->getConfig(),
            static fn($channelId, $chatId) => is_numeric($chatId) && is_numeric($channelId),
            ARRAY_FILTER_USE_BOTH
        );

        if (empty($config)) {
            TelegramLog::debug('DailytoppostCommand: No valid configuration pairs found.');
            return Request::emptyResponse();
        }

        $pdo = DB::getPdo();

        foreach ($config as $chatId => $channelId) {
            try {
                $topMessage = $this->getTopMessage($pdo, (string) $chatId);

                if ($topMessage === null) {
                    TelegramLog::debug(
                        'DailytoppostCommand: No reactions found for chat ' . $chatId . ' in the last 24 hours.'
                    );
                    continue;
                }

                $result = Request::forwardMessage([
                    'chat_id'      => $channelId,
                    'from_chat_id' => $chatId,
                    'message_id'   => $topMessage['message_id'],
                ]);

                if (!$result->isOk()) {
                    TelegramLog::error(
                        'DailytoppostCommand: Failed to forward message ' . $topMessage['message_id']
                        . ' from ' . $chatId . ' to ' . $channelId
                        . ': ' . $result->getDescription()
                    );
                }
            } catch (PDOException $e) {
                TelegramLog::error('DailytoppostCommand: ' . $e->getMessage());
            }
        }

        $this->cleanupOldReactions($pdo);

        return Request::emptyResponse();
    }

    /**
     * Get the message with the most reactions in the last 24 hours.
     * Checks both message_reaction_count (anonymous) and message_reaction (per-user) tables.
     *
     * @param PDO $pdo
     * @param string $chatId
     *
     * @return array|null ['message_id' => int, 'total' => int]
     */
    private function getTopMessage(PDO $pdo, string $chatId): ?array
    {
        return $this->getTopFromReactionCount($pdo, $chatId)
            ?? $this->getTopFromReaction($pdo, $chatId);
    }

    /**
     * Get top message from anonymous aggregate reactions (message_reaction_count)
     *
     * @param PDO $pdo
     * @param string $chatId
     *
     * @return array|null
     */
    private function getTopFromReactionCount(PDO $pdo, string $chatId): ?array
    {
        $sql = "
            SELECT mrc.message_id, mrc.reactions
            FROM `message_reaction_count` mrc
            INNER JOIN (
                SELECT message_id, MAX(id) as max_id
                FROM `message_reaction_count`
                WHERE chat_id = :chat_id
                  AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)
                GROUP BY message_id
            ) latest ON mrc.id = latest.max_id
        ";

        $sth = $pdo->prepare($sql);
        $sth->bindValue(':chat_id', $chatId, PDO::PARAM_STR);
        $sth->execute();

        $rows = $sth->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return null;
        }

        $topMessageId = null;
        $topTotal = 0;

        foreach ($rows as $row) {
            $reactions = json_decode($row['reactions'], true);

            if (!is_array($reactions)) {
                continue;
            }

            $total = 0;
            foreach ($reactions as $reaction) {
                $total += $reaction['total_count'] ?? 0;
            }

            if ($total > $topTotal) {
                $topTotal = $total;
                $topMessageId = $row['message_id'];
            }
        }

        if ($topMessageId === null || $topTotal === 0) {
            return null;
        }

        return ['message_id' => $topMessageId, 'total' => $topTotal];
    }

    /**
     * Get top message from per-user reactions (message_reaction)
     *
     * @param PDO $pdo
     * @param string $chatId
     *
     * @return array|null
     */
    private function getTopFromReaction(PDO $pdo, string $chatId): ?array
    {
        $sql = "
            SELECT mr.message_id, mr.new_reaction
            FROM `message_reaction` mr
            INNER JOIN (
                SELECT user_id, message_id, MAX(id) as max_id
                FROM `message_reaction`
                WHERE chat_id = :chat_id
                  AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)
                GROUP BY user_id, message_id
            ) latest ON mr.id = latest.max_id
        ";

        $sth = $pdo->prepare($sql);
        $sth->bindValue(':chat_id', $chatId, PDO::PARAM_STR);
        $sth->execute();

        $rows = $sth->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return null;
        }

        $totals = [];

        foreach ($rows as $row) {
            $reactions = json_decode($row['new_reaction'], true);

            if (!is_array($reactions) || empty($reactions)) {
                continue;
            }

            $messageId = $row['message_id'];
            $totals[$messageId] = ($totals[$messageId] ?? 0) + count($reactions);
        }

        if (empty($totals)) {
            return null;
        }

        arsort($totals);
        $topMessageId = array_key_first($totals);

        return ['message_id' => $topMessageId, 'total' => $totals[$topMessageId]];
    }

    /**
     * Clean up reaction data older than 2 days
     *
     * @param PDO $pdo
     */
    private function cleanupOldReactions(PDO $pdo): void
    {
        try {
            $pdo->exec(
                "DELETE FROM `message_reaction_count` WHERE `created_at` < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)"
            );
            $pdo->exec(
                "DELETE FROM `message_reaction` WHERE `created_at` < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)"
            );
        } catch (PDOException $e) {
            TelegramLog::error('DailytoppostCommand cleanup: ' . $e->getMessage());
        }
    }
}

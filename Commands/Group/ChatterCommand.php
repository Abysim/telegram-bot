<?php


namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\ChatAction;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\TelegramLog;
use PDO;

class ChatterCommand extends CustomSystemCommand
{
    /**
     * @var string
     */
    protected $name = 'chatter';

    /**
     * @var string
     */
    protected $description = 'Chatter';

    /**
     * @var string
     */
    protected $version = '1.0.0';


    private function getWord($sentence)
    {
        $words = explode(' ', $sentence);
        if(is_string($words)) {
            return $words;
        }
        return $words[rand(0,count($words)-1)];
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
        $config = $this->getConfig();
        $parts = explode('<===<===<===', $message->getText(true));
        $text = str_replace(' ', "⬤", trim(str_replace("⬤", "\n", $parts[0])));
        $triggerText = $parts[1] ?? '';
        $triggerText = trim(str_replace("⬤", "\n", $triggerText));
        $telegram = $this->getTelegram();

        $firstChar = mb_substr($text, 0, 1);
        if (
            !$telegram->isDbEnabled()
            || (
                $firstChar != '@'
                && $firstChar != '*'
                && !is_numeric($firstChar)
                && mb_strtoupper($firstChar) == mb_strtolower($firstChar)
            )
            || (
                $message->getReplyToMessage()
                && mb_substr($message->getReplyToMessage()->getText(), 0, 10) == 'ПЕРЕКЛАД: '
            )
        ) {
            return Request::emptyResponse();
        }

        $pdo = DB::getPdo();

        $sql = "SELECT `word` FROM `banned_words`";
        $sth = $pdo->prepare($sql);
        $sth->execute();
        $result = $sth->fetchAll(PDO::FETCH_ASSOC);
        $bannedWords = array_column($result, 'word');

        [$parts, $words, $keywords, $aux, $isTriggered] = $this->getWords($text, $bannedWords);

        if (count($keywords) == 0) {
            return Request::emptyResponse();
        }

        if (in_array($message->getChat()->getId(), $config['speak_chats'])) {
            if ($isTriggered
                || (
                    $message->getReplyToMessage()
                    && $message->getReplyToMessage()->getFrom()->getId() == $telegram->getBotId()
                )
            ) {
                $responseAux = [];
                $filteredAux = [];
                foreach ($aux as $part) {
                    $lcPart = mb_strtolower($part);
                    if (isset($config['antonyms'][$lcPart])) {
                        $responseAux[] = $config['antonyms'][$lcPart];
                    } else {
                        $filteredAux[] = $part;
                    }
                }
                $uniqueParts = array_unique(array_merge($keywords, $filteredAux, $responseAux));
                $inParams = [];
                $in = [];
                foreach ($uniqueParts as $id => $item) {
                    $key = ':id' . $id;
                    $in[] = $key;
                    $inParams[$key] = $item;
                }
                $in = implode(',', $in);

                $sql = "
                    SELECT `id`, `word`
                    FROM `words`
                    WHERE `word` IN ($in)
                ";
                $sth = $pdo->prepare($sql);
                $sth->execute($inParams);
                $result = $sth->fetchAll(PDO::FETCH_ASSOC);
                $indexedWords = array_column($result, 'word', 'id');

                //if ($message->getChat()->getId() == '-504422047') {
                //    $this->replyToChat(
                //        json_encode(array_merge($responseKeywords, [' '], $responseAux)),
                //        ['reply_to_message_id' => $message->getMessageId()]
                //    );
                //}

                $idKeywords = $this->toIds($keywords, $indexedWords);
                $idFilteredAux = $this->toIds($filteredAux, $indexedWords);
                $idResponseAuxConfig = $this->toIds($responseAux, $indexedWords);
                //$idKeywords = [$idKeywords[array_rand($idKeywords)]];

                if (in_array($message->getChat()->getId(), $config['debug_chats'])) {
                    $this->replyToChat("!"
                         . "<b>Клічові слова-трігери:</b> " . implode(",", $keywords)
                        //. "\n<b>Їх айді:</b> " . implode(",", $idKeywords)
                        . "\n<b>Допоміжні слова-трігери:</b> " . implode(",", $filteredAux)
                        //. "\n<b>Їх айді:</b> " . implode(",", $idFilteredAux)
                        . "\n<b>Допоміжні слова з конфігу:</b> " . implode(",", $responseAux)
                        //. "\n<b>Їх айді:</b> " . implode(",", $idResponseAuxConfig)
                        ,
                        ['reply_to_message_id' => $message->getMessageId(), 'parse_mode' => 'HTML']
                    );
                }
                //TelegramLog::debug(var_export([
                //    'idKeywords' => $idKeywords,
                //    'idFilteredAux' => $idFilteredAux,
                //    'idResponseAux' => $idResponseAux,
                //], true));

                $inParams = [];
                $in = [];
                foreach (array_merge($idKeywords, $idFilteredAux) as $id => $item) {
                    $key = ':id' . $id;
                    $in[] = $key;
                    $inParams[$key] = $item;
                }
                $in = implode(',', $in);

                $sql = "
                    SELECT `trig`, `resp`
                    FROM `responses`
                    WHERE `trig` IN ($in)
                ";
                $sth = $pdo->prepare($sql);
                $sth->execute($inParams);
                $result = $sth->fetchAll(PDO::FETCH_ASSOC);

                $idResponseKeywords = [];
                $idResponseAux = [];
                foreach ($result as $response) {
                    if (in_array($response['trig'], $idKeywords)) {
                        $idResponseKeywords[$response['trig']] = $idResponseKeywords[$response['trig']] ?? [];
                        $idResponseKeywords[$response['trig']][] = $response['resp'];
                    } else {
                        $idResponseAux[$response['trig']] = $idResponseAux[$response['trig']] ?? [];
                        $idResponseAux[$response['trig']][] = $response['resp'];
                    }
                }

                $idResponseKeywordsFinal = null;
                $keywordsToInclude = [];
                $additionKeywordsToInclude = [];
                foreach ($idResponseKeywords as $trigId => $resps) {
                    if (/*count($resps) > 255 || */empty($resps)) {
                        $keywordsToInclude[] = $trigId;

                        continue;
                    }

                    if (is_null($idResponseKeywordsFinal)) {
                        $idResponseKeywordsFinal = $resps;
                        $additionKeywordsToInclude[] = $trigId;
                    } else {
                        $inter = array_intersect($idResponseKeywordsFinal, $resps);
                        if (!empty($inter)) {
                            $additionKeywordsToInclude[] = $trigId;
                            $idResponseKeywordsFinal = $inter;
                        } elseif (!empty($idResponseKeywordsFinal) && count($resps) < count($idResponseKeywordsFinal)) {
                            $idResponseKeywordsFinal = $resps;

                            $keywordsToInclude = array_merge($keywordsToInclude, $additionKeywordsToInclude);
                            $additionKeywordsToInclude = [$trigId];
                        } else {
                            $keywordsToInclude[] = $trigId;
                        }
                    }
                }

                $idResponseKeywordsFinal = array_merge($idResponseKeywordsFinal, $keywordsToInclude, array_diff(
                    $idKeywords,
                    array_keys($idResponseKeywords)
                ));

                $idResponseAuxFinal = null;
                foreach ($idResponseAux as $resps) {
                    if (empty($resps)) {
                        continue;
                    }

                    if (is_null($idResponseAuxFinal)) {
                        $idResponseAuxFinal = $resps;
                    } else {
                        $inter = array_intersect($idResponseAuxFinal, $resps);
                        if (!empty($inter)) {
                            $idResponseAuxFinal = $inter;
                        } elseif (!empty($idResponseAuxFinal) && count($resps) < count($idResponseAuxFinal)) {
                            $idResponseAuxFinal = $resps;
                        }
                    }
                }

                //TelegramLog::debug(var_export([
                //    '$idResponseKeywordsFinal' => $idResponseKeywordsFinal,
                //    '$idResponseAuxFinal' => $idResponseAuxFinal,
                //    '$idResponseAuxConfig' => $idResponseAuxConfig,
                //], true));

                $idKeywords = array_unique($idResponseKeywordsFinal ?? []);
                $idAux = array_unique(array_merge($idResponseAuxFinal ?? [], $idResponseAuxConfig));

                if (in_array($message->getChat()->getId(), $config['debug_chats'])) {
                    $inParams = [];
                    $in = [];
                    foreach (array_merge($idKeywords, $idAux) as $id => $item) {
                        $key = ':id' . $id;
                        $in[] = $key;
                        $inParams[$key] = $item;
                    }
                    $in = implode(',', $in);

                    $sql = "
                            SELECT `id`, `word`
                            FROM `words`
                            WHERE `id` IN ($in)
                        ";
                    $sth = $pdo->prepare($sql);
                    $sth->execute($inParams);
                    $result = $sth->fetchAll(PDO::FETCH_ASSOC);
                    $tempIndexedWords = array_column($result, 'word', 'id');
                    $tempKeywords = [];
                    $tempAux = [];
                    foreach ($idKeywords as $idKeyword) {
                        if (isset($tempIndexedWords[$idKeyword])) {
                            $tempKeywords[] = $tempIndexedWords[$idKeyword];
                        }
                    }
                    foreach ($idAux as $idAu) {
                        if (isset($tempIndexedWords[$idAu])) {
                            $tempAux[] = $tempIndexedWords[$idAu];
                        }
                    }

                    $this->replyToChat("!!"
                        . "<b>Використані ключові слова:</b> " . implode(",", $tempKeywords)
                        //. "\n<b>Їх айді:</b> " . implode(",", $idKeywords)
                        . "\n<b>Використані допоміжні слова:</b> " . implode(",", $tempAux)
                        //. "\n<b>Їх айді:</b> " . implode(",", $idAux)
                        ,
                    ['reply_to_message_id' => $message->getMessageId(), 'parse_mode' => 'HTML']
                    );
                }

                //TelegramLog::debug(var_export([
                //    'idKeywords' => $idKeywords,
                //    'idAux' => $idAux,
                //], true));

                if (count($idKeywords) > 0) {
                    Request::sendChatAction([
                        'chat_id' => $message->getChat()->getId(),
                        'action'  => ChatAction::TYPING,
                    ]);

                    $keyWordId = array_rand($idKeywords);
                    $keyword = $idKeywords[$keyWordId];
                    unset($idKeywords[$keyWordId]);
                    //$idKeywords = array_merge($idKeywords, $idAux);

                    $sql = "
                        SELECT `k1`,`k2`,`k3`,`k4`,`k5`
                        FROM `brain`
                        WHERE `k1` = :key OR `k2` = :key OR `k3` = :key OR `k4` = :key OR `k5` = :key
                    ";
                    $sth = $pdo->prepare($sql);
                    $sth->execute([':key' => $keyword]);
                    $result = $sth->fetchAll(PDO::FETCH_ASSOC);

                    $enders = [];
                    $potential = [];
                    $potentialByAux = [];
                    foreach ($result as $quad) {
                        if ($quad['k1'] == 0 || $quad['k5'] == 1) {
                            $enders[] = $quad;
                        }

                        if (!empty($idKeywords) || !empty($idAux)) {
                            $isAuxFound = false;
                            for ($i = 1; $i <= 5; $i++) {
                                if (in_array($quad['k' . $i], $idKeywords)) {
                                    $potential[] = $quad;

                                    break;
                                }
                                if (!$isAuxFound && in_array($quad['k' . $i], $idAux)) {
                                    $potentialByAux[] = $quad;
                                    $isAuxFound = true;
                                }
                            }
                        }
                    }

                    if (!empty($potential)) {
                        $final = $potential[array_rand($potential)];

                        for ($i = 1; $i <= 5; $i++) {
                            $key = array_search($final['k' . $i], $idKeywords);
                            if ($key !== false) {
                                unset($idKeywords[$key]);
                            }

                            $key = array_search($final['k' . $i], $idAux);
                            if ($key !== false) {
                                unset($idKeywords[$key]);
                            }
                        }
                    } elseif (!empty($potentialByAux)) {
                        $final = $potentialByAux[array_rand($potentialByAux)];

                        for ($i = 1; $i <= 5; $i++) {
                            $key = array_search($final['k' . $i], $idAux);
                            if ($key !== false) {
                                unset($idKeywords[$key]);
                            }
                        }
                    } elseif (!empty($idKeywords) || empty($enders)) {
                        $final = $result[array_rand($result)];
                    } else {
                        $final = $enders[array_rand($enders)];
                    }

                    $isLastByAux = false;
                    $sentence = array_values($final);
                    $i = 0;
                    while ($sentence[0] != 0 || $sentence[count($sentence) - 1] != 1) {
                        if ($i > 255) {
                            break;
                        }

                        if ($sentence[0] != 0) {
                            Request::sendChatAction([
                                'chat_id' => $message->getChat()->getId(),
                                'action'  => ChatAction::TYPING,
                            ]);

                            if (mb_strpos($config['word_chars'], mb_substr($sentence[0], 0, 1)) === false) {
                                $sql = "
                                    SELECT `k1`
                                    FROM `brain`
                                    WHERE `k2` = :k2 AND `k3` = :k3 AND `k4` = :k4 AND `k5` = :k5
                                ";

                                $sth = $pdo->prepare($sql);
                                $sth->execute([
                                    ':k2' => $sentence[0],
                                    ':k3' => $sentence[1],
                                    ':k4' => $sentence[2],
                                    ':k5' => $sentence[3],
                                ]);
                            } else {
                                $sql = "
                                    SELECT `k1`, `k2`
                                    FROM `brain`
                                    WHERE `k3` = :k3 AND `k4` = :k4 AND `k5` = :k5
                                ";

                                $sth = $pdo->prepare($sql);
                                $sth->execute([
                                    ':k3' => $sentence[0],
                                    ':k4' => $sentence[1],
                                    ':k5' => $sentence[2],
                                ]);
                            }
                            $result = $sth->fetchAll(PDO::FETCH_ASSOC);

                            $enders = [];
                            $potential = [];
                            $potentialByAux = [];
                            foreach ($result as $val) {
                                if ($val['k1'] == 0) {
                                    $enders[] = $val;

                                    if (empty($idKeywords)) {
                                        break;
                                    }
                                }

                                if (in_array($val['k1'], $idKeywords)) {
                                    $potential[] = $val;
                                } elseif (!$isLastByAux && in_array($val['k1'], $idAux)) {
                                    $potentialByAux[] = $val;
                                }
                            }

                            if (empty($result)) {
                                $final = [0];
                            } elseif (!empty($potential)) {
                                $final = $potential[array_rand($potential)];
                                $isLastByAux = false;

                                $key = array_search($final['k1'], $idKeywords);
                                if ($key !== false) {
                                    unset($idKeywords[$key]);
                                }
                            } elseif (!empty($potentialByAux)) {
                                $final = $potentialByAux[array_rand($potentialByAux)];
                                $isLastByAux = true;

                                $key = array_search($final['k1'], $idAux);
                                if ($key !== false) {
                                    unset($idAux[$key]);
                                }
                            } elseif (!empty($idKeywords) || empty($enders)) {
                                $final = $result[array_rand($result)];
                            } else {
                                $final = $result[array_rand($enders)];
                            }

                            array_unshift($sentence, ...array_values($final));
                        }

                        if ($sentence[count($sentence) - 1] != 1) {
                            Request::sendChatAction([
                                'chat_id' => $message->getChat()->getId(),
                                'action'  => ChatAction::TYPING,
                            ]);

                            if (mb_strpos($config['word_chars'], mb_substr($sentence[count($sentence) - 1], 0, 1)) === false) {
                                $sql = "
                                    SELECT `k5`
                                    FROM `brain`
                                    WHERE `k1` = :k1 AND `k2` = :k2 AND `k3` = :k3 AND `k4` = :k4
                                ";

                                $sth = $pdo->prepare($sql);
                                $sth->execute([
                                    ':k1' => $sentence[count($sentence) - 4],
                                    ':k2' => $sentence[count($sentence) - 3],
                                    ':k3' => $sentence[count($sentence) - 2],
                                    ':k4' => $sentence[count($sentence) - 1],
                                ]);
                            } else {
                                $sql = "
                                    SELECT `k4`,`k5`
                                    FROM `brain`
                                    WHERE `k1` = :k1 AND `k2` = :k2 AND `k3` = :k3
                                ";

                                $sth = $pdo->prepare($sql);
                                $sth->execute([
                                    ':k1' => $sentence[count($sentence) - 3],
                                    ':k2' => $sentence[count($sentence) - 2],
                                    ':k3' => $sentence[count($sentence) - 1],
                                ]);
                            }

                            $result = $sth->fetchAll(PDO::FETCH_ASSOC);

                            $enders = [];
                            $potential = [];
                            $potentialByAux = [];
                            foreach ($result as $val) {
                                if ($val['k5'] == 1) {
                                    $enders[] = $val;

                                    if (empty($idKeywords)) {
                                        break;
                                    }
                                }

                                if (in_array($val['k5'], $idKeywords)) {
                                    $potential[] = $val;
                                } elseif (!$isLastByAux && in_array($val['k5'], $idAux)) {
                                    $potentialByAux[] = $val;
                                }
                            }

                            if (empty($result)) {
                                $final = [1];
                            } elseif (!empty($potential)) {
                                $final = $potential[array_rand($potential)];
                                $isLastByAux = false;

                                $key = array_search($final['k5'], $idKeywords);
                                if ($key !== false) {
                                    unset($idKeywords[$key]);
                                }
                            } elseif (!empty($potentialByAux)) {
                                $final = $potentialByAux[array_rand($potentialByAux)];
                                $isLastByAux = true;

                                $key = array_search($final['k5'], $idKeywords);
                                if ($key !== false) {
                                    unset($idKeywords[$key]);
                                }
                            } elseif (!empty($idKeywords) || empty($enders)) {
                                $final = $result[array_rand($result)];
                            } else {
                                $final = $enders[array_rand($enders)];
                            }

                             array_push($sentence, ...array_values($final));
                        }

                        $i++;
                    }

                    //$this->replyToChat(
                    //    json_encode($sentence),
                    //    ['reply_to_message_id' => $message->getMessageId()]
                    //);

                    Request::sendChatAction([
                        'chat_id' => $message->getChat()->getId(),
                        'action'  => ChatAction::TYPING,
                    ]);

                    $uniqueSentenceParts = array_unique($sentence);
                    $inParams = [];
                    $in = [];
                    foreach ($uniqueSentenceParts as $id) {
                        $key = ':id' . $id;
                        $in[] = $key;
                        $inParams[$key] = $id;
                    }
                    $in = implode(',', $in);

                    $sql = "
                        SELECT `id`, `word`
                        FROM `words`
                        WHERE `id` IN ($in)
                    ";
                    $sth = $pdo->prepare($sql);
                    $sth->execute($inParams);
                    $result = $sth->fetchAll(PDO::FETCH_ASSOC);
                    $indexedSentenceWords = array_column($result, 'word', 'id');

                    $humanSentence = '';
                    foreach ($sentence as $idWord) {
                        $humanSentence .= $indexedSentenceWords[$idWord];
                    }

                    $data = ['reply_to_message_id' => $message->getMessageId()];
                    $username = '';
                    if (in_array(2, $sentence)) {
                        $username = $message->getFrom()->getUsername();
                        if (empty($username)) {
                            $data['parse_mode'] = 'HTML';
                            $username = "<a href=\"tg://user?id={$message->getFrom()->getId()}\">{$message->getFrom()->getFirstName()}</a>";
                        }
                    }
                    $this->replyToChat(trim(str_replace(['⬤', 'VUFFUN'], [' ', $username], $humanSentence)), $data);
                }
            }
        }

        if ($message->getCommand() == 'teach' || in_array($message->getChat()->getId(), $config['learn_chats'])) {
            if (count($parts) > 2) {
                if (in_array(mb_strtolower($words[0]), $config['triggers'])) {
                    $parts = array_slice($parts, array_search($words[1], $parts));
                }
                $uniqueParts = array_unique($parts);

                $inParams = [];
                $in = [];
                foreach ($uniqueParts as $id => $item) {
                    $key = ':id' . $id;
                    $in[] = $key;
                    $inParams[$key] = $item;
                }
                $in = implode(',', $in);

                $sql = "
                    SELECT `id`, `word`
                    FROM `words`
                    WHERE `word` IN ($in)
                ";
                $sth = $pdo->prepare($sql);
                $sth->execute($inParams);
                $result = $sth->fetchAll(PDO::FETCH_ASSOC);
                $indexedWords = array_column($result, 'word', 'id');

                foreach ($indexedWords as $word) {
                    $key = array_search($word, $uniqueParts);
                    if ($key !== false) {
                        unset($uniqueParts[$key]);
                    }
                }

                if (!empty($uniqueParts)) {
                    $params = [];
                    $values = [];
                    $in = [];
                    foreach ($uniqueParts as $id => $item) {
                        $key = ':id' . $id;
                        $values[] = "($key)";
                        $in[] = $key;
                        $params[$key] = $item;
                    }
                    $values = implode(',', $values);
                    $in = implode(',', $in);

                    $sql = "
                        INSERT IGNORE INTO `words` (`word`)
                        VALUES $values
                    ";

                    $sth = $pdo->prepare($sql);
                    $sth->execute($params);


                    $sql = "
                        SELECT `id`, `word`
                        FROM `words`
                        WHERE `word` IN ($in)
                    ";
                    $sth = $pdo->prepare($sql);
                    $sth->execute($params);
                    $result = $sth->fetchAll(PDO::FETCH_ASSOC);

                    $indexedWords = $indexedWords + array_column($result, 'word', 'id');
                }

                $idParts = $this->toIds($parts, $indexedWords);
                array_unshift($idParts, 0);
                $idParts[] = 1;

                $params = [];
                $values = [];
                for ($i = 0; $i < count($idParts) - 4; $i++) {
                    $in = [];
                    for ($j = $i; $j < $i + 5; $j++) {
                        $key = ':id' . $i . $j;
                        $in[] = "$key";
                        $params[$key] = $idParts[$j];
                    }
                    $values[] = '(' . implode(',', $in) . ')';
                }
                $values = implode(',', $values);

                $sql = "
                        INSERT IGNORE INTO `brain` (`k1`,`k2`,`k3`,`k4`,`k5`)
                        VALUES $values
                    ";

                $sth = $pdo->prepare($sql);
                $sth->execute($params);

                if (empty($triggerText)) {
                    if (!empty($message->getReplyToMessage())) {
                        $triggerText = $message->getReplyToMessage()->getText(true) ?? '';
                    } else {
                        $sql = '
                        SELECT `id`, `text`
                        FROM `message`
                        WHERE `chat_id` = :chat_id AND `id` < :id
                        ORDER BY `id` DESC
                        LIMIT 1';
                        $sth = $pdo->prepare($sql);
                        $sth->bindValue(':chat_id', $message->getChat()->getId());
                        $sth->bindValue(':id', $message->getMessageId());
                        $sth->execute();
                        $result = $sth->fetchAll(PDO::FETCH_ASSOC);
                        if (!empty($result[0]['text'])) {
                            $sql = '
                            SELECT `text`
                            FROM `edited_message`
                            WHERE `chat_id` = :chat_id AND `message_id` = :id
                            LIMIT 1
                        ';
                            $sth = $pdo->prepare($sql);
                            $sth->bindValue(':chat_id', $message->getChat()->getId());
                            $sth->bindValue(':id', $result[0]['id']);
                            $sth->execute();
                            $editedResult = $sth->fetchAll(PDO::FETCH_ASSOC);

                            $triggerText = $editedResult[0]['text'] ?? $result[0]['text'] ?? '';
                        }
                    }
                }

                if (!empty($triggerText)) {
                    $triggerText = str_replace(' ', "⬤", trim($triggerText));

                    $firstChar = mb_substr($triggerText, 0, 1);
                    if (
                        $firstChar == '@'
                        || $firstChar == '*'
                        || is_numeric($firstChar)
                        || mb_strtoupper($firstChar) != mb_strtolower($firstChar)
                    ) {
                        [$trigParts, $trigWords, $trigKeywords, $trigAux] = $this->getWords($triggerText, $bannedWords);

                        if (count($trigKeywords) > 0) {
                            $trigAux = array_diff($trigAux, $config['triggers']);
                            $filteredAux = array_diff($aux, $config['triggers']);

                            $uniqueParts = array_unique(array_merge($trigKeywords, $trigAux));

                            $inParams = [];
                            $in = [];
                            foreach ($uniqueParts as $id => $item) {
                                $key = ':id' . $id;
                                $in[] = $key;
                                $inParams[$key] = $item;
                            }
                            $in = implode(',', $in);

                            $sql = "
                                SELECT `id`, `word`
                                FROM `words`
                                WHERE `word` IN ($in)
                            ";
                            $sth = $pdo->prepare($sql);
                            $sth->execute($inParams);
                            $result = $sth->fetchAll(PDO::FETCH_ASSOC);
                            $indexedTrigWords = array_column($result, 'word', 'id');

                            $idKeywords = $this->toIds($keywords, $indexedWords);
                            $idAux = $this->toIds($filteredAux, $indexedWords);

                            $idTrigKeyWords = $this->toIds($trigKeywords, $indexedTrigWords);
                            $idTrigAux = $this->toIds($trigAux, $indexedTrigWords);

                            //TelegramLog::debug(var_export([
                            //    'trigKeywords' => $trigKeywords,
                            //    'keywords' => $keywords,
                            //    'trigAux' => $trigAux,
                            //    'filteredAux' => $filteredAux,
                            //], true));
                            //
                            //
                            //TelegramLog::debug(var_export([
                            //    '$idTrigKeyWords' => $idTrigKeyWords,
                            //    '$idKeywords' => $idKeywords,
                            //    '$idTrigAux' => $idTrigAux,
                            //    '$idAux' => $idAux,
                            //], true));

                            $params = [];
                            $values = [];
                            for ($i = 0; $i < count($idTrigKeyWords); $i++) {
                                for ($j = 0; $j < count($idKeywords); $j++) {
                                    $keyTrig = ':keytrig' . $i . $j;
                                    $keyResp = ':keyresp' . $i . $j;
                                    $values[] = "($keyTrig,$keyResp)";
                                    $params[$keyTrig] = $idTrigKeyWords[$i];
                                    $params[$keyResp] = $idKeywords[$j];
                                }
                            }
                            for ($i = 0; $i < count($idTrigAux); $i++) {
                                for ($j = 0; $j < count($idAux); $j++) {
                                    $keyTrig = ':auxtrig' . $i . $j;
                                    $keyResp = ':auxresp' . $i . $j;
                                    $values[] = "($keyTrig,$keyResp)";
                                    $params[$keyTrig] = $idTrigAux[$i];
                                    $params[$keyResp] = $idAux[$j];
                                }
                            }
                            $values = implode(',', $values);

                            $sql = "
                                INSERT IGNORE INTO `responses` (`trig`,`resp`)
                                VALUES $values
                            ";

                            //TelegramLog::debug(var_export([
                            //    '$values' => $values,
                            //    '$params' => $params,
                            //], true));

                            $sth = $pdo->prepare($sql);
                            $sth->execute($params);
                        }
                    }
                }
            }
        }

        return Request::emptyResponse();
    }

    /**
     * @param $parts
     * @param $indexedWords
     *
     * @return array
     */
    private function toIds($parts, $indexedWords) {
        $idParts = [];
        foreach ($parts as $part) {
            $key = array_search($part, $indexedWords);
            if ($key != false) {
                $idParts[] = $key;
            }
        }

        return $idParts;
    }

    private function getParts($sentence) {
        $wordChars = $this->getConfig('word_chars');
        $sentence = trim($sentence);
        $parts = [];
        $words = [];
        $punctuation = false;
        $isWordChar = false;
        $buffer = '';
        $i = 0;
        while ($i < mb_strlen($sentence)) {
            $ch = mb_substr($sentence, $i, 1);
            $isWordChar = mb_strpos($wordChars, $ch) !== false;
            if ($isWordChar == $punctuation) {
                $punctuation = !$punctuation;
                if (mb_strlen($buffer) > 0) {
                    $parts[] = $buffer;

                    if (!$isWordChar) {
                        $words[] = $buffer;
                    }
                }

                $buffer = '';
            }
            $buffer = $buffer . $ch;
            $i++;
        }

        if (mb_strlen($buffer) > 0) {
            $parts[] = $buffer;

            if ($isWordChar) {
                $words[] = $buffer;
            }
        }

        return [$parts, $words];
    }

    /**
     * @param $text
     *
     * @return array
     */
    private function getWords($text, $bannedWords): array
    {
        $config = $this->getConfig();
        [$parts, $words] = $this->getParts($text);
        $keywords = [];
        $aux = [];
        $isTriggered = false;
        $isLastA = false;
        foreach ($parts as $i => $word) {
            $lcWord = mb_strtolower($word);
            $isHumanWord = in_array($word, $words);
            if ($isHumanWord) {
                if (!$isTriggered && in_array($lcWord, $config['triggers'])) {
                    $isTriggered = true;
                }

                if ($isLastA) {
                    $word = 'VUFFUN';
                    $lcWord = 'vuffun';
                    $parts[$i] = $word;
                }

                $isLastA = false;
            } else {
                $isLastA = mb_substr($word, -1) == '@';
            }

            if ($isHumanWord) {
                if (in_array($lcWord, $bannedWords)) {
                    continue;
                }

                if (in_array($lcWord, $config['auxiliary']) || in_array($lcWord, $config['triggers'])) {
                    if (!in_array($word, $aux)) {
                        $aux[] = $word;
                    }
                } else {
                    if (!in_array($word, $keywords)) {
                        $keywords[] = $word;
                    }
                }
            }
        }

        return [$parts, $words, $keywords, $aux, $isTriggered];
    }
}

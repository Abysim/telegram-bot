<?php

$result = file_get_contents("result.json");
$result = json_decode($result, true);

$fh = fopen('text.csv', "w");


$i = 0;
$prevMessage = '';
$textById = [];
foreach ($result['messages'] as $message) {
    echo ++$i . "\n";

    $text = '';
    if (is_array($message['text'])) {
        foreach ($message['text'] as $part) {
            if (is_string($part)) {
                $text .= $part;
            } elseif (is_array($part) && !empty($part['text'])) {
                $text .= $part['text'];
            }
        }
    }

    if (is_string($message['text']) && mb_strlen(trim($message['text'])) > 0) {
        $text = $message['text'];
    }

    if (mb_substr($text, 0, 1) == '<') {
        $pos = mb_strpos($text, '>');

        if ($pos !== false) {
            $text = mb_substr($text, $pos + 1);
        }
    }

    $text = trim($text);

    $firstChar = mb_substr($text, 0, 1);
    if (
        count(explode(' ', $text)) > 1
        && (
            mb_strtoupper($firstChar) != mb_strtolower($firstChar)
            || ($firstChar == '*')
            || ($firstChar == '@')
            || is_numeric($firstChar)
        )
    ) {
        $triggerText = '';
        if (!empty($message['reply_to_message_id'])) {
            if (isset($textById[$message['reply_to_message_id']])) {
                $triggerText = $textById[$message['reply_to_message_id']];
            }
        } elseif (!empty($prevMessage)) {
            $triggerText = $prevMessage;
        }

        fputcsv($fh, [str_replace("\n", "⬤", $text), str_replace("\n", "⬤", $triggerText)]);

        $textById[$message['id']] = $text;
        $prevMessage = $text;
    } else {
        $prevMessage = '';
    }
}

fclose($fh);
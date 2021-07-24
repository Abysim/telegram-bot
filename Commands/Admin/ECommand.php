<?php


/**
 * Admin "/e" command
 *
 * Excludes specific words from chatter responses relations.
 */

namespace Longman\TelegramBot\Commands\AdminCommands;

use cijic\phpMorphy\Morphy;
use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use PDO;

class ECommand extends AdminCommand
{
    /**
     * @var string
     */
    protected $name = 'e';

    /**
     * @var string
     */
    protected $description = 'Exclude';

    /**
     * @var string
     */
    protected $usage = '/e <word1,word2,word3...>';

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

        $words = explode(',',trim($this->getMessage()->getText(true)));
        $pdo = DB::getPdo();

        $params = [];
        $values = [];
        foreach ($words as $id => $item) {
            $key = ':id' . $id;
            $values[] = "($key)";
            $params[$key] = mb_strtolower((trim($item)));
        }
        $values = implode(',', $values);

        $sql = "
            INSERT IGNORE INTO `banned_words` (`word`)
            VALUES $values
        ";

        $sth = $pdo->prepare($sql);
        $sth->execute($params);

        $result = [];
        foreach ($words as $word) {
            $word = trim($word);
            $result[] = $word;
            $result[] = mb_strtoupper($word);
            $lc = mb_strtolower($word);
            $result[] = $lc;
            $result[] = mb_strtoupper(mb_substr($lc, 0, 1)) . mb_substr($lc, 1);
            $result[] =  mb_substr($lc, 0, -1) . mb_strtoupper(mb_substr($lc, -1));
        }

        $inParams = [];
        $in = [];
        foreach (array_unique($result) as $id => $item) {
            $key = ':id' . $id;
            $in[] = $key;
            $inParams[$key] = $item;
        }
        $in = implode(',', $in);

        $sql = "
                    SELECT `id`
                    FROM `words`
                    WHERE `word` IN ($in)
                ";
        $sth = $pdo->prepare($sql);
        $sth->execute($inParams);
        $result = $sth->fetchAll(PDO::FETCH_ASSOC);
        $ids = array_column($result, 'id');

        if (count($ids) > 0) {
            $inParams = [];
            $in = [];
            foreach ($ids as $item) {
                $key = ':id' . $item;
                $in[] = $key;
                $inParams[$key] = $item;
            }
            $in = implode(',', $in);

            $sql = "
                    DELETE 
                    FROM `responses`
                    WHERE `trig` IN ($in) OR `resp` IN ($in)
                ";
            $sth = $pdo->prepare($sql);
            $sth->execute($inParams);
        }

    }
}

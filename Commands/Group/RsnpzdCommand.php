<?php

/**
 * "/rsnpzd" command
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use Exception;
use Longman\TelegramBot\Commands\SystemCommands\CustomSystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;

class RsnpzdCommand extends CustomSystemCommand
{
    private const STATS = [
        'personnel_units' => 'Особовий склад',
        'tanks' => 'Танки',
        'armoured_fighting_vehicles' => 'Бойовi машини',
        'artillery_systems' => 'Артилерiйськi системи',
        'mlrs' => 'РСЗВ',
        'aa_warfare_systems' => 'Засоби ППО',
        'planes' => 'Літаки',
        'helicopters' => 'Гелікоптери',
        'vehicles_fuel_tanks' => 'Автомобільна техніка та цистерни з паливом',
        'warships_cutters' => 'Кораблi та катери',
        'uav_systems' => 'БПЛА',
        'special_military_equip' => 'Спецiальна технiка',
        'atgm_srbm_systems' => 'Установки ОТРК/ТРК',
        'cruise_missiles' => 'Крилаті ракети',
        'submarines' => 'Підводні човни',
    ];

    private const MAX_LEN = 24;

    private const NUM_LEN = 8;

    private const MONTHS = [
        "сiч",
        "лют",
        "бер",
        "квi",
        "тра",
        "чер",
        "лип",
        "сер",
        "вер",
        "жов",
        "лис",
        "гру",
    ];

    /**
     * @var string
     */
    protected $name = 'rsnpzd';

    /**
     * @var string
     */
    protected $description = 'rsnpzd';

    /**
     * @var string
     */
    protected $usage = '/rsnpzd';

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
        $this->messageIds[] = $message->getMessageId();

        $error = null;
        $result = [];

        try {
            $response = file_get_contents('https://russianwarship.rip/api/v2/statistics/latest');
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        $stat = json_decode($response, true);

        if (isset($stat['message']) && $stat['message'] == 'The data were fetched successfully.') {
            [$year, $month, $day] = explode('-', $stat['data']['date']);
            $result[] = sprintf(
                'Статистика втрат окупантiв станом на %d %s %d (День %d):',
                $day,
                self::MONTHS[$month - 1],
                $year,
                $stat['data']['day']
            );
            $result[] = '<code>';

            foreach (self::STATS as $key => $caption) {
                $result[] = self::createLine($caption, $stat['data']['stats'][$key], $stat['data']['increase'][$key]);
            }

            $result[] = '</code>';
        } else {
            $result[] = 'Пiд час завантаження даних сталася помилка:';
            $result[] = '';

            if (is_null($error)) {
                if (is_null($stat)) {
                    $result[] = 'Хибнi данi';
                } else {
                    $result[] = $stat['message'];
                }
            } else {
                $result[] = $error;
            }

            $result[] = '';
            $result[] = 'Але, незважаючи на це, всеодно';
        }

        $result[] = 'Руснi пизда!';

        $this->messageIds[] = $this->replyToChat(implode("\n", $result), [
            'reply_to_message_id' => $message->getMessageId(),
            'parse_mode' => 'HTML',
        ])->getResult()->getMessageId();

        return $this->deleteMessages();
    }

    /**
     * @param string $caption
     * @param int $total
     * @param int $increase
     *
     * @return string
     */
    private static function createLine(string $caption, int $total, int $increase): string
    {
        $inc = '';
        $tot = '';
        $cap = '';

        if (mb_strlen($caption) > self::MAX_LEN - 1) {
            $spacePos = mb_strpos(strrev(mb_substr($caption, 0 , self::MAX_LEN)), ' ');
            $cap = mb_substr($caption, 0, self::MAX_LEN - 1 - $spacePos) . "\n";
            $caption = ' ' . mb_substr($caption, self::MAX_LEN - $spacePos - 1);
        }
        $cap .= $caption . ':' . str_repeat('.', self::MAX_LEN - 1 - mb_strlen($caption));

        if ($total > 0) {
            $tot = $total;

            if ($increase > 0) {
                $inc .=  str_repeat(' ', self::NUM_LEN - strlen($tot)) . "[+$increase]";
            }
        }

        return $cap . $tot . $inc;
    }
}

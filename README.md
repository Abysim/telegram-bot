# Особистий Telegram-бот

Бот на PHP Telegram Bot для особистих цілей, в котрому реалізовано наступний функціонал.

## Риболовля
Гра, що дозволяє копати, риболовити та полювати в чаті. Основана на грі від Rex для Eggdrop, що основана на ідеї від Nerfbendr.

## Балаканина
Примітивний штучний інтелект на ланцюгах Маркова, що натхнений MegaHAL, але більш простий і виокристовує MySQL для збереження даних.
Має примітивний функціонал запам'ятовування, які слова говоряться у відповідь на слова у виразі. 
Присутні команди, що дозволяють видаляти зв'язки між словами-відповідями, навчати певній фразі та навчати з фраз в CSV.
Дадається скрипт, що конвертує лог чату з JSON в такий CSV.
Є опція, що дозволяє відлагоджувати бота.

## Інше
- Автоматичне видалення всіх нових повідомлень з певного чату і пересилка їх в зданий чат.
- Можливість писати повідомлення від імені бота в певний чат.
- Функціонал запуску команд в іншому процесі після паузи.
- Команда видалення заданих повідомлень в заданому чаті.
- Можливість запускати очистку БД в фоні з виводом статусу першому адміну.
- Команда отримання всіх форм слова в українській і російській мовах за допомогою бібліотеки phpMorphy.
- Збереження в БД повідомлень, що були відправлені самим ботом.

[core-github]: https://github.com/php-telegram-bot/core "php-telegram-bot/core"
[core-readme-github]: https://github.com/php-telegram-bot/core#readme "PHP Telegram Bot - README"
[bot-manager-github]: https://github.com/php-telegram-bot/telegram-bot-manager "php-telegram-bot/telegram-bot-manager"
[bot-manager-readme-github]: https://github.com/php-telegram-bot/telegram-bot-manager#readme "PHP Telegram Bot Manager - README"
[phpmorphy]: https://github.com/cijic/phpmorphy "cijic/phpmorphy"
[composer]: https://getcomposer.org/ "Composer"

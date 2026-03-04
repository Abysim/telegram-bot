<!-- Generated: 2026-03-05 | Updated: 2026-03-05 -->

# telegram-bot

## Purpose
A personal Telegram bot built on the PHP Telegram Bot library (`longman/telegram-bot`). Features a fishing/hunting/digging game, a MegaHAL-inspired Markov chain chatbot ("Chatter"), OpenAI GPT integration, automatic message translation (DeepL + AWS Comprehend), message proxying between chats, admin tools, and Russian war losses statistics display. The bot is primarily Ukrainian-language oriented.

## Key Files

| File | Description |
|------|-------------|
| `hook.php` | **Main entry point** - webhook handler that receives Telegram updates |
| `manager.php` | Alternative entry point using TelegramBotManager |
| `getUpdatesCLI.php` | CLI-based polling entry point (getUpdates method) |
| `exe.php` | Background command executor - runs bot commands in a separate process (requires secret) |
| `cron.php` | Cron job runner - executes scheduled commands (currently `/customcleanup`) |
| `set.php` | Webhook registration script |
| `unset.php` | Webhook removal script (requires secret via query param) |
| `config.php` | **Main configuration** - API keys, DB credentials, command paths, all feature configs |
| `config.example.php` | Configuration template with documented options |
| `fishing_config.php` | Fishing game configuration (targets, places, messages, cooldowns) |
| `fishing_config.example.php` | Fishing game config template |
| `chatter_config.php` | Chatter AI configuration (word chars, triggers, antonyms, auxiliary words) |
| `chatter_config.example.php` | Chatter config template |
| `fishing_structure.sql` | Database schema for fishing game tables (`fishing_trophy`, `fishing_time`) |
| `chatter_structure.sql` | Database schema for chatter tables (`words`, `brain`, `responses`, `banned_words`) |
| `chatter_json_to_csv.php` | Utility to convert Telegram chat JSON export to CSV for chatter training |
| `composer.json` | Composer dependencies and project metadata |
| `phpcs.xml.dist` | PHP CodeSniffer configuration |
| `.gitignore` | Git ignore rules |

## Subdirectories

| Directory | Purpose |
|-----------|---------|
| `Commands/` | All bot command handlers organized by category (see `Commands/AGENTS.md`) |
| `temp/` | Temporary/working files for data processing scripts (not version-controlled content) |
| `vendor/` | Composer dependencies (git-ignored) |

## For AI Agents

### Working In This Directory
- **Never modify** `config.php`, `fishing_config.php`, or `chatter_config.php` - they contain production secrets and chat IDs. Use the `.example.php` variants as reference.
- All commands extend either `SystemCommand`, `AdminCommand`, `UserCommand`, or the custom `CustomSystemCommand` base class.
- The bot uses the `Longman\TelegramBot` namespace. Custom commands live in `Longman\TelegramBot\Commands\SystemCommands`, `AdminCommands`, or `UserCommands`.
- Entry points (`hook.php`, `exe.php`, `cron.php`) all follow the same pattern: load autoloader, load config, create `Telegram` instance, configure, then handle/run.
- `exe.php` is used for background execution (e.g., delayed message deletion). It requires the secret as `$argv[1]`.
- The project uses MySQL for persistence. Key custom tables: `fishing_trophy`, `fishing_time`, `words`, `brain`, `responses`, `banned_words`.

### Testing Requirements
- Run `composer check-code` for PHP CodeSniffer validation
- No automated test suite exists; test manually via Telegram
- Use `php -l <file>` for syntax checking

### Common Patterns
- Commands return `ServerResponse` from their `execute()` method
- `Request::emptyResponse()` is used when no reply is needed
- `$this->replyToChat()` for sending messages back
- `$this->getConfig()` retrieves command-specific configuration from `config.php`
- `DB::getPdo()` for direct database access via PDO
- Messages are often deleted after a timeout using `CustomSystemCommand::deleteMessages()` which spawns `exe.php` in background

## Dependencies

### External
- `longman/telegram-bot` 0.76.1 - Core Telegram Bot framework
- `monolog/monolog` ^2.2 - Logging
- `openai-php/client` ^0.10.1 - OpenAI API client for GPT command
- `deeplcom/deepl-php` ^1.5 - DeepL translation API
- `aws/aws-sdk-php` ^3.312 - AWS Comprehend for language detection
- `cijic/phpmorphy` ^0.3.1 - Ukrainian/Russian morphological analysis
- `landrok/language-detector` ^1.3 - Language detection fallback

### Infrastructure
- PHP 7.4+ with extensions: pdo, json, mbstring
- MySQL database
- XAMPP (local development)
- Webhook or polling mode for Telegram API

<!-- MANUAL: Any manually added notes below this line are preserved on regeneration -->

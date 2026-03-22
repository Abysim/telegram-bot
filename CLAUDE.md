# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Personal Telegram bot (PHP) built on `longman/telegram-bot` 0.83.1. Features: fishing/hunting/digging game, MegaHAL-inspired Markov chain chatbot, OpenAI GPT integration, auto-translation (DeepL + AWS Comprehend), message proxying, admin tools, and Ukrainian war loss stats.

## Commands

```bash
# Install dependencies (composer.lock is gitignored, so this resolves fresh)
composer install

# Lint (PSR-12 via PHP CodeSniffer)
composer check-code

# Syntax check a single file
php -l Commands/Group/FishingCommand.php

# Run bot via webhook (production entry point)
# Telegram calls hook.php automatically

# Run bot via polling (development)
php getUpdatesCLI.php

# Run scheduled cleanup (cron)
php cron.php

# Execute a command in background (used internally)
php exe.php <secret> <command> [args...]
```

No automated test suite exists. Test manually via Telegram.

## Architecture

### Entry Points
- **`hook.php`** — Webhook handler (main production entry). Creates `Telegram` instance, loads config, calls `$telegram->handle()`.
- **`getUpdatesCLI.php`** — CLI polling alternative for development.
- **`exe.php`** — Background command executor. Called by `CustomSystemCommand::deleteMessages()` to run commands (like delayed message deletion) in a separate process. Requires secret as `$argv[1]`.
- **`cron.php`** — Runs scheduled commands (currently `/customcleanup`).
- **`manager.php`** — Alternative entry using `TelegramBotManager`.

### Command System
Commands live in `Commands/` subdirectories. Only three paths are active by default (configured in `config.php` `commands.paths`):
- `Commands/Group/` — Core features (fishing, chatter, GPT, translation, proxying)
- `Commands/Admin/` — Admin-only commands
- `Commands/User/` — User-facing commands (/start, /help)

Other subdirectories (Channel, Config, Conversation, InlineMode, Keyboard, Message, Other, Payments, ServiceMessages) contain example/template commands from `php-telegram-bot/example-bot` and are **not loaded**.

### Command Class Hierarchy
- `SystemCommand` — System events (messages, member joins)
- `AdminCommand` — Admin-only (checks admin list automatically)
- `UserCommand` — User-invocable commands
- **`CustomSystemCommand`** (`Commands/Group/CustomSystemCommand.php`) — Project-specific base extending `SystemCommand`. Adds:
  - `replyToChat()` override that saves bot-sent messages to the `message` DB table
  - `deleteMessages(?int $timeout)` that spawns `exe.php` in background for delayed deletion

### Message Flow
`Group/GenericmessageCommand.php` is the central router for all group messages. It processes in order:
1. Banned channel message deletion
2. Private message forwarding (join request tracking)
3. Message proxying to admin chats
4. Auto-translation (CLD2 → AWS Comprehend → LanguageDetector fallback → DeepL translate)
5. GPT reply chain detection (replies to "GPT: " messages)
6. Fishing game command delegation
7. Chatter AI processing

### Database
MySQL via PDO (`DB::getPdo()`). Custom tables beyond the telegram-bot schema:
- **Fishing**: `fishing_trophy`, `fishing_time`
- **Chatter**: `words` (vocabulary), `brain` (5-gram Markov chains), `responses` (trigger→response word mappings), `banned_words`

Schema files: `fishing_structure.sql`, `chatter_structure.sql`.

### Configuration
`config.php` returns an array with all settings. Command-specific config is under `commands.configs.<command_name>` and accessed via `$this->getConfig('key')`. Separate config files: `fishing_config.php`, `chatter_config.php`.

`config.php`, `fishing_config.php`, `chatter_config.php` contain production secrets — **never edit without explicit user approval**. Use `.example.php` variants as reference.

## Deployment
- **Server**: `ssh vps-web` → `~/web/bot.abysim.com/public_html`
- **Logs**: `~/web/bot.abysim.com/private/logs/php-telegram-bot-{debug,error}.log` (debug log disabled by default)
- **Deploy files**: `scp <file> vps-web:~/web/bot.abysim.com/public_html/<file>` — **never deploy without explicit user approval**
- **Local PHP 8.4 is available via `p` alias** (default `php` is 7.2) — use `p` for local lint/phpcs
- **Production runs PHP 8.4** (both FPM and CLI) — `error_reporting` excludes `E_DEPRECATED`
- **NEVER execute production code** (hook.php, cron.php, exe.php, getUpdatesCLI.php) via SSH — use only static analysis (`php -l`, grep, reading logs). Running entry points triggers real bot actions.
- **MySQL is on external shared hosting** (not localhost) — connection config in `config.php`
- DB migrations: `vendor/longman/telegram-bot/utils/db-schema-update/`
- **Query live DB**: `ssh vps-web` then `mysql` CLI (credentials in `config.php` on server)

## Code Style
- PSR-12 (enforced by phpcs)
- Namespace: `Longman\TelegramBot\Commands\{SystemCommands|AdminCommands|UserCommands}`
- File naming: `{CommandName}Command.php`
- Primary language in UI strings: Ukrainian

## Gotchas
- `composer.lock` is gitignored — fresh clones resolve dependencies via `composer install` from `composer.json` constraints
- Some commands in `Commands/Group/` use `UserCommands` namespace (e.g., `GptCommand`, `RsnpzdCommand`) rather than `SystemCommands` — this is intentional for Telegram Bot library routing
- The `GenericmessageCommand` class name exists in multiple directories (Group, Conversation, Message, Payments, ServiceMessages) — only `Group/` is loaded
- `exe.php` requires the config secret as first CLI argument — without it the process silently dies
- `set.php` returns "Webhook is already set" without updating `allowed_updates` — must delete webhook first (`$telegram->deleteWebhook()`) then re-run `set.php`
- `unset.php` only works via web request with `?secret=` param — use `$telegram->deleteWebhook()` from CLI instead
- Telegram sends `message_reaction` (per-user) for groups with visible reaction authors, and `message_reaction_count` (anonymous aggregate) for groups with hidden authors — code must handle both
- DB column names differ across tables (e.g., `message.id` vs `message_reaction.message_id` referencing the same value) — always verify column names from `structure.sql`, migrations, or live DB before writing SQL
- DB is only accessible from the production server (IP-restricted) — use `ssh vps-web` then `mysql` CLI to inspect live data
- `longman/telegram-bot` 0.83.x emits implicit nullable parameter deprecations on PHP 8.4 (`TelegramLog::initialize`, `DB::getTelegramRequestCount`) — suppressed by `error_reporting` in prod, no fix available upstream

## Key Patterns
- Commands return `ServerResponse` from `execute()`
- `Request::emptyResponse()` when no reply needed
- `$this->messageIds[]` collects IDs for batch deletion via `deleteMessages()`
- `Request::sendChatAction(['action' => ChatAction::TYPING])` for typing indicators during slow operations
- Config access: `$this->getConfig('key')` or `$this->getConfig()` for full array

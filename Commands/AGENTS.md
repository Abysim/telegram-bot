<!-- Parent: ../AGENTS.md -->
<!-- Generated: 2026-03-05 | Updated: 2026-03-05 -->

# Commands

## Purpose
All Telegram bot command handlers, organized by category. Each subdirectory contains commands scoped to a specific context (group chats, admin actions, inline mode, etc.). The root also contains two system-level commands that apply globally.

## Key Files

| File | Description |
|------|-------------|
| `GenericCommand.php` | Fallback handler for unrecognized commands; also routes `/whoisXYZ` for admins |
| `StartCommand.php` | System `/start` command - greets users in private chat |

## Subdirectories

| Directory | Purpose |
|-----------|---------|
| `Admin/` | Admin-only commands: send messages as bot, manage chatter data, DB cleanup (see `Admin/AGENTS.md`) |
| `Channel/` | Channel post and edited channel post handlers (see `Channel/AGENTS.md`) |
| `Config/` | Example config-dependent commands: `/date`, `/weather` (see `Config/AGENTS.md`) |
| `Conversation/` | Conversation flow commands: surveys, cancel, generic message (see `Conversation/AGENTS.md`) |
| `Group/` | **Main feature commands**: fishing game, chatter AI, GPT, translation, message proxying, war stats (see `Group/AGENTS.md`) |
| `InlineMode/` | Inline query and chosen inline result handlers (see `InlineMode/AGENTS.md`) |
| `Keyboard/` | Keyboard interaction commands: callbacks, force reply, inline keyboards (see `Keyboard/AGENTS.md`) |
| `Message/` | Message editing and generic message handling (see `Message/AGENTS.md`) |
| `Other/` | Utility commands: `/echo`, `/help`, `/image`, `/slap`, `/whoami` (see `Other/AGENTS.md`) |
| `Payments/` | Telegram payment flow commands (see `Payments/AGENTS.md`) |
| `ServiceMessages/` | Service message handler (see `ServiceMessages/AGENTS.md`) |
| `User/` | User-facing commands: `/start`, `/help` (see `User/AGENTS.md`) |

## For AI Agents

### Working In This Directory
- Commands are loaded by path via `config.php` `commands.paths` - only `Group/`, `Admin/`, and `User/` are active by default.
- Other directories (Config, Conversation, Keyboard, etc.) contain example/template commands from `php-telegram-bot/example-bot`.
- New custom commands should go in the appropriate category directory.
- The `Group/` directory contains the custom `CustomSystemCommand` base class which adds: bot message DB persistence via overridden `replyToChat()`, and delayed message deletion via `deleteMessages()`.

### Command Class Hierarchy
- `SystemCommand` - for system events (generic messages, new members, etc.)
- `AdminCommand` - for admin-only commands (checks admin list)
- `UserCommand` - for user-invocable commands
- `CustomSystemCommand extends SystemCommand` - project-specific base with message persistence and deletion

### Naming Convention
- File: `{CommandName}Command.php` (PascalCase)
- Class: `{CommandName}Command`
- The `$name` property must match the command trigger (lowercase, no slash)

### Common Patterns
- Every command implements `execute(): ServerResponse`
- Config access: `$this->getConfig('key')` pulls from `config.php` commands.configs section
- Message context: `$this->getMessage()` to get the triggering message
- Database: `DB::getPdo()` for direct PDO access
- Namespace depends on command type: `SystemCommands`, `AdminCommands`, or `UserCommands`

## Dependencies

### Internal
- `CustomSystemCommand` in `Group/CustomSystemCommand.php` - base class for most custom commands
- Root `config.php` - all command configurations

### External
- `longman/telegram-bot` - provides base command classes and `Request`, `DB` utilities

<!-- MANUAL: Any manually added notes below this line are preserved on regeneration -->

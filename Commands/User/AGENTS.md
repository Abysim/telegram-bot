<!-- Parent: ../AGENTS.md -->
<!-- Generated: 2026-03-05 | Updated: 2026-03-05 -->

# User

## Purpose
User-facing commands available to all users. These are actively loaded and provide basic bot interaction.

## Key Files

| File | Description |
|------|-------------|
| `HelpCommand.php` | Displays list of available commands to the user |
| `StartCommand.php` | Handles the `/start` command in private chat |

## For AI Agents

### Working In This Directory
- This directory IS actively loaded via `config.php` `commands.paths`.
- Commands here extend `UserCommand` and are visible in the bot's command list.
- New user-facing commands should be added here.

<!-- MANUAL: Any manually added notes below this line are preserved on regeneration -->

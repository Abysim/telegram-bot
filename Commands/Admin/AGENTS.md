<!-- Parent: ../AGENTS.md -->
<!-- Generated: 2026-03-05 | Updated: 2026-03-05 -->

# Admin

## Purpose
Admin-only commands accessible to users listed in the `admins` config array. Includes chat management, chatter AI data management, message operations, and database cleanup.

## Key Files

| File | Description |
|------|-------------|
| `ChatCommand.php` | Send messages as the bot to a specific chat (by ID) or configured default chat |
| `CustomcleanupCommand.php` | Database cleanup - removes old records from Telegram tables in configurable time windows, runs via cron |
| `DeletemessagesCommand.php` | Deletes specified messages from a chat after an optional delay (used by `CustomSystemCommand::deleteMessages()`) |
| `ECommand.php` | Exclude words from chatter - adds words to `banned_words` table and removes all their existing response relations |
| `FCommand.php` | Chatter word form management using phpMorphy morphological analysis |
| `ForwardmessageCommand.php` | Forward a message from one chat to another |
| `LearnCommand.php` | Manually teach the chatter a specific phrase |
| `TeachCommand.php` | Batch teach the chatter from CSV data |

## For AI Agents

### Working In This Directory
- All commands extend `AdminCommand` which automatically checks admin permissions.
- `CustomcleanupCommand` is the primary scheduled task, run via `cron.php`. It deletes in 10k-row chunks with transactions.
- `ECommand` handles word exclusion with case variations (lower, upper, capitalized, etc.) and cleans up response relations.
- `DeletemessagesCommand` is called indirectly via `exe.php` background execution from `CustomSystemCommand::deleteMessages()`.

### Testing Requirements
- Must be in the admin list to test these commands
- `CustomcleanupCommand` supports a `dry` flag to preview queries without executing

### Common Patterns
- `$this->getMessage()->getText(true)` gets command arguments (text after the command)
- Admin commands use `AdminCommand` base class (not `CustomSystemCommand`)

## Dependencies

### Internal
- `exe.php` - `DeletemessagesCommand` is invoked through background execution
- Chatter DB tables: `words`, `brain`, `responses`, `banned_words`

### External
- `cijic/phpmorphy` - morphological analysis in `FCommand`

<!-- MANUAL: Any manually added notes below this line are preserved on regeneration -->

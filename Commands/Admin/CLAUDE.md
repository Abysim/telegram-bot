# Commands/Admin

## Reaction tables
- `message_reaction` and `message_reaction_count` were added by migration (not in the base `structure.sql`)
- `message` table PK is `(chat_id, id)` — the column is `id`, NOT `message_id`
- Telegram reaction emoji use bare Unicode without variation selectors (e.g., heart on fire = U+2764 U+200D U+1F525, no U+FE0F)

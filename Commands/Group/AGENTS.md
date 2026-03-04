<!-- Parent: ../AGENTS.md -->
<!-- Generated: 2026-03-05 | Updated: 2026-03-05 -->

# Group

## Purpose
The core feature directory. Contains all group-chat commands including the fishing/hunting/digging game, MegaHAL-inspired Markov chain chatbot ("Chatter"), OpenAI GPT integration, automatic language translation, message proxying, war loss statistics, and the custom base command class.

## Key Files

| File | Description |
|------|-------------|
| `CustomSystemCommand.php` | **Base class** for most custom commands. Extends `SystemCommand` with: bot message DB persistence (overridden `replyToChat()`), and delayed message deletion via background `exe.php` (`deleteMessages()`) |
| `GenericmessageCommand.php` | **Main message router** - handles all incoming group messages: banned channel filtering, private message forwarding to admins, message proxying between chats, auto-translation (CLD2 + AWS Comprehend + DeepL), GPT reply chaining, then delegates to fishing and chatter commands |
| `FishingCommand.php` | Fishing/hunting/digging game - users can `/cast`, `/dig`, `/hunt` with cooldowns, random outcomes, trophy records stored in DB |
| `ChatterCommand.php` | Markov chain chatbot - learns from messages (5-gram brain table), generates responses using keyword/auxiliary word matching, supports trigger words and antonym substitution |
| `GptCommand.php` | OpenAI GPT integration - sends user messages to configurable OpenAI-compatible API with optional DeepL translation, supports conversation threading via reply chains |
| `RsnpzdCommand.php` | Russian war losses statistics - fetches data from russianwarship.rip API and displays formatted Ukrainian casualty report |
| `CastCommand.php` | Alias trigger for fishing game (`/cast`) |
| `DigCommand.php` | Alias trigger for digging game (`/dig`, `/копати`) |
| `HuntCommand.php` | Alias trigger for hunting game (`/hunt`, `/полювання`) |
| `TrophiesCommand.php` | Displays fishing/hunting/digging trophy records |
| `ChatterCommand.php` | MegaHAL-inspired chatbot with learning and response generation |
| `TazerCommand.php` | Tazer command |
| `NewchatmembersCommand.php` | New chat member event handler |
| `LeftchatmemberCommand.php` | Left chat member event handler |
| `ChatJoinRequestCommand.php` | Chat join request handler - approves/manages join requests with welcome messages |

## For AI Agents

### Working In This Directory
- `CustomSystemCommand` is the base class for most commands here - always extend it instead of `SystemCommand` directly when you need message persistence or auto-deletion.
- `GenericmessageCommand` is the central router - it calls `fishing` and `chatter` commands at the end of every group message. Changes here affect all group message processing.
- The fishing game uses DB tables `fishing_trophy` and `fishing_time`. Config is in `fishing_config.php`.
- The chatter uses DB tables `words`, `brain` (5-gram Markov chains), `responses` (trigger-response mappings), and `banned_words`. Config is in `chatter_config.php`.
- Messages are in Ukrainian (`uk`) as the primary language. Russian (`ru`) and English are also used.
- `GptCommand` supports configurable API endpoints per chat (can use different OpenAI-compatible services).

### Testing Requirements
- Test fishing commands in allowed chats only (configured in `fishing_config.php`)
- Chatter learning only occurs in configured `learn_chats`
- Translation requires valid DeepL and AWS credentials
- GPT requires valid OpenAI API key

### Common Patterns
- `$this->messageIds[]` tracks message IDs for later deletion via `deleteMessages()`
- `$this->getConfig()` pulls from the command-specific config section
- `Request::sendChatAction()` sends typing indicators during long operations
- Database queries use PDO with named parameter binding
- `Request::emptyResponse()` when no reply is needed

## Dependencies

### Internal
- `CustomSystemCommand` (this directory) - base class
- `config.php` configs: `fishing`, `chatter`, `genericmessage` (proxy, joinrequest, translate, banned_channels)

### External
- `openai-php/client` - GPT API calls
- `deeplcom/deepl-php` - translation
- `aws/aws-sdk-php` - AWS Comprehend language detection
- `landrok/language-detector` - fallback language detection
- `russianwarship.rip` API - war statistics

<!-- MANUAL: Any manually added notes below this line are preserved on regeneration -->

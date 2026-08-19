# Changelog

All notable changes to the Uchara PHP SDK will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.1] - 2026-08-20

### Changed

- Hardened HTTP and Server SDK behavior and expanded agent/server integration support.
- Added the current visitor and agent SDK API surface used by the platform.

## [1.1.0] - 2025-08-06

### Added
- **AgentSDK** for email/password JWT authentication, token refresh, agent-scoped conversation operations and messages attributed to the authenticated human agent.
- Workspace **members** management: `listMembers`, `getMember`, `createMember`,
  `updateMember`, `updateMemberRole`, `deactivateMember`, `reactivateMember`, `deleteMember`
  against `/v1/workspace/members`.
- **Agent aliases** for human members: `listAgents`, `getAgent`, `createAgent`,
  `updateAgent`, `deactivateAgent`, `reactivateAgent`, `deleteAgent`, `inviteAgent`.
- **Invites** management: `inviteMember`, `listInvites`, `revokeInvite` against
  `/v1/workspace/invites`.
- **Channel CRUD**: `createChannel`, `updateChannel`, `deleteChannel`,
  `setupChannelWebhook`, `testChannelConnection`.
- **Bot CRUD**: `listBots`, `createBot`, `updateBot`, `deleteBot`.
- **Message aliases**: `sendMessageToConversation`, `listMessages`, `getConversationMessages`.
- **VisitorSDK** against the actual `/v1/widget/*` endpoints: `init`, `getConfig`,
  `getActiveConversation`, `startConversation`, `getMessages`, `sendMessage`, `upload`,
  `download`, `close`.
- Native `Uchara` factory (`server`, `visitor`, `make`).
- Laravel integration: `UcharaManager`, `UcharaServiceProvider`, `Uchara` facade and
  `config/uchara.php` with auto-discovery for Laravel 10/11.
- Hardened `HTTPClient`: query string support, per-request headers, idempotency headers,
  injectable Guzzle client, structured `UcharaResponse` (status/meta/headers/raw body) and
  structured `UcharaException` (status/details/response).
- Test suite using Guzzle `MockHandler`, plus composer scripts (`test`, `analyse`, `validate`),
  PHPUnit and PHPStan configuration.

### Changed
- Package renamed to `uchara/uchara-php`.
- PHP requirement raised to `^8.1`.
- `UcharaException` now exposes `getStatus()` and `getResponse()`.

## [1.0.0] - 2025-07-30

### Added
- Initial release
- ServerSDK for server-to-server API integration (contacts, conversations, messages, channels, members, API keys)
- VisitorSDK for widget/visitor endpoints (session init, config, conversation management, messaging)
- HTTPClient with automatic envelope unwrapping and error handling
- UcharaException for structured error responses
- Laravel integration: ServiceProvider, Facade, and config file
- Auto-discovery support for Laravel 10+

[1.1.0]: https://github.com/uchara/uchara-php/releases/tag/v1.1.0
[1.0.0]: https://github.com/uchara/uchara-php/releases/tag/v1.0.0

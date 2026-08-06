# Uchara PHP SDK

Official PHP SDK for the Uchara Chat Platform. It provides:

- **ServerSDK** — server-to-server integration with the authenticated `/v1/*` REST API (members/agents, invites, channels, bots, conversations, messages, contacts, canned responses, API keys).
- **VisitorSDK** — embed the chat widget in customer applications via the public `/v1/widget/*` endpoints.
- **Laravel integration** — a service provider, manager, facade and config file with auto-discovery for Laravel 10/11. The native SDK itself has **no Laravel dependency**.

## Requirements

- PHP 8.1 or higher
- Composer
- `ext-json`

## Installation

```bash
composer require uchara/uchara-php
```

For Laravel 10/11 the service provider and facade are registered automatically via
[package auto-discovery](https://laravel.com/docs/packages#package-discovery). Publish the
config file with:

```bash
php artisan vendor:publish --tag=uchara-config
```

## Quick Start — Server SDK

```php
<?php

require 'vendor/autoload.php';

use Uchara\SDK\ServerSDK;

$client = new ServerSDK(
    apiUrl: 'https://api.uchara.com',
    apiKey: getenv('UCHARA_API_KEY')
);

// Send a message
$message = $client->sendMessage('conv_abc123', [
    'content' => 'Your order has shipped!',
    'sender_type' => 'bot',
]);

echo "Message sent: {$message['id']}\n";

// List conversations
$conversations = $client->listConversations([
    'status' => 'open',
    'limit' => 10,
]);

foreach ($conversations as $conv) {
    echo "Conversation: {$conv['id']} - {$conv['contact_name']}\n";
}
```

### Members (a.k.a. agents)

Workspace members are the human users of a workspace. Because many users refer to them as
"agents", ergonomic `Agent` aliases are provided alongside the canonical `Member` methods.

```php
// Canonical member methods
$members = $client->listMembers(['role' => 'agent']);
$member  = $client->getMember('member_123');
$created = $client->createMember(['email' => 'a@b.com', 'role' => 'agent'], 'idem-key-1');
$client->updateMember('member_123', ['name' => 'Alice']);
$client->updateMemberRole('member_123', 'admin');
$client->deactivateMember('member_123');
$client->reactivateMember('member_123');
$client->deleteMember('member_123');

// Agent aliases — identical behaviour
$agents = $client->listAgents();
$agent  = $client->getAgent('member_123');
$client->createAgent(['email' => 'a@b.com']);
$client->updateAgent('member_123', ['name' => 'Alice']);
$client->deactivateAgent('member_123');
$client->reactivateAgent('member_123');
$client->deleteAgent('member_123');
```

### Invites

```php
$invite = $client->inviteMember(['email' => 'x@y.com', 'role' => 'agent']);
$invites = $client->listInvites();
$client->revokeInvite('invite_123');
```

### Channels, Bots & Messages

```php
$channels = $client->listChannels();
$channel  = $client->getChannel('channel_123'); // filters the list (no GET-by-id route)
$client->createChannel(['name' => 'WhatsApp', 'type' => 'whatsapp']);
$client->updateChannel('channel_123', ['name' => 'WA']);
$client->deleteChannel('channel_123');

$bots = $client->listBots();
$client->createBot(['name' => 'Support Bot']);
$client->updateBot('bot_123', ['name' => 'Support Bot v2']);
$client->deleteBot('bot_123');

// Message aliases
$client->sendMessageToConversation('conv_1', ['content' => 'hi']);
$messages = $client->listMessages('conv_1');
$messages = $client->getConversationMessages('conv_1');
```

## Quick Start — Visitor SDK

```php
<?php

require 'vendor/autoload.php';

use Uchara\SDK\VisitorSDK;

$visitor = new VisitorSDK(
    apiUrl: 'https://api.uchara.com',
    widgetToken: 'widget_token_123'
);

// Create a visitor session (stores the visitor JWT for subsequent calls)
$session = $visitor->init(['name' => 'Alice', 'email' => 'a@b.com']);

$config = $visitor->getConfig();

$active = $visitor->getActiveConversation(); // null when none exists
if ($active === null) {
    $active = $visitor->startConversation(['message' => 'Hello']);
}

$visitor->sendMessage($active['id'], ['content' => 'Hi there']);
$messages = $visitor->getMessages($active['id'], ['limit' => 20]);
$visitor->close($active['id']);
```

## Factory

The `Uchara` factory builds SDK instances from a config array or directly:

```php
use Uchara\SDK\Uchara;

$server = Uchara::server('https://api.uchara.com', 'uchara_sk_...');
$visitor = Uchara::visitor('https://api.uchara.com', 'widget_token_...');

// From a config array
$sdk = Uchara::make([
    'api_url' => 'https://api.uchara.com',
    'api_key' => 'uchara_sk_...',
    'default' => 'server', // or 'visitor'
]);
```

## Laravel

Set the environment variables and use the facade:

```php
// .env
UCHARA_API_URL=https://api.uchara.com
UCHARA_API_KEY=uchara_sk_...
UCHARA_DEFAULT=server
```

```php
use Uchara\SDK\Laravel\Facades\Uchara;

$members = Uchara::listMembers();          // forwards to the default SDK
$server  = Uchara::server();               // explicit ServerSDK
$visitor = Uchara::visitor();              // explicit VisitorSDK
```

## Error Handling

```php
use Uchara\SDK\UcharaException;

try {
    $message = $client->sendMessage('conv_id', ['content' => 'Hello!']);
} catch (UcharaException $e) {
    echo "Error ({$e->getStatus()}): {$e->getMessage()}\n";
    if ($e->getDetails()) {
        print_r($e->getDetails());
    }
}
```

`UcharaException` exposes the HTTP status via `getStatus()` (alias of `getCode()`), the parsed
error payload via `getDetails()`, and the full structured response via `getResponse()`.

## Advanced HTTP access

The simple helpers (`get`/`post`/`patch`/`put`/`delete`) return the unwrapped `data` payload.
When you need the status code, pagination `meta` or response headers, use `request()`:

```php
$response = $client->http()->request('GET', '/v1/workspace/members', ['query' => ['limit' => 10]]);
$status = $response->status();
$meta   = $response->meta();
$data   = $response->data();
```

## Development

```bash
composer install
composer test      # PHPUnit
composer analyse   # PHPStan
composer validate  # composer validate --strict
```

## Documentation

Full documentation: https://www.uchara.com/docs/sdk/php

## License

MIT

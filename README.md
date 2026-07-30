# Uchara PHP SDK

Official PHP SDK for the Uchara Chat Platform.

## Requirements

- PHP 8.0 or higher
- Composer

## Installation

```bash
composer require uchara/sdk
```

## Quick Start

### Server SDK

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

### Error Handling

```php
use Uchara\SDK\UcharaException;

try {
    $message = $client->sendMessage('conv_id', [
        'content' => 'Hello!',
    ]);
} catch (UcharaException $e) {
    echo "Error ({$e->getCode()}): {$e->getMessage()}\n";
    if ($e->getDetails()) {
        print_r($e->getDetails());
    }
}
```

## Documentation

Full documentation: https://www.uchara.com/docs/sdk/php

## License

MIT

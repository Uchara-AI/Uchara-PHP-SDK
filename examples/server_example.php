<?php

require 'vendor/autoload.php';

use Uchara\SDK\ServerSDK;
use Uchara\SDK\UcharaException;

echo "🚀 Uchara PHP Server SDK Example\n\n";

// Initialize SDK
$client = new ServerSDK(
    apiUrl: 'https://api.uchara.com',
    apiKey: getenv('UCHARA_API_KEY') // Set via environment variable
);

try {
    // Example 1: List channels
    echo "📋 Listing channels...\n";
    $channels = $client->listChannels();
    echo "✅ Found " . count($channels) . " channels\n";
    foreach ($channels as $channel) {
        echo "  - {$channel['name']} ({$channel['id']})\n";
    }

    // Example 2: List conversations
    echo "\n📋 Listing open conversations...\n";
    $conversations = $client->listConversations([
        'status' => 'open',
        'limit' => 10,
    ]);
    echo "✅ Found " . count($conversations) . " open conversations\n";

    // Example 3: Send a message (if there's an open conversation)
    if (count($conversations) > 0) {
        $conv = $conversations[0];
        echo "\n💬 Sending message to conversation {$conv['id']}...\n";

        $message = $client->sendMessage($conv['id'], [
            'content' => 'Your order #12345 has been shipped! 🚚',
            'sender_type' => 'bot',
        ]);
        echo "✅ Message sent: {$message['id']}\n";
    }

    // Example 4: List workspace members
    echo "\n👥 Listing workspace members...\n";
    $members = $client->listMembers();
    echo "✅ Found " . count($members) . " members\n";
    foreach ($members as $member) {
        echo "  - {$member['name']} ({$member['email']}) - {$member['role']}\n";
    }

    echo "\n✅ Example completed successfully!\n";

} catch (UcharaException $e) {
    echo "❌ Error ({$e->getCode()}): {$e->getMessage()}\n";
    if ($e->getDetails()) {
        echo "Details: " . json_encode($e->getDetails()) . "\n";
    }
    exit(1);
}

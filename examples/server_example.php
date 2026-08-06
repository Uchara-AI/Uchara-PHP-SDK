<?php

/**
 * Uchara PHP Server SDK example.
 *
 * Run with:
 *   UCHARA_API_KEY=uchara_sk_... php examples/server_example.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Uchara\SDK\ServerSDK;
use Uchara\SDK\UcharaException;

echo "🚀 Uchara PHP Server SDK Example\n\n";

// Initialize SDK
$client = new ServerSDK(
    apiUrl: 'https://api.uchara.com',
    apiKey: getenv('UCHARA_API_KEY') ?: null // Set via environment variable
);

try {
    // ── Channels ──────────────────────────────────────────────────────────────
    echo "📋 Listing channels...\n";
    $channels = $client->listChannels();
    echo "✅ Found " . count($channels) . " channels\n";
    foreach ($channels as $channel) {
        echo "  - {$channel['name']} ({$channel['id']})\n";
    }

    // ── Conversations ─────────────────────────────────────────────────────────
    echo "\n📋 Listing open conversations...\n";
    $conversations = $client->listConversations([
        'status' => 'open',
        'limit' => 10,
    ]);
    echo "✅ Found " . count($conversations) . " open conversations\n";

    // ── Messages ──────────────────────────────────────────────────────────────
    if (count($conversations) > 0) {
        $conv = $conversations[0];
        echo "\n💬 Sending message to conversation {$conv['id']}...\n";

        $message = $client->sendMessage($conv['id'], [
            'content' => 'Your order #12345 has been shipped! 🚚',
            'sender_type' => 'bot',
        ]);
        echo "✅ Message sent: {$message['id']}\n";
    }

    // ── Members (a.k.a. agents) ───────────────────────────────────────────────
    echo "\n👥 Listing workspace members...\n";
    $members = $client->listMembers();
    echo "✅ Found " . count($members) . " members\n";
    foreach ($members as $member) {
        echo "  - {$member['name']} ({$member['email']}) - {$member['role']}\n";
    }

    // Agent aliases work identically:
    $agents = $client->listAgents();
    echo "✅ listAgents() returned " . count($agents) . " agents\n";

    // ── Invites ───────────────────────────────────────────────────────────────
    echo "\n📨 Listing pending invites...\n";
    $invites = $client->listInvites();
    echo "✅ Found " . count($invites) . " invites\n";

    echo "\n✅ Example completed successfully!\n";
} catch (UcharaException $e) {
    echo "\n❌ Error ({$e->getStatus()}): {$e->getMessage()}\n";
    if ($e->getDetails()) {
        print_r($e->getDetails());
    }
    exit(1);
}

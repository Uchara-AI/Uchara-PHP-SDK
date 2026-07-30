<?php
/**
 * PHP Server SDK Integration Test
 * Tests the Server SDK against a live API server
 */

require __DIR__ . '/vendor/autoload.php';

use Uchara\SDK\ServerSDK;
use Uchara\SDK\UcharaException;

$apiURL = 'http://localhost:8080';
$apiKey = 'uchara_sk_139861d97edd8007b24c78163c649dc7';

echo "🚀 PHP Server SDK Integration Test\n";
echo str_repeat("=", 50) . "\n";

$passed = 0;
$failed = 0;

// Initialize SDK
$client = new ServerSDK(
    apiUrl: $apiURL,
    apiKey: $apiKey
);

// ── Test 1: List Channels ───────────────────────────────────────────────────
echo "\n📋 Test 1: List Channels (GET /v1/channels)\n";
try {
    $channels = $client->listChannels();
    echo "  ✅ Channels retrieved\n";
    echo "  📊 Total channels: " . count($channels) . "\n";
    foreach ($channels as $ch) {
        echo "    - {$ch['name']} ({$ch['id']})\n";
    }
    $passed++;
} catch (UcharaException $e) {
    echo "  ❌ Failed ({$e->getCode()}): {$e->getMessage()}\n";
    $failed++;
}

// ── Test 2: List Conversations ──────────────────────────────────────────────
echo "\n📋 Test 2: List Conversations (GET /v1/conversations)\n";
try {
    $conversations = $client->listConversations(['status' => 'open', 'limit' => 10]);
    echo "  ✅ Conversations retrieved\n";
    echo "  📊 Total open conversations: " . count($conversations) . "\n";
    foreach (array_slice($conversations, 0, 3) as $conv) {
        $contactName = $conv['contact_name'] ?? 'Unknown';
        echo "    - [{$conv['id']}] {$contactName} (status: {$conv['status']})\n";
    }
    $passed++;
} catch (UcharaException $e) {
    echo "  ❌ Failed ({$e->getCode()}): {$e->getMessage()}\n";
    $failed++;
}

// ── Test 3: Send Message to Conversation ────────────────────────────────────
echo "\n📋 Test 3: Send Message (POST /v1/conversations/{id}/messages)\n";
try {
    // Get first open conversation
    $conversations = $client->listConversations(['status' => 'open', 'limit' => 1]);
    if (count($conversations) > 0) {
        $convId = $conversations[0]['id'];
        echo "  📤 Sending to conversation: $convId\n";
        
        $message = $client->sendMessage($convId, [
            'content' => 'Halo! Terima kasih telah menghubungi kami. Ada yang bisa dibantu? 🙋',
            'sender_type' => 'bot',
        ]);
        
        echo "  ✅ Message sent\n";
        echo "  📨 Message ID: {$message['id']}\n";
        echo "  📝 Content: {$message['content']}\n";
        $passed++;
    } else {
        echo "  ⚠️  No open conversations to test with\n";
        $passed++; // Not a failure
    }
} catch (UcharaException $e) {
    echo "  ❌ Failed ({$e->getCode()}): {$e->getMessage()}\n";
    $failed++;
}

// ── Test 4: Get Conversation Details ────────────────────────────────────────
echo "\n📋 Test 4: Get Conversation (GET /v1/conversations/{id})\n";
try {
    $conversations = $client->listConversations(['limit' => 1]);
    if (count($conversations) > 0) {
        $convId = $conversations[0]['id'];
        $conv = $client->getConversation($convId);
        
        echo "  ✅ Conversation retrieved\n";
        echo "  💬 ID: {$conv['id']}\n";
        echo "  📊 Status: {$conv['status']}\n";
        echo "  📅 Created: {$conv['created_at']}\n";
        $passed++;
    } else {
        echo "  ⚠️  No conversations available\n";
        $passed++;
    }
} catch (UcharaException $e) {
    echo "  ❌ Failed ({$e->getCode()}): {$e->getMessage()}\n";
    $failed++;
}

// ── Test 5: Upsert Contact ──────────────────────────────────────────────────
echo "\n📋 Test 5: Upsert Contact (POST /v1/contacts)\n";
try {
    $contact = $client->upsertContact([
        'name' => 'Budi Santoso',
        'email' => 'budi.santoso@example.com',
        'phone' => '+6281234567890',
        'metadata' => [
            'company' => 'PT Maju Jaya',
            'plan' => 'premium',
        ],
    ]);
    
    echo "  ✅ Contact upserted\n";
    echo "  📇 Contact ID: {$contact['id']}\n";
    echo "  👤 Name: {$contact['name']}\n";
    echo "  📧 Email: {$contact['email']}\n";
    $passed++;
} catch (UcharaException $e) {
    echo "  ❌ Failed ({$e->getCode()}): {$e->getMessage()}\n";
    $failed++;
}

// ── Test 6: List Contacts ───────────────────────────────────────────────────
echo "\n📋 Test 6: List Contacts (GET /v1/contacts)\n";
try {
    $contacts = $client->listContacts(['limit' => 5]);
    echo "  ✅ Contacts retrieved\n";
    echo "  📊 Total contacts: " . count($contacts) . "\n";
    foreach (array_slice($contacts, 0, 3) as $c) {
        $name = $c['name'] ?? 'Unknown';
        $email = $c['email'] ?? 'N/A';
        echo "    - {$name} ({$email})\n";
    }
    $passed++;
} catch (UcharaException $e) {
    echo "  ❌ Failed ({$e->getCode()}): {$e->getMessage()}\n";
    $failed++;
}

// ── Test 7: List Members ────────────────────────────────────────────────────
echo "\n📋 Test 7: List Members (GET /v1/members)\n";
try {
    $members = $client->listMembers();
    echo "  ✅ Members retrieved\n";
    echo "  📊 Total members: " . count($members) . "\n";
    foreach ($members as $m) {
        echo "    - {$m['name']} ({$m['email']}) - {$m['role']}\n";
    }
    $passed++;
} catch (UcharaException $e) {
    echo "  ❌ Failed ({$e->getCode()}): {$e->getMessage()}\n";
    $failed++;
}

// ── Test 8: Error Handling ──────────────────────────────────────────────────
echo "\n📋 Test 8: Error Handling (invalid API key)\n";
try {
    $badClient = new ServerSDK(
        apiUrl: $apiURL,
        apiKey: 'uchara_sk_invalid_key_12345678901234567890'
    );
    $badClient->listChannels();
    echo "  ❌ Should have thrown an exception\n";
    $failed++;
} catch (UcharaException $e) {
    echo "  ✅ Exception caught correctly\n";
    echo "  📊 Code: {$e->getCode()}\n";
    echo "  📝 Message: {$e->getMessage()}\n";
    $passed++;
}

// ── Summary ─────────────────────────────────────────────────────────────────
echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 Results: $passed passed, $failed failed\n";
if ($failed === 0) {
    echo "✅ PHP Server SDK: ALL TESTS PASSED\n";
    exit(0);
} else {
    echo "❌ PHP Server SDK: SOME TESTS FAILED\n";
    exit(1);
}

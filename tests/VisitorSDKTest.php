<?php

namespace Uchara\SDK\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Uchara\SDK\UcharaException;
use Uchara\SDK\VisitorSDK;

class VisitorSDKTest extends TestCase
{
    use MocksHttp;

    private function visitor(array $responses, array &$history = []): VisitorSDK
    {
        return new VisitorSDK('https://api.example.com', 'widget-token', 30, $this->mockClient($responses, $history));
    }

    public function testInitCreatesSessionAndSetsAuth(): void
    {
        $history = [];
        $visitor = $this->visitor([
            $this->jsonResponse(200, ['ok' => true, 'data' => [
                'visitor_token' => 'jwt-123',
                'contact_id' => 'c1',
                'channel_name' => 'Web',
            ]]),
        ], $history);

        $result = $visitor->init(['name' => 'Alice', 'email' => 'a@b.com']);

        $this->assertSame('jwt-123', $result['visitor_token']);
        $this->assertSame('jwt-123', $visitor->getVisitorToken());
        $this->assertSame('c1', $visitor->getContactId());

        $request = $history[0]['request'];
        $this->assertSame('/v1/widget/session', $request->getUri()->getPath());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('widget-token', $body['widget_token']);
        $this->assertSame('Alice', $body['name']);
    }

    public function testGetConfigSendsTokenQuery(): void
    {
        $history = [];
        $visitor = $this->visitor([
            $this->jsonResponse(200, ['ok' => true, 'data' => ['channel_name' => 'Web']]),
        ], $history);

        $visitor->getConfig();
        $this->assertSame('/v1/widget/config', $history[0]['request']->getUri()->getPath());
        $this->assertSame('token=widget-token', $history[0]['request']->getUri()->getQuery());
    }

    public function testGetActiveConversationReturnsNullOn404(): void
    {
        $visitor = $this->visitor([
            $this->jsonResponse(404, ['error' => ['message' => 'no active conversation']]),
        ]);

        $this->assertNull($visitor->getActiveConversation());
    }

    public function testGetActiveConversationReturnsConversation(): void
    {
        $visitor = $this->visitor([
            $this->jsonResponse(200, ['ok' => true, 'data' => ['id' => 'conv1', 'status' => 'open']]),
        ]);

        $conv = $visitor->getActiveConversation();
        $this->assertSame('conv1', $conv['id']);
    }

    public function testStartConversation(): void
    {
        $history = [];
        $visitor = $this->visitor([
            $this->jsonResponse(201, ['ok' => true, 'data' => ['id' => 'conv1']]),
        ], $history);

        $visitor->startConversation(['message' => 'Hello']);
        $this->assertSame('/v1/widget/conversations', $history[0]['request']->getUri()->getPath());
    }

    public function testGetMessagesSendsQuery(): void
    {
        $history = [];
        $visitor = $this->visitor([
            $this->jsonResponse(200, ['ok' => true, 'data' => []]),
        ], $history);

        $visitor->getMessages('conv1', ['limit' => 20]);
        $this->assertSame('/v1/widget/conversations/conv1/messages', $history[0]['request']->getUri()->getPath());
        $this->assertSame('limit=20', $history[0]['request']->getUri()->getQuery());
    }

    public function testSendMessage(): void
    {
        $history = [];
        $visitor = $this->visitor([
            $this->jsonResponse(201, ['ok' => true, 'data' => ['id' => 'msg1']]),
        ], $history);

        $visitor->sendMessage('conv1', ['content' => 'Hi']);
        $this->assertSame('/v1/widget/conversations/conv1/messages', $history[0]['request']->getUri()->getPath());
    }

    public function testCloseConversation(): void
    {
        $history = [];
        $visitor = $this->visitor([
            $this->jsonResponse(200, ['ok' => true, 'data' => ['message' => 'conversation closed']]),
        ], $history);

        $visitor->close('conv1');
        $this->assertSame('/v1/widget/conversations/conv1/close', $history[0]['request']->getUri()->getPath());
    }

    public function testDownloadReturnsRawText(): void
    {
        $visitor = $this->visitor([
            new Response(200, ['Content-Type' => 'text/plain'], "Percakapan #abc\n"),
        ]);

        $this->assertSame("Percakapan #abc\n", $visitor->download('conv1'));
    }

    public function testUploadSendsMultipart(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'uchara');
        file_put_contents($file, 'hello');

        $history = [];
        $visitor = $this->visitor([
            $this->jsonResponse(201, ['ok' => true, 'data' => ['id' => 'msg1']]),
        ], $history);

        $visitor->upload('conv1', $file, 'note.txt', 'text/plain');

        $request = $history[0]['request'];
        $this->assertSame('/v1/widget/conversations/conv1/uploads', $request->getUri()->getPath());
        $this->assertStringContainsString('multipart/form-data', $request->getHeaderLine('Content-Type'));

        unlink($file);
    }

    public function testUploadMissingFileThrows(): void
    {
        $visitor = $this->visitor([]);

        $this->expectException(UcharaException::class);
        $visitor->upload('conv1', '/nonexistent/file.txt');
    }
}

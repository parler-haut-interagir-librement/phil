<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Functional\Chat;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Answers as Slack's `chat.postMessage` and a Discord webhook answer, and keeps each request.
 */
final class ChatServers
{
    /** @var list<array{method: string, url: string, body: mixed}> */
    public array $requests = [];

    /**
     * @param array<array-key, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options): MockResponse
    {
        $body = $options['body'] ?? '';
        $this->requests[] = ['method' => $method, 'url' => $url, 'body' => json_decode(\is_string($body) ? $body : '', true)];

        return str_contains($url, 'discord.com')
            ? new MockResponse('', ['http_code' => 204])
            : new MockResponse('{"ok":true,"channel":"C0RELEASES","ts":"1.2"}', ['http_code' => 200]);
    }
}

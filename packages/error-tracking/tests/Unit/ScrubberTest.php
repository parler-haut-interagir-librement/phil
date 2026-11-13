<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Tests\Unit;

use Phil\ErrorTracking\RequestSummary;
use Phil\ErrorTracking\Scrubber;
use Phil\ErrorTracking\Tests\Unit\Fixture\Events;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Scrubber::class)]
final class ScrubberTest extends TestCase
{
    #[TestWith(['Authorization'])]
    #[TestWith(['proxy-authorization'])]
    #[TestWith(['Cookie'])]
    #[TestWith(['set-cookie'])]
    #[TestWith(['X-Api-Key'])]
    #[TestWith(['x-csrf-token'])]
    #[TestWith(['user_password'])]
    #[TestWith(['PASSWD'])]
    #[TestWith(['client_secret'])]
    #[TestWith(['apikey'])]
    #[TestWith(['session_id'])]
    #[TestWith(['credentials'])]
    #[TestWith(['card_number'])]
    #[TestWith(['private_key'])]
    #[TestWith(['xsrf'])]
    #[TestWith(['signature'])]
    #[TestWith(['cvv'])]
    #[TestWith(['iban'])]
    #[TestWith(['ssn'])]
    #[TestWith(['api-key'])]
    #[TestWith(['private-key'])]
    public function testASensitiveName(string $name): void
    {
        self::assertTrue(new Scrubber()->isSensitive($name));
    }

    #[TestWith(['accept'])]
    #[TestWith(['user-agent'])]
    #[TestWith(['transport'])]
    #[TestWith(['a.b'])]
    public function testAnOrdinaryName(string $name): void
    {
        self::assertFalse(new Scrubber()->isSensitive($name));
    }

    public function testFieldsOfTheApplication(): void
    {
        $scrubber = new Scrubber(['birth.date']);

        self::assertTrue($scrubber->isSensitive('user_birth.date'));
        self::assertFalse($scrubber->isSensitive('birthxdate'));
        self::assertTrue($scrubber->isSensitive('token'));
    }

    #[TestWith(['Mail to jane.doe@example.com failed', 'Mail to [email] failed'])]
    #[TestWith(['Header Bearer abc.def-ghi refused', 'Header Bearer [Filtered] refused'])]
    #[TestWith(['basic  dXNlcjpwYXNz', 'basic  [Filtered]'])]
    #[TestWith(['Connect to mysql://app:s3cret@db/app failed', 'Connect to mysql://[Filtered]@db/app failed'])]
    #[TestWith(['Login with password=hunter2&next=1', 'Login with password=[Filtered]&next=1'])]
    #[TestWith(['{"api_key": "abc", "id": 1}', '{"api_key": "[Filtered]", "id": 1}'])]
    #[TestWith(['user.token: abc def', 'user.token: [Filtered] def'])]
    #[TestWith(["secret='x'", "secret='[Filtered]'"])]
    #[TestWith(['Nothing to hide', 'Nothing to hide'])]
    public function testATextIsScrubbed(string $text, string $scrubbed): void
    {
        self::assertSame($scrubbed, new Scrubber()->text($text));
    }

    #[TestWith(['https://example.com/reset?token=abc&email=a@b.co&page=2#top', 'https://example.com/reset?token=[Filtered]&email=[Filtered]&page=[Filtered]'])]
    #[TestWith(['/search?q=jane&&flag', '/search?q=[Filtered]&flag=[Filtered]'])]
    #[TestWith(['/users/jane@example.com/edit#x', '/users/[email]/edit'])]
    #[TestWith(['/plain', '/plain'])]
    #[TestWith(['/empty?', '/empty?'])]
    #[TestWith(['/a?=x&b', '/a?b=[Filtered]'])]
    public function testAnAddressIsScrubbed(string $url, string $scrubbed): void
    {
        self::assertSame($scrubbed, new Scrubber()->url($url));
    }

    public function testAnEventIsScrubbedBeforeItIsWritten(): void
    {
        $request = new RequestSummary('POST', 'https://example.com/login?next=/me', 'login', [
            'Authorization' => 'Bearer abc',
            'Cookie' => 'PHPSESSID=1',
            'Referer' => 'https://example.com/?from=jane@example.com',
            'Accept' => 'text/html',
        ], '192.0.2.0');
        $event = Events::one('No user jane@example.com', request: $request, context: ['password' => 'hunter2', 'note' => 'from jane@example.com', 'retries' => 2]);
        $scrubbed = new Scrubber()->scrub($event);

        self::assertSame('RuntimeException: No user [email]', $scrubbed->title());
        self::assertSame($event->exceptions[0]->frames, $scrubbed->exceptions[0]->frames);
        self::assertSame('RuntimeException', $scrubbed->exceptions[0]->type);
        self::assertSame(['password' => '[Filtered]', 'note' => 'from [email]', 'retries' => 2], $scrubbed->context);
        self::assertSame('https://example.com/login?next=[Filtered]', $scrubbed->request?->url);
        self::assertSame([
            'authorization' => '[Filtered]',
            'cookie' => '[Filtered]',
            'referer' => 'https://example.com/?from=[email]',
            'accept' => 'text/html',
        ], $scrubbed->request->headers);
        self::assertSame(['POST', 'login', '192.0.2.0'], [$scrubbed->request->method, $scrubbed->request->route, $scrubbed->request->clientAddress]);
        self::assertSame($event->id, $scrubbed->id);
        self::assertNull(new Scrubber()->scrub(Events::one())->request);
        self::assertSame(['[Filtered]', '[email]'], [Scrubber::FILTERED, Scrubber::EMAIL]);
    }
}

<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Application;

use DateTimeImmutable;
use InvalidArgumentException;
use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Message\Claim;
use Phil\Lti\Platform\DeepLinkingResult;
use Phil\LtiBundle\Application\DeepLinkingHandler;
use Phil\LtiBundle\Application\Event\DeepLinkingReturned;
use Phil\LtiBundle\Application\StoredDeepLinkingReturn;
use Phil\LtiBundle\Infrastructure\Console\ResumeDeepLinkingCommand;
use Phil\LtiBundle\Infrastructure\Memory\InMemoryDeepLinkingReturns;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(DeepLinkingHandler::class)]
#[CoversClass(DeepLinkingReturned::class)]
#[CoversClass(StoredDeepLinkingReturn::class)]
#[CoversClass(ResumeDeepLinkingCommand::class)]
final class DeepLinkingHandlerTest extends TestCase
{
    private MockClock $clock;

    private InMemoryDeepLinkingReturns $returns;

    private EventDispatcher $events;

    /** @var list<DeepLinkingReturned> */
    private array $told = [];

    private bool $failing = false;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-14 12:00:00 UTC');
        $this->returns = new InMemoryDeepLinkingReturns();
        $this->events = new EventDispatcher();
        $this->events->addListener(DeepLinkingReturned::class, function (DeepLinkingReturned $event): void {
            $this->told[] = $event;

            if ($this->failing) {
                throw new RuntimeException('The gradebook is down.');
            }

            $event->setResponse(new Response('placed'));
        });
    }

    public function testAResponseIsKeptBeforeTheApplicationIsTold(): void
    {
        $result = $this->verified('n1');
        $event = $this->handler()->handle($result);

        self::assertSame($result, $event->result);
        self::assertSame($result->key, $event->key);
        self::assertFalse($event->resumed);
        self::assertSame('placed', $event->response()?->getContent());
        $kept = $this->returns->find($result->key, $this->clock->now());
        self::assertNotNull($kept);
        self::assertSame([$result->key, 'client', $result->claims], [$kept->key, $kept->clientId, $kept->claims]);
        self::assertEquals([$this->clock->now(), new DateTimeImmutable('2026-11-14 12:10:00 UTC'), $this->clock->now()], [$kept->receivedAt, $kept->expiresAt, $kept->handledAt]);
        self::assertSame([], $this->handler()->unhandled(1));
    }

    public function testAHandlingThatFailsIsResumedUnderTheSameKey(): void
    {
        $this->failing = true;
        $result = $this->verified('n2');

        try {
            $this->handler()->handle($result);
            self::fail('The listener did not fail.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The gradebook is down.', $runtimeException->getMessage());
        }

        self::assertNull($this->returns->find($result->key, $this->clock->now())?->handledAt);
        self::assertSame([], $this->handler()->unhandled(60), 'A response that came less than the delay ago may still be handled.');
        $this->clock->sleep(60);
        self::assertSame([$result->key], $this->handler()->unhandled(60));
        self::assertSame([], $this->handler()->unhandled(60, 1) === [$result->key] ? [] : ['limit']);

        $this->failing = false;
        $event = $this->handler()->resume($result->key);
        self::assertNotNull($event);
        self::assertTrue($event->resumed);
        self::assertSame($result->key, $event->key);
        self::assertEquals($result, $event->result);
        self::assertEquals($this->clock->now(), $this->returns->find($result->key, $this->clock->now())?->handledAt);
        self::assertSame([], $this->handler()->unhandled(1));

        self::assertNotNull($this->handler()->resume($result->key), 'A response handled is told again when its key is given.');
        self::assertCount(3, $this->told);
        self::assertNull($this->handler()->resume('nope'));
        $this->clock->sleep(540);
        self::assertNull($this->handler()->resume($result->key), 'A response expired is not told again.');
    }

    public function testTheCommandResumesOneResponseOrEveryOneNotHandled(): void
    {
        $this->failing = true;

        foreach (['a', 'b', 'c'] as $nonce) {
            try {
                $this->handler()->handle($this->verified($nonce));
            } catch (RuntimeException) {
                $this->clock->sleep(1);
            }
        }

        $command = new ResumeDeepLinkingCommand($this->handler());
        $this->clock->sleep(300);
        [$status, $display] = $this->execute($command, null, 300, 1);
        self::assertSame([Command::FAILURE, 1], [$status, \count($this->told) - 3]);
        self::assertStringContainsString('The response "'.$this->verified('a')->key.'" failed again: The gradebook is down.', $display);
        self::assertStringContainsString('Handled 0 responses again; 1 failed.', $display);

        $this->failing = false;
        [$status, $display] = $this->execute($command, null, 300, 100);
        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('Handled 3 responses again; 0 failed.', $display);
        self::assertSame(Command::SUCCESS, $this->execute($command, null, 300, 100)[0]);
        self::assertStringContainsString('Handled 0 responses again; 0 failed.', $this->execute($command, null, 300, 100)[1]);
        [$status, $display] = $this->execute($command, $this->verified('c')->key, 300, 100);
        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('Handled 1 responses again; 0 failed.', $display);
        [$status, $display] = $this->execute($command, 'nope', 300, 100);
        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('No response is kept under "nope".', $display);

        foreach ([[59, 1], [60, 0], [60, 1001]] as [$delay, $limit]) {
            [$status, $display] = $this->execute($command, null, $delay, $limit);
            self::assertSame(Command::INVALID, $status, $delay.' '.$limit);
            self::assertStringContainsString('The delay is 60 seconds or more, and the limit 1 to 1,000.', $display);
        }

        self::assertSame(Command::SUCCESS, $this->execute($command, null, 60, 1000)[0]);
        self::assertSame(Command::SUCCESS, $this->execute($command, null, 60, 1)[0]);
    }

    public function testClaimsKeptThatAreNotThoseOfAResponseFail(): void
    {
        $now = $this->clock->now();
        $this->returns->save(new StoredDeepLinkingReturn('broken', 'client', ['iss' => 'client'], $now, $now->modify('+1 hour')));

        $this->expectException(InvalidToken::class);
        $this->handler()->resume('broken');
    }

    public function testTheRetentionIsASecondAtLeast(): void
    {
        self::assertSame(86_400, DeepLinkingHandler::DEFAULT_RETENTION);
        self::assertInstanceOf(DeepLinkingHandler::class, new DeepLinkingHandler($this->returns, $this->events, $this->clock, 1));

        try {
            $handler = new DeepLinkingHandler($this->returns, $this->events, $this->clock, 0);
            self::fail('A response kept no time was accepted: '.$handler::class);
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('A deep linking response is kept a second at least.', $invalidArgumentException->getMessage());
        }
    }

    public function testALocalAddressIsReadAgainOnlyWhenAllowed(): void
    {
        $claims = ['iss' => 'client', 'nonce' => 'n', Claim::DEPLOYMENT_ID => 'd', Claim::CONTENT_ITEMS => [['type' => 'link', 'url' => 'https://localhost/a']]];
        $now = $this->clock->now();
        $stored = new StoredDeepLinkingReturn(DeepLinkingResult::key('client', 'n'), 'client', $claims, $now, $now->modify('+1 hour'));
        $this->returns->save($stored);

        self::assertCount(1, $stored->result(true)->items);
        self::assertCount(1, new DeepLinkingHandler($this->returns, $this->events, $this->clock, 600, true)->resume($stored->key)->result->items ?? []);

        $this->expectException(InvalidToken::class);
        $this->handler()->resume($stored->key);
    }

    /**
     * @return array{int, string}
     */
    private function execute(ResumeDeepLinkingCommand $command, ?string $key, int $delay, int $limit): array
    {
        $output = new BufferedOutput();
        $status = $command(new SymfonyStyle(new ArrayInput([]), $output), $key, $delay, $limit);

        return [$status, (string) preg_replace('/\s+/', ' ', $output->fetch())];
    }

    private function handler(): DeepLinkingHandler
    {
        return new DeepLinkingHandler($this->returns, $this->events, $this->clock, 600);
    }

    private function verified(string $nonce): DeepLinkingResult
    {
        return DeepLinkingResult::fromClaims(['iss' => 'client', 'nonce' => $nonce, Claim::DEPLOYMENT_ID => 'd', Claim::CONTENT_ITEMS => [['type' => 'link', 'url' => 'https://example.com/'.$nonce]]]);
    }
}

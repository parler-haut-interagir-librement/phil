<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommercePosBundle\Application\Contract\OfflineSaleGate;
use Phil\CommercePosBundle\Application\OfflineSale;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\CommercePosBundle\Infrastructure\Http\OfflineSaleController;
use Phil\CommercePosBundle\Tests\Unit\Support\Till;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(OfflineSaleController::class)]
final class OfflineSaleControllerTest extends TestCase
{
    public function testASaleIsImportedThenRecognised(): void
    {
        [$controller, $session] = $this->controller(2);
        $body = $this->body($session);

        $created = $controller($this->post($body));
        $again = $controller($this->post($body));

        self::assertSame([201, '{"id":"sale-1","discrepancy":0}'], [$created->getStatusCode(), $created->getContent()]);
        self::assertSame([200, '{"id":"sale-1","discrepancy":0}'], [$again->getStatusCode(), $again->getContent()]);
    }

    public function testAClosedOrUnknownSessionIsAConflict(): void
    {
        [$controller] = $this->controller(0);

        $response = $controller($this->post($this->body('nope')));

        self::assertSame([409, '{"error":"The session nope is closed."}'], [$response->getStatusCode(), $response->getContent()]);
    }

    public function testAnUnreadableSaleIsUnprocessable(): void
    {
        [$controller, $session] = $this->controller(0);

        foreach (['{not json', '[]', '"sale"', str_replace('"TEA"', '"GONE"', $this->body($session))] as $body) {
            $response = $controller($this->post($body));
            self::assertSame(422, $response->getStatusCode(), $body);
            self::assertStringStartsWith('{"error":', (string) $response->getContent());
        }
    }

    public function testABodyNotSentAsJsonIsRefusedUnread(): void
    {
        [$controller, $session] = $this->controller(1);

        foreach ([null, 'text/plain', 'application/x-www-form-urlencoded'] as $type) {
            $request = Request::create('/', 'POST', server: null === $type ? [] : ['CONTENT_TYPE' => $type], content: $this->body($session));
            $response = $controller($request);
            self::assertSame([415, '{"error":"An offline sale is sent as application\/json."}'], [$response->getStatusCode(), $response->getContent()], (string) $type);
        }

        self::assertSame(201, $controller($this->post($this->body($session), 'application/json; charset=utf-8'))->getStatusCode());
    }

    /**
     * A sale whose amounts overflow once re-priced, or paid by a method the register does not take, is the sale's
     * fault: 422, which the queue sets aside, never a 500 that would block it.
     */
    public function testASaleThatCannotBeImportedIsUnprocessableNotAnError(): void
    {
        [$controller, $session] = $this->controller(0);
        $overflowing = str_replace('"quantity":2', '"quantity":100000', str_replace('"TEA"', '"GOLD"', $this->body($session)));
        $giftCard = str_replace('"method":"cash"', '"method":"gift_card"', $this->body($session));

        foreach ([$overflowing, $giftCard] as $body) {
            $response = $controller($this->post($body));
            self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        }
    }

    public function testASaleTheGateRefusesIsForbidden(): void
    {
        $till = new Till([], new class implements OfflineSaleGate {
            #[Override]
            public function allows(OfflineSale $sale, CashSession $session): bool
            {
                return false;
            }
        });
        $session = $till->sessions->open('front', 'ada', Money::of(0, Currency::of('CAD')), new DateTimeImmutable('2026-11-03 08:00:00'));
        $controller = new OfflineSaleController($till->offline, self::createStub(EntityManagerInterface::class), new MockClock('2026-11-03 12:00:00'));

        $response = $controller($this->post($this->body($session->id)));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(['error' => \sprintf('The offline sale sale-1 may not be imported into the session %s by whoever sent it.', $session->id)], json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR));
        self::assertSame([], $till->pos->imported);
    }

    private function post(string $body, string $type = 'application/json'): Request
    {
        return Request::create('/', 'POST', server: ['CONTENT_TYPE' => $type], content: $body);
    }

    /**
     * @return array{OfflineSaleController, string}
     */
    private function controller(int $flushes): array
    {
        $till = new Till();
        $session = $till->sessions->open('front', 'ada', Money::of(0, Currency::of('CAD')), new DateTimeImmutable('2026-11-03 08:00:00'));
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly($flushes))->method('flush');

        return [new OfflineSaleController($till->offline, $entityManager, new MockClock('2026-11-03 12:00:00')), $session->id];
    }

    private function body(string $session): string
    {
        return json_encode([
            'id' => 'sale-1', 'session' => $session, 'by' => 'ada', 'rung_at' => '2026-11-03T09:30:00-05:00', 'total' => 998,
            'lines' => [['type' => 'product', 'id' => 'TEA', 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 998]],
        ], \JSON_THROW_ON_ERROR);
    }
}

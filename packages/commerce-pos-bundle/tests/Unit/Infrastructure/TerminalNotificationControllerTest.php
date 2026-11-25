<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommercePosBundle\Infrastructure\Http\TerminalNotificationController;
use Phil\CommercePosBundle\Infrastructure\Terminal\FakePaymentTerminal;
use Phil\CommercePosBundle\Tests\Unit\Support\Till;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(TerminalNotificationController::class)]
final class TerminalNotificationControllerTest extends TestCase
{
    public function testAProcessorsNotificationSettlesTheChargeAndIsAnsweredOk(): void
    {
        $till = new Till();
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $till->registers->addTerminal('t1', 'front', 'fake', 'tmr_1', 'Reader');
        $session = $till->sessions->open('front', 'ada', Money::of(0, Currency::of('CAD')), $at);
        $order = $till->cart(['TEA' => 1]);
        $till->checkout->ring($order, $session, 'ada', $at);
        $payment = $till->terminalPayments->charge($order, $session, 't1', $at);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('flush');
        $controller = new TerminalNotificationController($till->terminalPayments, $entityManager, new MockClock('2026-11-03 10:01:00'));
        [$payload, $headers] = $till->terminal->notification('evt-1', $payment->id, PaymentEvent::Captured);

        $response = $controller($this->post($payload, $headers));
        $again = $controller($this->post($payload, $headers));

        self::assertSame([200, '', 200], [$response->getStatusCode(), $response->getContent(), $again->getStatusCode()]);
        self::assertSame(PaymentState::Completed, $payment->state());
        self::assertSame('2026-11-03 10:01:00', $till->pos->payments[$payment->id]->takenAt->format('Y-m-d H:i:s'));
    }

    public function testANotificationNoAdapterVerifiesIsABadRequestAndChangesNothing(): void
    {
        $till = new Till();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');
        $controller = new TerminalNotificationController($till->terminalPayments, $entityManager, new MockClock());
        [$payload] = $till->terminal->notification('evt-1', 'p', PaymentEvent::Captured);

        foreach ([[], [FakePaymentTerminal::SIGNATURE_HEADER => ['forged']]] as $headers) {
            self::assertSame(400, $controller($this->post($payload, $headers))->getStatusCode());
        }

        $tooLarge = $controller($this->post(str_repeat(' ', TerminalNotificationController::MAX_BODY + 1), []));
        self::assertSame(413, $tooLarge->getStatusCode());
    }

    public function testABodyOfTheLargestSizeIsRead(): void
    {
        $till = new Till();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');
        $controller = new TerminalNotificationController($till->terminalPayments, $entityManager, new MockClock());
        $payload = str_pad('{"id":"e","payment":"unknown","event":"captured"}', TerminalNotificationController::MAX_BODY);

        self::assertSame(200, $controller($this->post($payload, [FakePaymentTerminal::SIGNATURE_HEADER => [hash_hmac('sha256', $payload, hash_hkdf('sha256', 'a secret of sixteen bytes or more', 32, FakePaymentTerminal::USAGE))]]))->getStatusCode());
    }

    public function testAHeaderWithoutValueIsLeftOutOfThoseTheAdaptersRead(): void
    {
        $till = new Till();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');
        $controller = new TerminalNotificationController($till->terminalPayments, $entityManager, new MockClock());
        $payload = '{"id":"e","payment":"unknown","event":"captured"}';
        $request = $this->post($payload, []);
        $request->headers->set(FakePaymentTerminal::SIGNATURE_HEADER, null);
        $request->headers->set(FakePaymentTerminal::SIGNATURE_HEADER, hash_hmac('sha256', $payload, hash_hkdf('sha256', 'a secret of sixteen bytes or more', 32, FakePaymentTerminal::USAGE)), false);
        self::assertSame([null, hash_hmac('sha256', $payload, hash_hkdf('sha256', 'a secret of sixteen bytes or more', 32, FakePaymentTerminal::USAGE))], $request->headers->all(FakePaymentTerminal::SIGNATURE_HEADER));

        self::assertSame(200, $controller($request)->getStatusCode());
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function post(string $payload, array $headers): Request
    {
        $server = ['CONTENT_TYPE' => 'application/json'];

        foreach ($headers as $name => $values) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $values[0];
        }

        return Request::create('/', 'POST', server: $server, content: $payload);
    }
}

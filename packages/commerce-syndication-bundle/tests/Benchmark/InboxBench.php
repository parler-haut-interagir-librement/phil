<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Benchmark;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Generator;
use Phil\CommerceSyndicationBundle\Application\Inbox;
use Phil\CommerceSyndicationBundle\Application\Publisher;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\PublishedPrice;
use Phil\CommerceSyndicationBundle\Infrastructure\Doctrine\DoctrineSyndicationRepository;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Database;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Partners;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\StandardWebhooks\Signer;
use Phil\StandardWebhooks\WebhookHeaders;
use Phil\StandardWebhooks\WebhookId;
use Phil\StandardWebhooks\WebhookTimestamp;

/**
 * Every partner webhook goes through the inbox: an Ed25519 verification, a JSON decode and the repository lookups,
 * and for a forwarded order the price queries that check the cost each line claims, two for the whole order.
 *
 * The repositories are Doctrine's on SQLite in memory; each rev starts from a cleared entity manager, as a request
 * does, so nothing is flushed and the same message is new at every rev.
 *
 * @BeforeMethods("setUp")
 */
final class InboxBench
{
    private readonly EntityManager $entityManager;

    private readonly DoctrineSyndicationRepository $repository;

    private readonly Inbox $inbox;

    private WebhookHeaders $headers;

    private string $body = '';

    private readonly DateTimeImmutable $at;

    public function __construct()
    {
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
        $this->entityManager = Database::entityManager();
        $this->repository = new DoctrineSyndicationRepository($this->entityManager);
        $this->inbox = new Inbox($this->repository, $this->repository, $this->repository, $this->repository, $this->repository, 'shop', 300);
        $this->headers = new Signer()->sign(WebhookId::fromString('msg_1'), WebhookTimestamp::fromDateTime($this->at), $this->body, Partners::supplierKey());
    }

    /**
     * @param array{type: string, lines: int} $params
     */
    public function setUp(array $params): void
    {
        $repository = $this->repository;

        $cad = Currency::of('CAD');
        $catalogue = array_map(static fn (int $index): string => 'SKU-'.$index, range(0, max(200, $params['lines']) - 1));
        $reseller = Partners::reseller('reseller', $catalogue);
        $repository->addPartner(Partners::supplier());
        $repository->addPartner($reseller);

        $lines = [];

        for ($line = 0; $line < $params['lines']; ++$line) {
            $sku = 'SKU-'.$line;
            // Two costs published: an old one before the grace window, the current one within it.
            $repository->recordPrice(PublishedPrice::of($reseller, $sku, Money::of(2_000, $cad), Money::of(1_300, $cad), $this->at->modify('-60 days')));
            $repository->recordPrice(PublishedPrice::of($reseller, $sku, Money::of(2_000, $cad), Money::of(1_400, $cad), $this->at->modify('-2 days')));
            $lines[] = ['sku' => $sku, 'quantity' => 1 + $line % 3, 'unit_cost' => 1_400];
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        if (Publisher::TYPE === $params['type']) {
            $key = Partners::supplierKey();
            $envelope = ['type' => Publisher::TYPE, 'audience' => 'shop', 'timestamp' => $this->at->format(OutgoingMessage::MOMENT), 'data' => ['sku' => 'TEA-1', 'name' => 'Green tea', 'currency' => 'CAD', 'retail' => 1_000, 'cost' => 700, 'tax_category' => 'food', 'available' => 5, 'active' => true]];
        } else {
            $key = Partners::resellerKey();
            $envelope = ['type' => 'order.forwarded', 'audience' => 'shop', 'data' => ['order' => 'R-77', 'currency' => 'CAD', 'lines' => $lines, 'ship_to' => ['name' => 'Ada Lovelace', 'line1' => '1 rue Principale', 'city' => 'Montréal', 'country' => 'CA']]];
        }

        $this->body = json_encode($envelope, \JSON_THROW_ON_ERROR);
        $this->headers = new Signer()->sign(WebhookId::fromString('msg_1'), WebhookTimestamp::fromDateTime($this->at), $this->body, $key);
    }

    /**
     * @Revs(5)
     *
     * @Iterations(5)
     *
     * @ParamProviders("messages")
     *
     * @param array{type: string, lines: int} $params
     */
    public function benchReceive(array $params): void
    {
        $this->entityManager->clear();
        $this->inbox->receive(Publisher::TYPE === $params['type'] ? 'supplier' : 'reseller', $this->headers, $this->body, $this->at);
    }

    /**
     * @return Generator<string, array{type: string, lines: int}>
     */
    public function messages(): Generator
    {
        yield 'product.updated' => ['type' => Publisher::TYPE, 'lines' => 0];
        yield 'order.forwarded, 1 line' => ['type' => 'order.forwarded', 'lines' => 1];
        yield 'order.forwarded, 10 lines' => ['type' => 'order.forwarded', 'lines' => 10];
        yield 'order.forwarded, 50 lines' => ['type' => 'order.forwarded', 'lines' => 50];
    }
}

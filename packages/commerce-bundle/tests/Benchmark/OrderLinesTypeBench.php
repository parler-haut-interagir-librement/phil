<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Benchmark;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Generator;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Infrastructure\Doctrine\Type\OrderLinesType;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * Every hydration of an order or a receipt decodes its lines column and rebuilds each line through its validating
 * constructors, including a currency lookup per line.
 *
 * @BeforeMethods("setUp")
 */
final class OrderLinesTypeBench
{
    private readonly OrderLinesType $type;

    private readonly SQLitePlatform $platform;

    private string $json = '[]';

    public function __construct()
    {
        $this->type = new OrderLinesType();
        $this->platform = new SQLitePlatform();
    }

    /**
     * @param array{lines: int} $params
     */
    public function setUp(array $params): void
    {
        $currency = Currency::of('CAD');
        $lines = [];

        for ($i = 0; $i < $params['lines']; ++$i) {
            $price = Money::of(500 + $i, $currency);
            $line = OrderLine::of(Sellable::of(SellableReference::of('product', 'd6/'.$i), 'Dé à 6 faces n° '.$i, $price, 'goods'), 1 + $i % 4, $price);
            $lines[$line->reference->value()] = $line;
        }

        $this->json = $this->type->convertToDatabaseValue($lines, $this->platform);
    }

    /**
     * @ParamProviders("orders")
     */
    public function benchConvertToPHPValue(): void
    {
        $this->type->convertToPHPValue($this->json, $this->platform);
    }

    /**
     * @return Generator<string, array{lines: int}>
     */
    public function orders(): Generator
    {
        yield '5 lines' => ['lines' => 5];
        yield '20 lines' => ['lines' => 20];
        yield '100 lines' => ['lines' => 100];
    }
}

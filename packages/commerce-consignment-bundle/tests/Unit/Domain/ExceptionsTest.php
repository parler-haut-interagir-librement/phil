<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Domain;

use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorPayout;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorStatement;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\StatementPeriod;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A refused value is quoted in the message, cut to a length that keeps the message readable, character by character.
 */
#[CoversClass(InvalidValue::class)]
#[CoversClass(ConsignorStatement::class)]
#[CoversClass(ConsignorPayout::class)]
final class ExceptionsTest extends TestCase
{
    public function testALongIdentifierIsQuotedOnItsFirstEightyCharacters(): void
    {
        $this->expectExceptionMessageIsOrContains('A SKU has 1 to 64 characters, none of them a control character; "'.str_repeat('é', 80).'" does not.');
        InvalidValue::checkIdentifier(str_repeat('é', 81), 'SKU');
    }

    public function testALongStatementNumberIsQuotedOnItsFirstFortyCharacters(): void
    {
        $period = StatementPeriod::of(Fixtures::utc('2026-11-01'), Fixtures::utc('2026-12-01'));

        $this->expectExceptionMessageIsOrContains('A statement number has 1 to 32 visible ASCII characters; "'.str_repeat('é', 40).'" does not.');
        ConsignorStatement::issue(str_repeat('é', 41), Owner::consignor('c'), Currency::of('CAD'), $period, [], [], Fixtures::utc('2026-12-01'));
    }

    public function testALongPayoutMethodIsQuotedOnItsFirstFortyCharacters(): void
    {
        $this->expectExceptionMessageIsOrContains('A payout method has 1 to 32 lowercase letters, digits, dots, dashes or underscores; "X'.str_repeat('é', 39).'" does not.');
        ConsignorPayout::record('k', Owner::consignor('c'), Fixtures::cad(1), Fixtures::at(9), 'X'.str_repeat('é', 40), null, Fixtures::at(9));
    }
}

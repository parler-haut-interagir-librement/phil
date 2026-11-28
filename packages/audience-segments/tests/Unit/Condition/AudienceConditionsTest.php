<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Tests\Unit\Condition;

use Phil\AudienceSegments\AudienceConditions;
use Phil\AudienceSegments\Condition\DateParticipant;
use Phil\AudienceSegments\Condition\Member;
use Phil\AudienceSegments\Condition\PersonTagged;
use Phil\AudienceSegments\Condition\PurchaseMade;
use Phil\AudienceSegments\Condition\SelectionMember;
use Phil\AudienceSegments\Condition\SupporterActive;
use Phil\AudienceSegments\Condition\TicketHolder;
use Phil\AudienceSegments\PurchaseFilter;
use Phil\AudienceSegments\Segment;
use Phil\AudienceSegments\Tests\Support\MemoryFacts;
use Phil\Rules\Condition\Leaf;
use Phil\Rules\Fact\Facts;
use Phil\Rules\Rule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AudienceConditions::class)]
#[CoversClass(TicketHolder::class)]
#[CoversClass(DateParticipant::class)]
#[CoversClass(SelectionMember::class)]
#[CoversClass(PurchaseMade::class)]
#[CoversClass(SupporterActive::class)]
#[CoversClass(PersonTagged::class)]
#[CoversClass(Member::class)]
#[CoversClass(PurchaseFilter::class)]
final class AudienceConditionsTest extends TestCase
{
    private MemoryFacts $facts;

    protected function setUp(): void
    {
        $this->facts = new MemoryFacts();
        $this->facts->tickets = ['ana' => ['spring-fair' => ['day']]];
        $this->facts->attended = ['ana' => ['spring-fair:2026-05-02']];
        $this->facts->selections = ['ana' => ['speakers-2026']];
        $this->facts->orders = ['ana' => [['sku' => 'NAQ001F-M', 'product' => 'shirt', 'state' => 'fulfilled']]];
        $this->facts->supporters = ['ana' => 'gold'];
        $this->facts->tags = ['ana' => ['press', 'crew']];
    }

    public function testRegistersTheTypesOfTheGivenPortsOnly(): void
    {
        self::assertSame([], AudienceConditions::types()->names());
        self::assertSame(['ticket.holder', 'date.participant', 'selection.member'], AudienceConditions::types(participation: $this->facts)->names());
        self::assertSame(['purchase.made'], AudienceConditions::types(purchases: $this->facts)->names());
        self::assertSame(['supporter.active'], AudienceConditions::types(supporters: $this->facts)->names());
        self::assertSame(['person.tagged'], AudienceConditions::types(tags: $this->facts)->names());
        self::assertSame(
            ['ticket.holder', 'date.participant', 'selection.member', 'purchase.made', 'supporter.active', 'person.tagged'],
            AudienceConditions::types($this->facts, $this->facts, $this->facts, $this->facts)->names(),
        );
    }

    public function testBoundsTheListsOfItsOperands(): void
    {
        $types = AudienceConditions::types($this->facts, $this->facts, $this->facts, $this->facts)->describe();
        $maxItems = static fn (string $type, string $operand): ?int => array_first(array_filter($types[$type], static fn (array $described): bool => $described['name'] === $operand))['maxItems'] ?? null;

        self::assertSame(50, $maxItems('ticket.holder', 'ticket_types'));
        self::assertSame(50, $maxItems('supporter.active', 'tiers'));
        self::assertSame(50, $maxItems('person.tagged', 'tags'));
        self::assertSame(20, $maxItems('purchase.made', 'order_states'));
        self::assertSame(100, $maxItems('purchase.made', 'skus'));
        self::assertSame(100, $maxItems('purchase.made', 'products'));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string, bool, string, array<string, scalar|null>}>
     */
    public static function cases(): iterable
    {
        yield 'holds a ticket' => ['ticket.holder', ['event' => 'spring-fair'], 'ana', true, 'ticket.held', ['event' => 'spring-fair']];
        yield 'holds a ticket of the type' => ['ticket.holder', ['event' => 'spring-fair', 'ticket_types' => ['weekend', 'day']], 'ana', true, 'ticket.held', ['event' => 'spring-fair']];
        yield 'holds no ticket of the type' => ['ticket.holder', ['event' => 'spring-fair', 'ticket_types' => ['weekend']], 'ana', false, 'ticket.not-held', ['event' => 'spring-fair']];
        yield 'holds no ticket' => ['ticket.holder', ['event' => 'spring-fair'], 'bob', false, 'ticket.not-held', ['event' => 'spring-fair']];
        yield 'attended' => ['date.participant', ['occurrence' => 'spring-fair:2026-05-02'], 'ana', true, 'date.attended', ['occurrence' => 'spring-fair:2026-05-02']];
        yield 'did not attend' => ['date.participant', ['occurrence' => 'spring-fair:2026-05-03'], 'ana', false, 'date.not-attended', ['occurrence' => 'spring-fair:2026-05-03']];
        yield 'chosen' => ['selection.member', ['selection' => 'speakers-2026'], 'ana', true, 'selection.chosen', ['selection' => 'speakers-2026']];
        yield 'not chosen' => ['selection.member', ['selection' => 'speakers-2026'], 'bob', false, 'selection.not-chosen', ['selection' => 'speakers-2026']];
        yield 'bought anything' => ['purchase.made', [], 'ana', true, 'purchase.made', ['skus' => 0, 'products' => 0, 'order_states' => 0]];
        yield 'bought the SKU' => ['purchase.made', ['skus' => ['NAQ001F-M', 'NAQ001F-L'], 'order_states' => ['fulfilled']], 'ana', true, 'purchase.made', ['skus' => 2, 'products' => 0, 'order_states' => 1]];
        yield 'did not buy the product' => ['purchase.made', ['products' => ['mug']], 'ana', false, 'purchase.none', ['skus' => 0, 'products' => 1, 'order_states' => 0]];
        yield 'bought nothing' => ['purchase.made', [], 'bob', false, 'purchase.none', ['skus' => 0, 'products' => 0, 'order_states' => 0]];
        yield 'supports' => ['supporter.active', [], 'ana', true, 'supporter.active', ['tiers' => 0]];
        yield 'supports the tier' => ['supporter.active', ['tiers' => ['gold']], 'ana', true, 'supporter.active', ['tiers' => 1]];
        yield 'supports another tier' => ['supporter.active', ['tiers' => ['silver', 'bronze']], 'ana', false, 'supporter.inactive', ['tiers' => 2]];
        yield 'does not support' => ['supporter.active', [], 'bob', false, 'supporter.inactive', ['tiers' => 0]];
        yield 'tagged' => ['person.tagged', ['tags' => ['volunteer', 'press']], 'ana', true, 'person.tagged', ['tags' => 2]];
        yield 'not tagged' => ['person.tagged', ['tags' => ['volunteer']], 'ana', false, 'person.untagged', ['tags' => 1]];
    }

    /**
     * @param array<string, mixed>       $operands
     * @param array<string, scalar|null> $details
     */
    #[DataProvider('cases')]
    public function testEvaluatesThroughItsPort(string $type, array $operands, string $member, bool $passed, string $reason, array $details): void
    {
        $result = $this->rule($type, $operands)->evaluate(Facts::of([Segment::MEMBER => $member]));

        self::assertSame($passed, $result->passed);
        self::assertSame($reason, $result->trace->reason);
        self::assertSame($details, $result->trace->details);
    }

    public function testPassesTheOperandsToThePorts(): void
    {
        $facts = Facts::of([Segment::MEMBER => 'ana']);
        $this->rule('ticket.holder', ['event' => 'fair', 'ticket_types' => ['day']])->evaluate($facts);
        $this->rule('ticket.holder', ['event' => 'fair'])->evaluate($facts);
        $this->rule('date.participant', ['occurrence' => 'fair:1'])->evaluate($facts);
        $this->rule('selection.member', ['selection' => 'crew'])->evaluate($facts);
        $this->rule('purchase.made', ['skus' => ['a'], 'products' => ['b'], 'order_states' => ['paid']])->evaluate($facts);
        $this->rule('supporter.active', ['tiers' => ['gold']])->evaluate($facts);
        $this->rule('person.tagged', ['tags' => ['press']])->evaluate($facts);

        self::assertEquals([
            ['ticket', 'ana', 'fair', ['day']],
            ['ticket', 'ana', 'fair', []],
            ['date', 'ana', 'fair:1'],
            ['selection', 'ana', 'crew'],
            ['purchase', 'ana', new PurchaseFilter(['a'], ['b'], ['paid'])],
            ['supporter', 'ana', ['gold']],
            ['tags', 'ana', ['press']],
        ], $this->facts->asked);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function everyType(): iterable
    {
        yield 'ticket.holder' => ['ticket.holder', ['event' => 'e']];
        yield 'date.participant' => ['date.participant', ['occurrence' => 'o']];
        yield 'selection.member' => ['selection.member', ['selection' => 's']];
        yield 'purchase.made' => ['purchase.made', []];
        yield 'supporter.active' => ['supporter.active', []];
        yield 'person.tagged' => ['person.tagged', ['tags' => ['t']]];
    }

    /**
     * @param array<string, mixed> $operands
     */
    #[DataProvider('everyType')]
    public function testFailsWithoutAMemberAndAsksNoPort(string $type, array $operands): void
    {
        foreach ([[], [Segment::MEMBER => 42], [Segment::MEMBER => 'a b']] as $scope) {
            $result = $this->rule($type, $operands)->evaluate(Facts::of($scope));

            self::assertFalse($result->passed);
            self::assertSame('member.missing', $result->trace->reason);
        }

        self::assertSame([], $this->facts->asked);
    }

    /**
     * @param array<string, mixed> $operands
     */
    private function rule(string $type, array $operands): Rule
    {
        return new Rule(new Leaf($type, $operands), AudienceConditions::types($this->facts, $this->facts, $this->facts, $this->facts));
    }
}

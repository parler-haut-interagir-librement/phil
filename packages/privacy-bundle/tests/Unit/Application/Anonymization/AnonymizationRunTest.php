<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Anonymization;

use Phil\PrivacyBundle\Application\Anonymization\AnonymizationReport;
use Phil\PrivacyBundle\Application\Anonymization\AnonymizationRun;
use Phil\PrivacyBundle\Application\Anonymization\ProductionGuard;
use Phil\PrivacyBundle\Application\Exception\AnonymizationRefused;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationPlan;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizerOptions;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizers;
use Phil\PrivacyBundle\Domain\Anonymization\ColumnRule;
use Phil\PrivacyBundle\Domain\Anonymization\DatabaseIdentity;
use Phil\PrivacyBundle\Domain\Anonymization\Locale\LocalePacks;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\MemoryTarget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AnonymizationRun::class)]
#[CoversClass(AnonymizationReport::class)]
#[CoversClass(ProductionGuard::class)]
#[CoversClass(AnonymizationRefused::class)]
final class AnonymizationRunTest extends TestCase
{
    public function testEveryNonNullValueIsRewrittenInOneTransaction(): void
    {
        $target = $this->target();
        $report = $this->anonymization()->run(new AnonymizationPlan([
            $this->rule('member', 'email', 'email'),
            $this->rule('member', 'name', 'constant', ['value' => 'Anonymous']),
            $this->rule('note', 'body', 'null'),
        ]), $target, 7, 'salt');

        self::assertSame(['member' => 2, 'note' => 1], $report->rows);
        self::assertSame(3, $report->total());
        self::assertSame([
            ['id' => 1, 'email' => 'user.'.substr(hash_hmac('sha256', 'ada@example.com', 'salt'), 0, 16).'@example.com', 'name' => 'Anonymous', 'plan' => 'gold'],
            ['id' => 2, 'email' => null, 'name' => 'Anonymous', 'plan' => 'free'],
            ['id' => 3, 'email' => null, 'name' => null, 'plan' => 'free'],
        ], $target->tables['member']);
        self::assertSame([['id' => 1, 'body' => null]], $target->tables['note']);
        self::assertSame(['key member', 'key note', 'begin', 'rows member id email,name', 'update member {"id":1}', 'update member {"id":2}', 'rows note id body', 'update note {"id":1}', 'commit'], $target->log);
    }

    /**
     * A seed makes the draws repeatable; without one, two runs differ.
     */
    public function testASeedMakesTheRunRepeatable(): void
    {
        $plan = new AnonymizationPlan([$this->rule('member', 'name', 'name')]);
        $names = [];

        foreach ([7, 7, 8, null, null] as $seed) {
            $target = $this->target();
            $this->anonymization()->run($plan, $target, $seed);
            $names[] = array_column($target->tables['member'], 'name');
        }

        self::assertSame($names[0], $names[1]);
        self::assertNotSame($names[0], $names[2]);
        self::assertNotSame($names[3], $names[4]);
        $name = $names[0][0];
        self::assertIsString($name);
        self::assertContains(explode(' ', $name)[0], LocalePacks::builtIn()->get('fr_FR')->givenNames, "The default locale is the run's.");
    }

    /**
     * Derived values need the salt too: without one given, a fresh salt is drawn for each run.
     */
    public function testTheDefaultSaltIsDrawnForTheRun(): void
    {
        $plan = new AnonymizationPlan([$this->rule('member', 'email', 'hash')]);
        $first = $this->target();
        $second = $this->target();
        $this->anonymization()->run($plan, $first, 1);
        $this->anonymization()->run($plan, $second, 1);

        self::assertNotSame($first->tables['member'][0]['email'], $second->tables['member'][0]['email']);
        $hash = $first->tables['member'][0]['email'];
        self::assertIsString($hash);
        self::assertSame(64, \strlen($hash));
    }

    public function testAColumnMayNameItsOwnLocale(): void
    {
        $target = $this->target();
        $this->anonymization()->run(new AnonymizationPlan([$this->rule('member', 'name', 'lastname', ['locale' => 'de_DE'])]), $target, 3);

        self::assertContains($target->tables['member'][0]['name'], LocalePacks::builtIn()->get('de_DE')->familyNames);
    }

    /**
     * An unknown type or locale, a table without key, a key column: refused before the transaction begins.
     */
    public function testWhatCanBeRefusedIsRefusedBeforeARowIsRead(): void
    {
        foreach ([
            'No anonymizer answers to the type "vat"' => $this->rule('member', 'name', 'vat'),
            'No locale pack answers to "xx_XX"' => $this->rule('member', 'name', 'name', ['locale' => 'xx_XX']),
            'The table "ghost" has no primary key, or does not exist: its rows cannot be rewritten one by one.' => $this->rule('ghost', 'name', 'name'),
            'The column "id" is part of the primary key of "member": anonymizing it would break the rows that refer to it.' => $this->rule('member', 'id', 'hash'),
        ] as $message => $rule) {
            $target = $this->target();

            try {
                $this->anonymization()->run(new AnonymizationPlan([$this->rule('note', 'body', 'null'), $rule]), $target);
                self::fail($message);
            } catch (InvalidPrivacyValue|AnonymizationRefused $refused) {
                self::assertStringStartsWith($message, $refused->getMessage());
                self::assertNotContains('begin', $target->log);
            }
        }
    }

    public function testAnOptionAnAnonymizerCannotReadRollsTheRunBack(): void
    {
        $target = $this->target();

        try {
            $this->anonymization()->run(new AnonymizationPlan([$this->rule('member', 'name', 'constant', ['value' => 'x']), $this->rule('note', 'body', 'pattern')]), $target);
            self::fail('A missing pattern stops the run.');
        } catch (InvalidPrivacyValue $invalidPrivacyValue) {
            self::assertSame('The anonymizer option "pattern" is required.', $invalidPrivacyValue->getMessage());
        }

        self::assertSame($this->target()->tables, $target->tables);
        self::assertSame('rollback', array_last($target->log));
    }

    public function testAKeyOfSeveralColumnsNamesEachRow(): void
    {
        $target = new MemoryTarget(['grant' => [['member' => 1, 'role' => 'a', 'note' => 'x'], ['member' => 1, 'role' => 'b', 'note' => 'y']]], ['grant' => ['member', 'role']]);
        $this->anonymization()->run(new AnonymizationPlan([$this->rule('grant', 'note', 'constant', ['value' => 'z'])]), $target);

        self::assertSame(['update grant {"member":1,"role":"a"}', 'update grant {"member":1,"role":"b"}'], array_values(array_filter($target->log, static fn (string $line): bool => str_starts_with($line, 'update'))));
        self::assertSame(['z', 'z'], array_column($target->tables['grant'], 'note'));
    }

    public function testTheGuardRefusesProductionAndProtectedDatabases(): void
    {
        $local = new DatabaseIdentity('postgresql', 'localhost', 'app');
        new ProductionGuard('dev')->check($local);
        new ProductionGuard('prod', ['production'])->check($local);
        new ProductionGuard('dev', ['prod'], ['db.example.com'], ['app_prod'])->check($local);
        new ProductionGuard('dev', [], [], ['app_prod'])->check(new DatabaseIdentity('sqlite', null, 'app'));
        $this->addToAssertionCount(4);

        foreach ([
            'Anonymization is refused in the "prod" environment: it rewrites personal data for good. Back the database up, restore the backup elsewhere, and anonymize there.' => [new ProductionGuard('prod'), $local],
            'Anonymization is refused in the "production" environment: it rewrites personal data for good. Back the database up, restore the backup elsewhere, and anonymize there.' => [new ProductionGuard('production', ['staging', 'production']), $local],
            'Anonymization is refused on the postgresql database app on db.example.com, which the configuration protects.' => [new ProductionGuard('dev', ['prod'], ['db.example.com']), new DatabaseIdentity('postgresql', 'db.example.com', 'app')],
            'Anonymization is refused on the mysql database app_prod on localhost, which the configuration protects.' => [new ProductionGuard('dev', ['prod'], [], ['app_prod']), new DatabaseIdentity('mysql', 'localhost', 'app_prod')],
        ] as $message => [$guard, $database]) {
            try {
                $guard->check($database);
                self::fail($message);
            } catch (AnonymizationRefused $anonymizationRefused) {
                self::assertSame($message, $anonymizationRefused->getMessage());
            }
        }
    }

    private function anonymization(): AnonymizationRun
    {
        return new AnonymizationRun(Anonymizers::builtIn(), LocalePacks::builtIn(), 'fr_FR');
    }

    private function target(): MemoryTarget
    {
        return new MemoryTarget([
            'member' => [
                ['id' => 1, 'email' => 'ada@example.com', 'name' => 'Ada', 'plan' => 'gold'],
                ['id' => 2, 'email' => null, 'name' => 'Bob', 'plan' => 'free'],
                ['id' => 3, 'email' => null, 'name' => null, 'plan' => 'free'],
            ],
            'note' => [['id' => 1, 'body' => 'Call Ada']],
        ]);
    }

    /**
     * @param array<string, scalar|null> $options
     */
    private function rule(string $table, string $column, string $type, array $options = []): ColumnRule
    {
        return new ColumnRule($table, $column, $type, new AnonymizerOptions($options), 'test');
    }
}

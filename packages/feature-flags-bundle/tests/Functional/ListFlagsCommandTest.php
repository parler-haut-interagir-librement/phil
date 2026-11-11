<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Functional;

use Phil\FeatureFlagsBundle\Infrastructure\Console\ListFlagsCommand;
use Phil\FeatureFlagsBundle\Infrastructure\Doctrine\FlagTables;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ListFlagsCommand::class)]
final class ListFlagsCommandTest extends FunctionalTestCase
{
    public function testTheListShowsEveryFlagAndWarnsOfTheExpiredOnes(): void
    {
        $tester = $this->tester();

        self::assertSame(0, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('new-checkout', $display);
        self::assertStringContainsString('checkout-team', $display);
        self::assertStringContainsString('A/B by tenant: one-column 50%, two-columns 50%', $display);
        self::assertStringContainsString('2020-01-31 (expired)', $display);
        self::assertStringContainsString('1 flag is past its expiry date: forgotten.', $display);
    }

    public function testEachFlagIsARowOfTheTable(): void
    {
        $this->post('/admin/flags/new-checkout/toggle', ['active' => '0', 'reason' => 'Paused.']);
        $this->post('/admin/flags/_new', [
            'name' => 'two-rules',
            'description' => '',
            'owner' => '',
            'kind' => 'permission',
            'expires_on' => '',
            'definition' => '{"rules": [{"attribute": "tenant", "members": ["a"]}, {"attribute": "project", "members": ["b"]}]}',
            'reason' => 'Listing.',
        ]);
        $tester = $this->tester();
        $tester->execute([]);

        $display = $tester->getDisplay();

        self::assertMatchesRegularExpression('/^ +Flag +State +Kind +Targeting +Owner +Expires on +Source *$/m', $display);
        self::assertMatchesRegularExpression('/^ +checkout-layout +active +experiment +everyone; A\/B by tenant/m', $display);
        self::assertMatchesRegularExpression('/^ +forgotten +inactive +everyone +2020-01-31 \(expired\) +phil_feature_flags\.provider\.configuration *$/m', $display);
        self::assertMatchesRegularExpression('/^ +new-checkout +inactive \(stored\) +release +1 rule +checkout-team +phil_feature_flags\.provider\.configuration *$/m', $display);
        self::assertMatchesRegularExpression('/^ +two-rules +inactive \\(stored\\) +permission +2 rules +phil_feature_flags\.provider\.doctrine *$/m', $display);
    }

    /**
     * A row of the database that no longer reads is named with its reason, apart from the table, and does not fail
     * the command.
     */
    public function testAnUnreadableRowIsReportedWithItsReason(): void
    {
        $this->connection()->insert(FlagTables::DEFINITIONS, ['name' => 'broken-row', 'definition' => 'not json', 'revision' => 1, 'updated_at' => '2026-01-01 00:00:00']);
        $tester = $this->tester();

        self::assertSame(0, $tester->execute([]));
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('1 flag cannot be read: broken-row (phil_feature_flags.provider.doctrine): The stored definition of "broken-row" is not JSON: Syntax error', $display);
    }

    public function testSeveralUnreadableRowsAreNamedInOneWarning(): void
    {
        foreach (['broken-row' => 'not json', 'another-row' => '{"rules": "all"}'] as $name => $definition) {
            $this->connection()->insert(FlagTables::DEFINITIONS, ['name' => $name, 'definition' => $definition, 'revision' => 1, 'updated_at' => '2026-01-01 00:00:00']);
        }

        $tester = $this->tester();
        $tester->execute([]);

        $display = (string) preg_replace('/\\s+/', ' ', $tester->getDisplay());
        self::assertMatchesRegularExpression('/2 flags cannot be read: another-row \\(phil_feature_flags\\.provider\\.doctrine\\): .+; broken-row \\(/', $display);
    }

    public function testTheExpiredOptionListsOnlyTheExpiredFlags(): void
    {
        $tester = $this->tester();
        $tester->execute(['--expired' => true]);

        self::assertStringContainsString('forgotten', $tester->getDisplay());
        self::assertStringNotContainsString('new-checkout', $tester->getDisplay());
    }

    /**
     * The time bomb: a CI job fails while a flag has outstayed its expiry date.
     */
    public function testAnExpiredFlagFailsTheCommandWhenAsked(): void
    {
        $tester = $this->tester();

        self::assertSame(1, $tester->execute(['--fail-on-expired' => true]));
        self::assertStringContainsString('[ERROR] 1 flag is past its expiry date: forgotten.', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        return new CommandTester(new Application($kernel)->find('phil:feature-flags:list'));
    }
}

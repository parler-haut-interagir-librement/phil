<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheckBundle\Tests\Unit\Console;

use Override;
use Phil\RouteAccessCheck\Detector\PublicRouteDetector;
use Phil\RouteAccessCheck\Detector\SecurityConfigDetector;
use Phil\RouteAccessCheck\Format\JsonFormatter;
use Phil\RouteAccessCheck\Format\TextFormatter;
use Phil\RouteAccessCheck\RouteAccessChecker;
use Phil\RouteAccessCheck\Security\SecurityMap;
use Phil\RouteAccessCheckBundle\Infrastructure\Console\CheckRouteAccessCommand;
use Phil\RouteAccessCheckBundle\Tests\Unit\Fixture\StaticRouter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(CheckRouteAccessCommand::class)]
final class CheckRouteAccessCommandTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-route-access-check-'.bin2hex(random_bytes(6));
        new Filesystem()->mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testAnUncoveredRouteFails(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString("PUBLIC    health  ANY /health\n    - public: route option public\n", $tester->getDisplay());
        self::assertStringContainsString("UNCOVERED reports  ANY /reports\n    ! no access rule for GET, HEAD, POST, PUT, PATCH, DELETE, OPTIONS, *\n", $tester->getDisplay());
        self::assertStringEndsWith("\n2 routes: 0 protected, 1 public, 0 mixed, 1 uncovered, 0 conflict, 0 ignored; 0 baselined.\n", $tester->getDisplay());
    }

    public function testCoveredRoutesPass(): void
    {
        $tester = $this->tester(['health' => new Route('/health', options: ['public' => true])]);

        self::assertSame(Command::SUCCESS, $tester->execute(['--format' => 'json']));
        self::assertStringStartsWith("{\n    \"passed\": true,", $tester->getDisplay());
    }

    public function testOnlyProblems(): void
    {
        $tester = $this->tester();
        $tester->execute(['--only-problems' => true]);

        self::assertStringNotContainsString('PUBLIC    health', $tester->getDisplay());
        self::assertStringContainsString('UNCOVERED reports', $tester->getDisplay());
    }

    public function testAnUnknownFormatIsRefused(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['--format' => 'xml'], ['capture_stderr_separately' => true]));
        self::assertSame("The format \"xml\" is unknown; the formats are text, json.\n", $tester->getErrorOutput());
        self::assertSame('', $tester->getDisplay());
        $definition = $this->command()->getDefinition();
        self::assertSame('The format of the report: text, json', $definition->getOption('format')->getDescription());
        self::assertSame('text', $definition->getOption('format')->getDefault());
        self::assertSame('f', $definition->getOption('format')->getShortcut());
        self::assertSame('b', $definition->getOption('baseline')->getShortcut());
        self::assertFalse($definition->getOption('generate-baseline')->getDefault());
        self::assertSame('phil:route-access:check', $this->command()->getName());
    }

    public function testTheBaselineIsGeneratedThenTolerated(): void
    {
        $file = $this->directory.'/nested/baseline.json';
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['--generate-baseline' => $file], ['capture_stderr_separately' => true]));
        self::assertSame("The baseline {$file} tolerates 1 failing routes.\n", $tester->getErrorOutput());
        self::assertSame("{\n    \"version\": 1,\n    \"routes\": {\n        \"reports\": \"uncovered\"\n    }\n}\n", file_get_contents($file));

        self::assertSame(Command::SUCCESS, $tester->execute(['--baseline' => $file]));
        self::assertStringContainsString('UNCOVERED reports  ANY /reports  (baselined)', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $this->tester(baseline: $file)->execute([]));
    }

    public function testTheConfiguredBaselineIsTheDefaultTarget(): void
    {
        $file = $this->directory.'/configured.json';

        self::assertSame(Command::SUCCESS, $this->tester(baseline: $file)->execute(['--generate-baseline' => null]));
        self::assertFileExists($file);

        $cwd = getcwd();
        self::assertIsString($cwd);
        chdir($this->directory);

        try {
            self::assertSame(Command::SUCCESS, $this->tester()->execute(['--generate-baseline' => null]));
            self::assertFileExists($this->directory.'/route-access-baseline.json');
        } finally {
            chdir($cwd);
        }
    }

    public function testStrictFailsOnWarnings(): void
    {
        $file = $this->directory.'/stale.json';
        file_put_contents($file, '{"version":1,"routes":{"reports":"uncovered","gone":"uncovered"}}');

        self::assertSame(Command::SUCCESS, $this->tester()->execute(['--baseline' => $file]));
        self::assertSame(Command::FAILURE, $this->tester()->execute(['--baseline' => $file, '--strict' => true]));
        self::assertSame(Command::SUCCESS, $this->tester(['health' => new Route('/health', options: ['public' => true])])->execute(['--strict' => true]));
    }

    public function testAnUnreadableBaselineIsRefused(): void
    {
        $tester = $this->tester();
        $missing = $this->directory.'/missing.json';

        self::assertSame(Command::INVALID, $tester->execute(['--baseline' => $missing], ['capture_stderr_separately' => true]));
        self::assertSame($missing.": The baseline is invalid: the file cannot be read.\n", $tester->getErrorOutput());

        self::assertSame(Command::INVALID, $tester->execute(['--baseline' => $this->directory], ['capture_stderr_separately' => true]));
        self::assertSame($this->directory.": The baseline is invalid: the file cannot be read.\n", $tester->getErrorOutput());

        $invalid = $this->directory.'/invalid.json';
        file_put_contents($invalid, '[]');
        self::assertSame(Command::INVALID, $tester->execute(['--baseline' => $invalid], ['capture_stderr_separately' => true]));
        self::assertSame($invalid.': The baseline is invalid: it is an object with "version": 1 and "routes".'."\n", $tester->getErrorOutput());

        $large = $this->directory.'/large.json';
        file_put_contents($large, str_repeat(' ', 5_000_001));
        self::assertSame(Command::INVALID, $tester->execute(['--baseline' => $large], ['capture_stderr_separately' => true]));
        self::assertSame($large.": The baseline is invalid: it is larger than 5000000 bytes.\n", $tester->getErrorOutput());

        $limit = $this->directory.'/limit.json';
        file_put_contents($limit, str_pad('{"version":1,"routes":{}}', 5_000_000, ' ', \STR_PAD_LEFT));
        self::assertSame(Command::FAILURE, $tester->execute(['--baseline' => $limit]));

        self::assertSame(Command::INVALID, $this->tester(baseline: $missing)->execute([]));
    }

    public function testTheErrorsGoToTheOutputWithoutAnErrorOutput(): void
    {
        $tester = $this->tester();
        $tester->execute(['--format' => 'xml']);

        self::assertSame("The format \"xml\" is unknown; the formats are text, json.\n", $tester->getDisplay());
    }

    /**
     * @param array<string, Route>|null $routes
     */
    private function tester(?array $routes = null, ?string $baseline = null): CommandTester
    {
        return new CommandTester($this->command($routes, $baseline));
    }

    /**
     * @param array<string, Route>|null $routes
     */
    private function command(?array $routes = null, ?string $baseline = null): CheckRouteAccessCommand
    {
        $collection = new RouteCollection();

        foreach ($routes ?? ['health' => new Route('/health', options: ['public' => true]), 'reports' => new Route('/reports')] as $name => $route) {
            $collection->add($name, $route);
        }

        $checker = new RouteAccessChecker([new PublicRouteDetector(), new SecurityConfigDetector(new SecurityMap())]);

        return new CheckRouteAccessCommand($checker, new StaticRouter($collection), [new TextFormatter(), new JsonFormatter()], $baseline);
    }
}

<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Unit\Infrastructure\Maker;

use InvalidArgumentException;
use Override;
use Phil\DddBundle\Infrastructure\Maker\AbstractDddMaker;
use Phil\DddBundle\Infrastructure\Maker\MakeDddCommand;
use Phil\DddBundle\Infrastructure\Maker\MakeDddContext;
use Phil\DddBundle\Infrastructure\Maker\MakeDddEvent;
use Phil\DddBundle\Infrastructure\Maker\MakeDddIdentifier;
use Phil\DddBundle\Infrastructure\Maker\MakeDddQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The five commands, run against a generator that writes into a temporary project directory: MakerBundle's own
 * generator needs its internal file manager, which a test outside Symfony may not build.
 */
#[CoversClass(AbstractDddMaker::class)]
#[CoversClass(MakeDddContext::class)]
#[CoversClass(MakeDddIdentifier::class)]
#[CoversClass(MakeDddCommand::class)]
#[CoversClass(MakeDddQuery::class)]
#[CoversClass(MakeDddEvent::class)]
final class MakeDddTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-make-ddd-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    /**
     * @return iterable<string, array{AbstractDddMaker, string, string, list<string>}>
     */
    public static function makers(): iterable
    {
        yield 'context' => [new MakeDddContext(), 'make:ddd:context', 'Create a bounded context: src/<Context>/{Domain,Application,Infrastructure} and a README', ['context']];
        yield 'identifier' => [new MakeDddIdentifier(), 'make:ddd:identifier', 'Create an identifier of a bounded context, and its Doctrine DBAL type', ['context', 'name']];
        yield 'command' => [new MakeDddCommand(), 'make:ddd:command', 'Create a command of a bounded context, and its handler on the command bus', ['context', 'name']];
        yield 'query' => [new MakeDddQuery(), 'make:ddd:query', 'Create a query of a bounded context, and its handler on the query bus', ['context', 'name']];
        yield 'event' => [new MakeDddEvent(), 'make:ddd:event', 'Create a domain event of a bounded context', ['context', 'name']];
    }

    /**
     * @param list<string> $arguments
     */
    #[DataProvider('makers')]
    public function testTheCommandIsNamedDescribedAndTakesItsArguments(AbstractDddMaker $maker, string $name, string $description, array $arguments): void
    {
        self::assertSame($name, $maker::getCommandName());
        self::assertSame($description, $maker::getCommandDescription());

        $command = $this->command($maker);

        self::assertSame($arguments, array_keys($command->getDefinition()->getArguments()));

        foreach ($command->getDefinition()->getArguments() as $argument) {
            // Optional, so that MakerBundle asks for a missing one instead of the console refusing the command.
            self::assertFalse($argument->isRequired());
        }

        self::assertSame('The name of the bounded context (e.g. <fg=yellow>Catalog</>)', $command->getDefinition()->getArgument('context')->getDescription());

        // No dependency to declare: the bundle registers the commands only when MakerBundle is installed.
        $maker->configureDependencies(new DependencyBuilder());
    }

    public function testTheNameArgumentSaysWhatItNames(): void
    {
        self::assertSame(
            'The name of the identifier, with or without its Id suffix (e.g. <fg=yellow>Product</>)',
            $this->command(new MakeDddIdentifier())->getDefinition()->getArgument('name')->getDescription(),
        );
        self::assertSame(
            'The name of the command, with or without its Command suffix (e.g. <fg=yellow>AddProduct</>)',
            $this->command(new MakeDddCommand())->getDefinition()->getArgument('name')->getDescription(),
        );
        self::assertSame(
            'The name of the query, with or without its Query suffix (e.g. <fg=yellow>FindProduct</>)',
            $this->command(new MakeDddQuery())->getDefinition()->getArgument('name')->getDescription(),
        );
        self::assertSame(
            'The name of the event, a fact in the past tense (e.g. <fg=yellow>ProductAdded</>)',
            $this->command(new MakeDddEvent())->getDefinition()->getArgument('name')->getDescription(),
        );
    }

    public function testMakeContextWritesTheLayersAndTheReadme(): void
    {
        $output = $this->generate(new MakeDddContext(), ['context' => ' Catalog ']);

        self::assertFileExists($this->directory.'/src/Catalog/Domain/.gitignore');
        self::assertFileExists($this->directory.'/src/Catalog/Application/.gitignore');
        self::assertFileExists($this->directory.'/src/Catalog/Infrastructure/.gitignore');
        self::assertStringStartsWith('# Catalog', (string) file_get_contents($this->directory.'/src/Catalog/README.md'));
        self::assertStringContainsString('Success!', $output);
        self::assertStringContainsString('Next: describe the context in src/Catalog/README.md', $output);
        self::assertStringContainsString('src/Catalog/Infrastructure/Persistence/Doctrine/Entity', $output);
        self::assertStringContainsString('add "Catalog" to the contexts of the Deptrac rules.', $output);
    }

    public function testMakeIdentifierWritesTheClassAndTheTypeAndShowsTheRegistration(): void
    {
        $output = $this->generate(new MakeDddIdentifier(), ['context' => 'Catalog', 'name' => 'Product']);

        self::assertStringContainsString('final readonly class ProductId extends Identifier', (string) file_get_contents($this->directory.'/src/Catalog/Domain/ProductId.php'));
        self::assertStringContainsString('final class ProductIdType extends IdentifierType', (string) file_get_contents($this->directory.'/src/Catalog/Infrastructure/Persistence/Doctrine/Type/ProductIdType.php'));
        self::assertStringContainsString("'product_id' => \\App\\Catalog\\Infrastructure\\Persistence\\Doctrine\\Type\\ProductIdType::class,", $output);
        self::assertStringContainsString("#[ORM\\Column(type: 'product_id')]", $output);
        self::assertStringContainsString('register the type in config/packages/doctrine.php, under doctrine.dbal.types:', $output);
    }

    public function testMakeCommandAndMakeQueryWriteTheMessagesAndTheirHandlers(): void
    {
        $command = $this->generate(new MakeDddCommand(), ['context' => 'Catalog', 'name' => 'AddProduct']);
        $query = $this->generate(new MakeDddQuery(), ['context' => 'Catalog', 'name' => 'FindProduct']);

        self::assertFileExists($this->directory.'/src/Catalog/Application/AddProductCommand.php');
        self::assertStringContainsString('implements CommandHandler', (string) file_get_contents($this->directory.'/src/Catalog/Application/AddProductHandler.php'));
        self::assertFileExists($this->directory.'/src/Catalog/Application/FindProductQuery.php');
        self::assertStringContainsString('implements QueryHandler', (string) file_get_contents($this->directory.'/src/Catalog/Application/FindProductHandler.php'));
        self::assertStringContainsString('Next: give the command its properties', $command);
        self::assertStringContainsString('Next: give the query its properties, and replace null', $query);
    }

    public function testMakeEventWritesTheEvent(): void
    {
        $output = $this->generate(new MakeDddEvent(), ['context' => 'Catalog', 'name' => 'ProductAdded']);

        self::assertStringContainsString('implements DomainEvent', (string) file_get_contents($this->directory.'/src/Catalog/Domain/Event/ProductAdded.php'));
        self::assertStringContainsString('Next: give the event the identifiers of the fact', $output);
    }

    public function testTheRootNamespaceOfMakerBundleIsUsed(): void
    {
        $this->generate(new MakeDddEvent(), ['context' => 'Catalog', 'name' => 'ProductAdded'], 'Acme\\');

        self::assertStringContainsString('namespace Acme\Catalog\Domain\Event;', (string) file_get_contents($this->directory.'/src/Catalog/Domain/Event/ProductAdded.php'));
    }

    public function testABadNameIsACommandError(): void
    {
        try {
            $this->generate(new MakeDddCommand(), ['context' => 'Catalog', 'name' => 'add product']);
            self::fail('The command was written.');
        } catch (RuntimeCommandException $runtimeCommandException) {
            self::assertStringStartsWith('The class name "add product" is not a PascalCase word', $runtimeCommandException->getMessage());
            self::assertSame(0, $runtimeCommandException->getCode());
            self::assertInstanceOf(InvalidArgumentException::class, $runtimeCommandException->getPrevious());
        }

        self::assertDirectoryDoesNotExist($this->directory.'/src');
    }

    public function testAMissingArgumentIsACommandError(): void
    {
        $this->expectException(RuntimeCommandException::class);
        $this->expectExceptionMessageIsOrContains('The context name "" is not a PascalCase word');

        $this->generate(new MakeDddContext(), []);
    }

    public function testAnExistingFileIsNotOverwrittenAndNothingIsWritten(): void
    {
        mkdir($this->directory.'/src/Catalog/Application', 0o777, true);
        file_put_contents($this->directory.'/src/Catalog/Application/AddProductHandler.php', 'mine');

        try {
            $this->generate(new MakeDddCommand(), ['context' => 'Catalog', 'name' => 'AddProduct']);
            self::fail('The handler was overwritten.');
        } catch (RuntimeCommandException $runtimeCommandException) {
            self::assertSame('The file "src/Catalog/Application/AddProductHandler.php" exists: it is not overwritten.', $runtimeCommandException->getMessage());
        }

        self::assertSame('mine', file_get_contents($this->directory.'/src/Catalog/Application/AddProductHandler.php'));
        self::assertFileDoesNotExist($this->directory.'/src/Catalog/Application/AddProductCommand.php');
    }

    /**
     * @param array<string, string> $arguments
     */
    private function generate(AbstractDddMaker $maker, array $arguments, string $rootNamespace = 'App\\'): string
    {
        $input = new ArrayInput($arguments, $this->command($maker)->getDefinition());
        $output = new BufferedOutput();
        /** @var array<string, string> $pending */
        $pending = [];
        $directory = $this->directory;

        $generator = self::createStub(Generator::class);
        $generator->method('getRootNamespace')->willReturn($rootNamespace);
        $generator->method('getRootDirectory')->willReturn($directory);
        $generator->method('dumpFile')->willReturnCallback(static function (string $path, string $contents) use (&$pending): void {
            $pending[$path] = $contents;
        });
        $generator->method('writeChanges')->willReturnCallback(static function () use (&$pending, $directory): void {
            foreach ($pending as $path => $contents) {
                new Filesystem()->dumpFile($directory.'/'.$path, $contents);
            }
        });

        $maker->generate($input, new ConsoleStyle($input, $output), $generator);

        return $output->fetch();
    }

    private function command(AbstractDddMaker $maker): Command
    {
        $command = new Command($maker::getCommandName());
        $maker->configureCommand($command, new InputConfiguration());

        return $command;
    }
}

<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use InvalidArgumentException;
use Override;
use Phil\EnumsBundle\Infrastructure\Maker\MakeEnum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Util\AutoloaderUtil;
use Symfony\Bundle\MakerBundle\Util\ComposerAutoloaderFinder;
use Symfony\Bundle\MakerBundle\Util\MakerFileLinkFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(MakeEnum::class)]
final class MakeEnumTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-make-enum-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testTheCommandIsNamedAndDescribed(): void
    {
        self::assertSame('make:enum', MakeEnum::getCommandName());
        self::assertSame('Create a new enum with the traits and labels of phil/enums', MakeEnum::getCommandDescription());

        $command = $this->command();

        self::assertSame('string', $command->getDefinition()->getOption('backed')->getDefault());
        self::assertTrue($command->getDefinition()->getOption('case')->isArray());
        self::assertFalse($command->getDefinition()->getOption('translatable')->acceptValue());
        self::assertFalse($command->getDefinition()->getArgument('name')->isRequired());
    }

    public function testItWritesATranslatableStringEnum(): void
    {
        $output = $this->generate(['name' => 'OrderStatus', '--case' => ['Pending', 'InTransit'], '--translatable' => true]);

        self::assertSame(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Enum;

            use Phil\Enums\Attribute\Label;
            use Phil\Enums\Attribute\Meta;
            use Phil\Enums\Traits\AsEnumerableEnumTrait;
            use Phil\EnumsBundle\Infrastructure\Translation\AsTranslatableEnumTrait;
            use Symfony\Contracts\Translation\TranslatableInterface;

            #[Meta(Label::class)]
            enum OrderStatus: string implements TranslatableInterface
            {
                use AsEnumerableEnumTrait;
                use AsTranslatableEnumTrait;

                #[Label('Pending')]
                case Pending = 'pending';

                #[Label('In transit')]
                case InTransit = 'in_transit';
            }

            PHP, (string) file_get_contents($this->directory.'/src/Enum/OrderStatus.php'));
        self::assertStringContainsString('Success!', $output);
        self::assertStringContainsString('Next: add the metadata your enum needs', $output);
    }

    public function testItWritesAnIntegerEnumAndAPureOne(): void
    {
        $this->generate(['name' => 'Level', '--backed' => 'int', '--case' => ['Low', 'High']]);
        $this->generate(['name' => 'Shape', '--backed' => 'pure', '--case' => ['Circle']]);

        $level = (string) file_get_contents($this->directory.'/src/Enum/Level.php');
        $shape = (string) file_get_contents($this->directory.'/src/Enum/Shape.php');

        self::assertStringContainsString("enum Level: int\n{", $level);
        self::assertStringContainsString("    case High = 2;\n", $level);
        self::assertStringNotContainsString('Translatable', $level);
        self::assertStringContainsString("enum Shape\n{", $shape);
        self::assertStringContainsString("    case Circle;\n", $shape);
    }

    public function testABadBlueprintIsACommandError(): void
    {
        try {
            $this->generate(['name' => 'OrderStatus', '--case' => ['In transit']]);
            self::fail('The enum was written.');
        } catch (RuntimeCommandException $runtimeCommandException) {
            self::assertSame('"In transit" is not a PHP identifier.', $runtimeCommandException->getMessage());
            self::assertSame(0, $runtimeCommandException->getCode());
            self::assertInstanceOf(InvalidArgumentException::class, $runtimeCommandException->getPrevious());
        }
    }

    public function testAnUnknownBackingIsACommandError(): void
    {
        $this->expectException(RuntimeCommandException::class);
        $this->expectExceptionMessageIsOrContains('"float" given.');

        $this->generate(['name' => 'OrderStatus', '--backed' => 'float', '--case' => ['One']]);
    }

    public function testInteractionAsksForTheCasesUntilAnEmptyAnswer(): void
    {
        $input = new ArrayInput(['name' => 'OrderStatus'], $this->command()->getDefinition());
        $input->setStream($this->stream("Pending\nShipped\n\n"));

        new MakeEnum()->interact($input, new ConsoleStyle($input, new BufferedOutput()), new Command('make:enum'));

        self::assertSame(['Pending', 'Shipped'], $input->getOption('case'));
    }

    public function testInteractionAsksNothingWhenTheCasesAreGiven(): void
    {
        $input = new ArrayInput(['name' => 'OrderStatus', '--case' => ['Pending']], $this->command()->getDefinition());
        $input->setStream($this->stream("Shipped\n\n"));

        new MakeEnum()->interact($input, new ConsoleStyle($input, new BufferedOutput()), new Command('make:enum'));

        self::assertSame(['Pending'], $input->getOption('case'));
    }

    public function testItNeedsNoDependency(): void
    {
        $dependencies = new DependencyBuilder();
        new MakeEnum()->configureDependencies($dependencies);

        self::assertSame([], $dependencies->getMissingDependencies());
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function generate(array $arguments): string
    {
        $input = new ArrayInput($arguments, $this->command()->getDefinition());
        $output = new BufferedOutput();
        $directory = $this->directory;
        $autoloader = new class($directory) extends AutoloaderUtil {
            public function __construct(
                private readonly string $root,
            ) {
                parent::__construct(new ComposerAutoloaderFinder('App'));
            }

            #[Override]
            public function getPathForFutureClass(string $className): string
            {
                return $this->root.'/src/'.str_replace(['App\\', '\\'], ['', '/'], $className).'.php';
            }
        };

        new MakeEnum()->generate($input, new ConsoleStyle($input, $output), new Generator(new FileManager(new Filesystem(), $autoloader, new MakerFileLinkFormatter(), $directory), 'App\\'));

        return $output->fetch();
    }

    private function command(): Command
    {
        $command = new Command('make:enum');
        new MakeEnum()->configureCommand($command, new InputConfiguration());

        return $command;
    }

    /**
     * @return resource
     */
    private function stream(string $answers)
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $answers);
        rewind($stream);

        return $stream;
    }
}

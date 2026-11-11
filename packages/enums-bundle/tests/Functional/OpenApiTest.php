<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Functional;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ArrayObject;
use Nelmio\ApiDocBundle\ApiDocGenerator;
use Override;
use Phil\EnumsBundle\PhilEnumsBundle;
use Phil\EnumsBundle\Tests\Functional\App\Api\ParcelView;
use Phil\EnumsBundle\Tests\Functional\App\OpenApiKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The enum schemas in the documents API Platform and NelmioApiDoc build, and `make:enum` writing an enum.
 */
#[CoversClass(PhilEnumsBundle::class)]
final class OpenApiTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return OpenApiKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
    }

    public function testApiPlatformDescribesTheEnumPropertiesOfAResource(): void
    {
        $factory = self::getContainer()->get('api_platform.json_schema.schema_factory'); // @phpstan-ignore symfonyContainer.serviceNotFound
        self::assertInstanceOf(SchemaFactoryInterface::class, $factory);

        $schema = $factory->buildSchema(ParcelView::class, 'json');
        $definitions = $schema->getDefinitions();
        $definition = $definitions[$schema->getRootDefinitionKey() ?? ''] ?? null;
        self::assertInstanceOf(ArrayObject::class, $definition);

        /** @var array<string, array<string, mixed>> $properties */
        $properties = $definition['properties'];

        self::assertSame(['ordered', 'shipped', 'delivered', 'cancelled'], $properties['status']['enum']);
        self::assertSame(['Ordered', 'Shipped', 'Delivered', 'Cancelled'], $properties['status']['x-enum-varnames']);
        self::assertIsArray($properties['status']['x-enum-descriptions']);
        self::assertSame('Waiting to be shipped.', $properties['status']['x-enum-descriptions'][0]);
        self::assertSame([1, 2, 4, null], $properties['option']['enum']);
        self::assertSame(['integer', 'null'], $properties['option']['type']);
        self::assertSame(Schema::VERSION_JSON_SCHEMA, $schema->getVersion());
    }

    public function testNelmioDescribesTheEnumsOfItsModels(): void
    {
        $generator = self::getContainer()->get('nelmio_api_doc.generator.default'); // @phpstan-ignore symfonyContainer.serviceNotFound
        self::assertInstanceOf(ApiDocGenerator::class, $generator);

        $document = json_decode($generator->generate()->toJson(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($document);

        self::assertIsArray($document['components']);

        /** @var array<string, array<string, mixed>> $schemas */
        $schemas = $document['components']['schemas'];

        self::assertSame('string', $schemas['ParcelStatus']['type']);
        self::assertSame(['ordered', 'shipped', 'delivered', 'cancelled'], $schemas['ParcelStatus']['enum']);
        self::assertSame(['Ordered', 'Shipped', 'Delivered', 'Cancelled'], $schemas['ParcelStatus']['x-enum-varnames']);
        self::assertSame([1, 2, 4], $schemas['ParcelOption']['enum']);
    }

    public function testMakeEnumWritesAnEnumIntoTheApplication(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $file = __DIR__.'/App/Enum/Probe.php';
        $tester = new CommandTester(new Application($kernel)->find('make:enum'));

        try {
            self::assertSame(Command::SUCCESS, $tester->execute(['name' => 'Probe', '--case' => ['Up', 'Down'], '--backed' => 'int'], ['interactive' => false]));
            self::assertFileExists($file);
            self::assertStringContainsString("namespace Phil\\EnumsBundle\\Tests\\Functional\\App\\Enum;\n", (string) file_get_contents($file));
            self::assertStringContainsString("    case Down = 2;\n", (string) file_get_contents($file));
        } finally {
            @unlink($file);
            @rmdir(\dirname($file));
        }
    }
}

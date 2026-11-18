<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Runtime;

use Phil\ElearningPackages\Runtime\Access;
use Phil\ElearningPackages\Runtime\DataModel;
use Phil\ElearningPackages\Runtime\ElementDefinition;
use Phil\ElearningPackages\Runtime\ElementName;
use Phil\ElearningPackages\Runtime\Mistake;
use Phil\ElearningPackages\Runtime\ScormError;
use Phil\ElearningPackages\Runtime\ScormVersion;
use Phil\ElearningPackages\Runtime\ValueCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * The tables of the data model — each element with its access, pattern, length and range, the collections and their
 * bounds, the patterns of the responses — are the values of the SCORM specifications, written once more in
 * tests/Fixture/DataModel: a figure or a pattern that changes is a change of the specification read, not a detail.
 */
#[CoversClass(DataModel::class)]
#[CoversClass(ElementDefinition::class)]
#[CoversClass(ElementName::class)]
#[CoversClass(ScormError::class)]
#[CoversClass(Mistake::class)]
#[CoversClass(ValueCheck::class)]
#[CoversClass(Access::class)]
final class DataModelTableTest extends TestCase
{
    /**
     * The model of a version is built once and kept: forgotten before each test, it is built again under the test,
     * which the coverage then credits with every line of the tables.
     */
    protected function setUp(): void
    {
        new ReflectionProperty(DataModel::class, 'definitions')->setValue(null, []);
    }

    /**
     * @return iterable<string, array{ScormVersion}>
     */
    public static function versions(): iterable
    {
        yield 'SCORM 1.2' => [ScormVersion::Scorm12];
        yield 'SCORM 2004' => [ScormVersion::Scorm2004];
    }

    #[DataProvider('versions')]
    public function testTheTablesAreThoseOfTheSpecification(ScormVersion $version): void
    {
        $expected = json_decode((string) file_get_contents(__DIR__.'/../../Fixture/DataModel/'.$version->value.'.json'), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($expected);
        self::assertIsArray($expected['elements']);
        self::assertEquals($expected, DataModel::describe($version));
        self::assertSame(array_keys($expected['elements']), array_keys(DataModel::definitions($version)));
    }

    #[DataProvider('versions')]
    public function testChildrenAreListedOnceInTheOrderOfTheModel(ScormVersion $version): void
    {
        foreach (DataModel::describe($version)['parents'] as $parent) {
            $children = DataModel::children($version, $parent);

            self::assertNotNull($children);
            self::assertSame(array_values(array_unique(explode(',', $children))), explode(',', $children));
        }

        self::assertSame(
            ScormVersion::Scorm12 === $version ? 'raw,min,max' : 'scaled,raw,min,max',
            DataModel::children($version, ScormVersion::Scorm12 === $version ? 'cmi.core.score' : 'cmi.score'),
        );
        self::assertSame(
            ScormVersion::Scorm12 === $version ? 'id,objectives,time,type,correct_responses,weighting,student_response,result,latency' : 'id,type,objectives,timestamp,correct_responses,weighting,learner_response,result,latency,description',
            DataModel::children($version, 'cmi.interactions'),
        );
    }
}

<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Phil\PrivacyBundle\Domain\Anonymization\ColumnRule;
use Phil\PrivacyBundle\Domain\Declaration\PersonalField;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Infrastructure\Doctrine\AnonymizationPlanReader;
use Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console\Fixture\OneManagerRegistry;
use Phil\PrivacyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Entity\Account;
use Phil\PrivacyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Entity\Partner;
use Phil\PrivacyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Entity\Person;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AnonymizationPlanReader::class)]
final class AnonymizationPlanReaderTest extends TestCase
{
    public function testThePlanIsDrawnFromTheMappingTheCategoriesAndTheConfiguration(): void
    {
        $reader = new AnonymizationPlanReader($this->registry(), ['phone' => 'phone', 'person_name' => 'name'], [
            'account' => ['email_address' => ['type' => 'hash', 'options' => ['length' => 12]]],
            'legacy_log' => ['ip' => ['type' => 'null']],
        ]);
        $plan = $reader->plan();

        self::assertSame([
            'account' => [
                ['email_address', 'hash', ['length' => 12], 'configuration'],
                ['birth', 'date_shift', [], Account::class.'::$birth'],
                ['name', 'name', [], 'category person_name'],
                ['phone', 'phone', [], 'category phone'],
                ['note', 'lorem', [], Partner::class.'::$note'],
            ],
            'legacy_log' => [['ip', 'null', [], 'configuration']],
            'supplier' => [['name', 'name', [], 'category person_name']],
        ], array_map(static fn (array $rules): array => array_map(static fn (ColumnRule $rule): array => [$rule->column, $rule->type, $rule->options->values, $rule->origin], $rules), $plan->tables()));
        self::assertSame(
            [[Account::class, 'nickname', 'nickname'], [Partner::class, 'alias', 'nickname']],
            array_map(static fn (PersonalField $field): array => [$field->class, $field->property, $field->category->value], $plan->uncovered),
        );
    }

    public function testWithoutCategoriesOrConfigurationOnlyTheAttributesCount(): void
    {
        $plan = new AnonymizationPlanReader($this->registry())->plan();

        self::assertSame(
            [['email_address', 'email', ['domain' => 'example.org']], ['birth', 'date_shift', []], ['note', 'lorem', []]],
            array_map(static fn (ColumnRule $rule): array => [$rule->column, $rule->type, $rule->options->values], $plan->tables()['account']),
        );
        self::assertSame(['account'], array_keys($plan->tables()));
        self::assertSame(['name', 'phone', 'nickname', 'alias'], array_map(static fn (PersonalField $field): string => $field->property, $plan->uncovered));
        self::assertSame(Person::class, $plan->uncovered[0]->class);
    }

    public function testWithoutDoctrineOnlyTheConfigurationCounts(): void
    {
        $plan = new AnonymizationPlanReader(null, ['phone' => 'phone'], ['member' => ['email' => ['type' => 'email']]])->plan();

        self::assertSame(['member'], array_keys($plan->tables()));
        self::assertSame([], $plan->uncovered);
        self::assertTrue(new AnonymizationPlanReader()->plan()->isEmpty());
    }

    public function testAMalformedConfiguredTypeIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('"E-mail" is not an anonymizer type');

        new AnonymizationPlanReader(null, [], ['member' => ['email' => ['type' => 'E-mail']]])->plan();
    }

    private function registry(): OneManagerRegistry
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__.'/Fixture/Entity']));
        $config->enableNativeLazyObjects(true);

        return new OneManagerRegistry(new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config));
    }
}

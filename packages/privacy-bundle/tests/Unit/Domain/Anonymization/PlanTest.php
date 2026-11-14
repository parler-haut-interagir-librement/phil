<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Anonymization;

use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationPlan;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizerOptions;
use Phil\PrivacyBundle\Domain\Anonymization\ColumnRule;
use Phil\PrivacyBundle\Domain\Anonymization\DatabaseIdentity;
use Phil\PrivacyBundle\Domain\Attribute\Anonymize;
use Phil\PrivacyBundle\Domain\Attribute\AsAnonymizer;
use Phil\PrivacyBundle\Domain\Declaration\AnonymizedField;
use Phil\PrivacyBundle\Domain\Declaration\DeclaredAnonymizations;
use Phil\PrivacyBundle\Domain\Declaration\Lineage;
use Phil\PrivacyBundle\Domain\Declaration\PersonalField;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use Phil\PrivacyBundle\Tests\Unit\Domain\Anonymization\Fixture\Customer;
use Phil\PrivacyBundle\Tests\Unit\Domain\Declaration\Fixture\BaseModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(AnonymizationPlan::class)]
#[CoversClass(ColumnRule::class)]
#[CoversClass(AnonymizerOptions::class)]
#[CoversClass(Anonymize::class)]
#[CoversClass(AsAnonymizer::class)]
#[CoversClass(AnonymizedField::class)]
#[CoversClass(DeclaredAnonymizations::class)]
#[CoversClass(Lineage::class)]
#[CoversClass(DatabaseIdentity::class)]
final class PlanTest extends TestCase
{
    /**
     * Tables in alphabetical order, columns in the order first given, a later rule replacing an earlier one.
     */
    public function testAPlanGroupsItsRulesByTable(): void
    {
        $email = $this->rule('member', 'email', 'email');
        $name = $this->rule('member', 'name', 'name');
        $override = $this->rule('member', 'email', 'hash', 'configuration');
        $note = $this->rule('account', 'note', 'lorem');
        $field = new PersonalField(Customer::class, 'phone', DataCategory::fromString('phone'), null);
        $plan = new AnonymizationPlan([$email, $name, $note, $override], [$field]);

        self::assertSame(['account' => [$note], 'member' => [$override, $name]], $plan->tables());
        self::assertSame([$field], $plan->uncovered);
        self::assertFalse($plan->isEmpty());
        self::assertTrue(new AnonymizationPlan([])->isEmpty());
        self::assertSame([], new AnonymizationPlan([])->uncovered);
        self::assertSame('configuration', $override->origin);
    }

    public function testARuleNamesItsTableColumnAndType(): void
    {
        foreach ([['', 'email', 'email', 'An anonymized column names its table and itself.'], ['member', '', 'email', 'An anonymized column names its table and itself.'], ['member', 'email', 'E-mail', '"E-mail" is not an anonymizer type: lowercase letters, digits and underscores, starting with a letter.']] as [$table, $column, $type, $message]) {
            try {
                $this->rule($table, $column, $type);
                self::fail($message);
            } catch (InvalidPrivacyValue $invalidPrivacyValue) {
                self::assertSame($message, $invalidPrivacyValue->getMessage());
            }
        }
    }

    public function testOptionsAreReadByType(): void
    {
        $options = new AnonymizerOptions(['domain' => 'example.org', 'days' => 7, 'zero' => 0, 'flag' => false, 'empty' => null]);

        self::assertTrue($options->has('empty'));
        self::assertFalse($options->has('missing'));
        self::assertSame('example.org', $options->string('domain'));
        self::assertSame('example.com', $options->string('missing', 'example.com'));
        self::assertSame('example.com', $options->string('empty', 'example.com'));
        self::assertSame(7, $options->int('days', 30));
        self::assertSame(30, $options->int('missing', 30));
        self::assertSame(0, $options->int('zero', 30, 0));
        self::assertFalse($options->scalar('flag', true));
        self::assertNull($options->scalar('empty', 'x'));
        self::assertSame('x', $options->scalar('missing', 'x'));

        foreach ([
            'The anonymizer option "missing" is required.' => static fn (): string => $options->string('missing'),
            'The anonymizer option "empty" must be a string.' => static fn (): string => $options->string('empty'),
            'The anonymizer option "days" must be a string.' => static fn (): string => $options->string('days', 'x'),
            'The anonymizer option "domain" must be an integer of at least 1.' => static fn (): int => $options->int('domain', 30),
            'The anonymizer option "zero" must be an integer of at least 1.' => static fn (): int => $options->int('zero', 30),
            'The anonymizer option "days" must be an integer of at least 8.' => static fn (): int => $options->int('days', 30, 8),
            'Anonymizer options are named: a list of values is not a set of options.' => static fn (): AnonymizerOptions => new AnonymizerOptions(['a' => 1, 2]),
        ] as $message => $read) {
            try {
                $read();
                self::fail($message);
            } catch (InvalidPrivacyValue $invalidPrivacyValue) {
                self::assertSame($message, $invalidPrivacyValue->getMessage());
            }
        }
    }

    public function testTheAttributesNameAWellFormedType(): void
    {
        $attribute = new Anonymize('date_shift2', ['days' => 3]);

        self::assertSame('date_shift2', $attribute->type);
        self::assertSame(['days' => 3], $attribute->options->values);
        self::assertSame([], new Anonymize('email')->options->values);
        self::assertSame('vat_number', new AsAnonymizer('vat_number')->type);

        foreach (['Email', '1email', 'e-mail', 'email ', ''] as $type) {
            foreach ([static fn (): object => new Anonymize($type), static fn (): object => new AsAnonymizer($type)] as $build) {
                try {
                    $build();
                    self::fail($type);
                } catch (InvalidPrivacyValue $invalidPrivacyValue) {
                    self::assertSame(\sprintf('"%s" is not an anonymizer type: lowercase letters, digits and underscores, starting with a letter.', $type), $invalidPrivacyValue->getMessage());
                }
            }
        }
    }

    /**
     * Declarations are read through parents, private ones included, keyed by property.
     */
    public function testDeclarationsAreReadThroughTheLineage(): void
    {
        $fields = DeclaredAnonymizations::of(Customer::class);

        self::assertSame(['email', 'birthDate'], array_keys($fields));
        self::assertSame([Customer::class, 'email', 'email', ['domain' => 'example.org']], [$fields['email']->class, $fields['email']->property, $fields['email']->anonymize->type, $fields['email']->anonymize->options->values]);
        self::assertSame('date_shift', $fields['birthDate']->anonymize->type);
        self::assertSame(
            [[BaseModel::class, 'createdFrom'], [Customer::class, 'email'], [Customer::class, 'plan'], [Customer::class, 'birthDate']],
            array_map(static fn (ReflectionProperty $property): array => [$property->getDeclaringClass()->getName(), $property->getName()], Lineage::properties(Customer::class)),
        );
    }

    public function testADatabaseDescribesItself(): void
    {
        self::assertSame('sqlite database /tmp/app.sqlite', new DatabaseIdentity('sqlite', null, '/tmp/app.sqlite')->describe());
        self::assertSame('postgresql database app on db.example.com', new DatabaseIdentity('postgresql', 'db.example.com', 'app')->describe());
    }

    private function rule(string $table, string $column, string $type, string $origin = 'App\Member::$x'): ColumnRule
    {
        return new ColumnRule($table, $column, $type, new AnonymizerOptions(), $origin);
    }
}

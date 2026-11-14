<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Anonymization;

use Phil\PrivacyBundle\Application\Contract\AnonymizationTarget;
use Phil\PrivacyBundle\Application\Exception\AnonymizationRefused;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationContext;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationPlan;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizers;
use Phil\PrivacyBundle\Domain\Anonymization\ColumnRule;
use Phil\PrivacyBundle\Domain\Anonymization\Locale\LocalePacks;
use Phil\PrivacyBundle\Domain\Contract\Anonymizer;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Rewrites the columns of a plan, row by row, in one transaction.
 *
 * **Everything that can be refused is refused before a row is read**: an unknown anonymizer type, an unknown
 * locale, a table without a primary key, a rule on a key column. An option an anonymizer cannot read is found on
 * the first row, inside the transaction, which is rolled back.
 *
 * **The run draws from one random source.** Without a seed it is the system's; with one, the same plan on the same
 * data gives the same copy, which a test or a shared fixture can rely on. Derived values — hashed, emails — also
 * need the same salt to come out the same. The default salt is drawn for the run and never kept.
 */
final readonly class AnonymizationRun
{
    public function __construct(
        private Anonymizers $anonymizers,
        private LocalePacks $locales,
        private string $locale = 'en_US',
    ) {
    }

    public function run(AnonymizationPlan $plan, AnonymizationTarget $target, ?int $seed = null, ?string $salt = null): AnonymizationReport
    {
        $random = null === $seed ? new Randomizer() : new Randomizer(new Xoshiro256StarStar(hash('sha256', (string) $seed, true)));
        $salt ??= random_bytes(32);
        $tables = [];

        foreach ($plan->tables() as $table => $rules) {
            $key = $target->keyOf($table);
            $columns = [];

            foreach ($rules as $rule) {
                if (\in_array($rule->column, $key, true)) {
                    throw AnonymizationRefused::keyColumn($table, $rule->column);
                }

                $columns[$rule->column] = [$this->anonymizers->get($rule->type), $this->context($rule, $random, $salt)];
            }

            $tables[$table] = [$key, $columns];
        }

        return $target->transactional(static function () use ($tables, $target): AnonymizationReport {
            $counts = [];

            foreach ($tables as $table => [$key, $columns]) {
                $counts[$table] = self::table($target, $table, $key, $columns);
            }

            return new AnonymizationReport($counts);
        });
    }

    /**
     * @param non-empty-string                                                 $table
     * @param non-empty-list<non-empty-string>                                 $key
     * @param array<non-empty-string, array{Anonymizer, AnonymizationContext}> $columns
     */
    private static function table(AnonymizationTarget $target, string $table, array $key, array $columns): int
    {
        $count = 0;

        foreach ($target->rows($table, $key, array_keys($columns)) as $row) {
            $values = [];

            foreach ($columns as $column => [$anonymizer, $context]) {
                $value = $row[$column] ?? null;

                if (\is_scalar($value)) {
                    $values[$column] = $anonymizer->anonymize($value, $context);
                }
            }

            if ([] !== $values) {
                $target->update($table, array_combine($key, array_map(static fn (string $column): mixed => $row[$column] ?? null, $key)), $values);
                ++$count;
            }
        }

        return $count;
    }

    private function context(ColumnRule $rule, Randomizer $random, string $salt): AnonymizationContext
    {
        return new AnonymizationContext($rule->options, $this->locales->get($rule->options->string('locale', $this->locale)), $random, $salt);
    }
}

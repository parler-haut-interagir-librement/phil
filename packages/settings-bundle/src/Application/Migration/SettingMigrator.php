<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Application\Migration;

use Closure;
use JsonException;
use Phil\SettingsBundle\Application\SettingCatalog;
use Phil\SettingsBundle\Domain\Contract\SettingMigration;
use Phil\SettingsBundle\Domain\Contract\SettingMigrationStore;
use Phil\SettingsBundle\Domain\Exception\InvalidSetting;
use Phil\SettingsBundle\Domain\Exception\InvalidSettingValue;
use Phil\SettingsBundle\Domain\Migration\Step;
use Phil\SettingsBundle\Domain\Migration\StepKind;
use Phil\SettingsBundle\Domain\Migration\StoredValue;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

/**
 * Applies the migrations not yet applied, in their order, each in one transaction with the record of its version: run
 * twice, the second run changes nothing. The steps of a migration see the changes of those before them; the values of
 * the keys a migration touches are held in memory while it runs. A dry run says what would change and writes nothing.
 *
 * A migration changes how values are stored, not who sees what: it dispatches no change event, and the values a
 * request already read stay until its end.
 */
final readonly class SettingMigrator
{
    /** The shape of a version. */
    public const string VERSION = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,189}$/D';

    /**
     * @param ContainerInterface $migrations the migrations, by version
     * @param list<string>       $order      the versions, in the order they run
     */
    public function __construct(
        private SettingCatalog $catalog,
        private SettingMigrationStore $store,
        private ContainerInterface $migrations,
        private array $order,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<string> the versions not yet applied, in their order
     */
    public function pending(): array
    {
        $applied = array_flip($this->store->appliedMigrations());

        return array_values(array_filter($this->order, static fn (string $version): bool => !isset($applied[$version])));
    }

    /**
     * @return list<MigrationReport> one per migration not yet applied
     *
     * @throws InvalidSetting when a step names a setting not declared, or a previous default the setting refuses
     */
    public function migrate(bool $dryRun = false): array
    {
        $reports = [];

        foreach ($this->pending() as $version) {
            $migration = $this->migrations->get($version);
            \assert($migration instanceof SettingMigration);
            $changes = $this->changes($migration);

            if (!$dryRun) {
                $this->store->applyMigration($version, $changes, $this->clock->now());
            }

            $removed = \count(array_filter($changes, static fn (StoredValue $change): bool => null === $change->value));
            $reports[] = new MigrationReport($version, \count($changes) - $removed, $removed, !$dryRun);
        }

        return $reports;
    }

    /**
     * @return list<StoredValue>
     */
    private function changes(SettingMigration $migration): array
    {
        $values = new MigrationValues($this->store);

        foreach ($migration->steps() as $step) {
            match ($step->kind) {
                StepKind::Rename => $this->rename($values, $step),
                StepKind::Convert => $this->convert($values, $step),
                StepKind::DefaultChanged => $this->defaultChanged($values, $step),
                StepKind::Remove => $values->replace($step->key, []),
            };
        }

        return $values->changes();
    }

    private function rename(MigrationValues $values, Step $step): void
    {
        $to = $step->to;
        \assert(\is_string($to));
        $definition = $this->catalog->get($to);
        $target = $values->of($to);

        foreach ($values->of($step->key) as $subject => $stored) {
            if (isset($target[$subject]) || $stored->scope !== $definition->scope->value) {
                continue;
            }

            $value = $definition->decode((string) $stored->value);

            if (null !== $value && $value !== $definition->default) {
                $target[$subject] = new StoredValue($stored->scope, $stored->subject, $to, $definition->encode($value));
            }
        }

        $values->replace($step->key, []);
        $values->replace($to, $target);
    }

    private function convert(MigrationValues $values, Step $step): void
    {
        $definition = $this->catalog->get($step->key);
        $converter = $step->converter;
        \assert($converter instanceof Closure);
        $converted = [];

        foreach ($values->of($step->key) as $subject => $stored) {
            try {
                $value = $definition->normalize($converter(json_decode((string) $stored->value, true, flags: \JSON_THROW_ON_ERROR)));
            } catch (InvalidSettingValue|JsonException) {
                continue;
            }

            if ($value !== $definition->default) {
                $converted[$subject] = new StoredValue($stored->scope, $stored->subject, $step->key, $definition->encode($value));
            }
        }

        $values->replace($step->key, $converted);
    }

    private function defaultChanged(MigrationValues $values, Step $step): void
    {
        $definition = $this->catalog->get($step->key);
        $kept = array_filter($values->of($step->key), static fn (StoredValue $stored): bool => $definition->decode((string) $stored->value) !== $definition->default);
        $site = Subject::site();

        if ($step->keepForSite && Scope::Site === $definition->scope && !isset($kept[$site->key()])) {
            try {
                $previous = $definition->normalize($step->previous);
            } catch (InvalidSettingValue $invalidSettingValue) {
                throw InvalidSetting::because(\sprintf('The previous default of the setting "%s" breaks its rules: %s', $step->key, $invalidSettingValue->getMessage()));
            }

            if ($previous !== $definition->default) {
                $kept[$site->key()] = new StoredValue($site->scope->value, $site->id, $step->key, $definition->encode($previous));
            }
        }

        $values->replace($step->key, $kept);
    }
}

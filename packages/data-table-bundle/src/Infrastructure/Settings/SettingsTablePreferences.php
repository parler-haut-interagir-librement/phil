<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Infrastructure\Settings;

use JsonException;
use Override;
use Phil\DataTableBundle\Application\Contract\TablePreferences;
use Phil\DataTableBundle\Application\State\TablePreference;
use Phil\SettingsBundle\Application\Contract\CurrentUser;
use Phil\SettingsBundle\Application\Settings;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use Psr\Log\LoggerInterface;

/**
 * Each user's choices, table by table, in one text setting of phil/settings-bundle, which the bundle declares. A
 * visitor keeps theirs in the address only. Choices that would no longer fit in the setting's 2,000 characters are not
 * kept, and the refusal is logged as a warning with the table's name and the length, never the user.
 */
final readonly class SettingsTablePreferences implements TablePreferences
{
    public const string KEY = 'data_table.preferences';

    public const int MAX_LENGTH = 2000;

    public function __construct(
        private Settings $settings,
        private CurrentUser $user,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function load(string $table): TablePreference
    {
        $stored = $this->stored()[$table] ?? null;
        $stored = \is_array($stored) ? $stored : [];

        $columns = \is_array($stored['columns'] ?? null) ? array_values(array_filter($stored['columns'], \is_string(...))) : [];

        return new TablePreference($columns, \is_int($stored['size'] ?? null) ? $stored['size'] : 0);
    }

    #[Override]
    public function save(string $table, TablePreference $preference): void
    {
        $user = $this->user->id();

        if (null === $user) {
            return;
        }

        $stored = [...$this->stored(), $table => ['columns' => $preference->columns, 'size' => $preference->pageSize]];
        $encoded = json_encode($stored, \JSON_THROW_ON_ERROR);

        if (\strlen($encoded) > self::MAX_LENGTH) {
            $this->logger?->warning('The preferences of the table "{table}" are not kept: {length} characters, more than {limit}.', [
                'table' => $table,
                'length' => \strlen($encoded),
                'limit' => self::MAX_LENGTH,
            ]);

            return;
        }

        $this->settings->save(Subject::user($user), [self::KEY => $encoded]);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function stored(): array
    {
        try {
            $stored = json_decode($this->settings->string(self::KEY), true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return \is_array($stored) ? $stored : [];
    }
}

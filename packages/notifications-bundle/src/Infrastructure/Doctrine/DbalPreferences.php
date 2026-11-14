<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Override;
use Phil\NotificationsBundle\Application\Contract\NotificationPreferences;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Application\Preferences\QuietHours;
use Phil\NotificationsBundle\Application\Preferences\RecipientSettings;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Domain\Identifiers;

/**
 * Each recipient's settings in one row, as a JSON object; a recipient without a row has the defaults of the
 * configuration. The application saves what a recipient chooses with `save()`, and forgets it with `forget()`.
 */
final readonly class DbalPreferences implements NotificationPreferences
{
    private const string TABLE = NotificationTables::PREFERENCES;

    public function __construct(
        private Connection $connection,
        private RecipientSettings $defaults = new RecipientSettings(),
    ) {
    }

    /**
     * @throws InvalidNotification when the stored row is not settings
     */
    #[Override]
    public function settingsFor(string $recipient): RecipientSettings
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT settings FROM %s WHERE recipient = ?', self::TABLE), [$recipient]);

        if (false === $row) {
            return $this->defaults;
        }

        $settings = new StoredRow($row, 'preference')->json('settings');
        $quiet = $settings['quiet_hours'] ?? null;
        $optOuts = $settings['opt_outs'] ?? [];
        $locale = $settings['locale'] ?? null;
        $digest = DigestFrequency::tryFrom(\is_string($settings['digest'] ?? null) ? $settings['digest'] : '');

        if (null === $digest || !\is_array($optOuts) || !array_is_list($optOuts) || (null !== $locale && !\is_string($locale))) {
            throw InvalidNotification::because(\sprintf('The stored preferences of "%s" are not settings.', $recipient));
        }

        return new RecipientSettings(
            $digest,
            null === $quiet ? null : $this->quietHours($quiet, $recipient),
            array_map(static fn (mixed $optOut): string => \is_string($optOut) ? $optOut : '', $optOuts),
            $locale,
        );
    }

    public function save(string $recipient, RecipientSettings $settings): void
    {
        $json = json_encode([
            'digest' => $settings->digest->value,
            'quiet_hours' => $settings->quietHours instanceof QuietHours ? [
                'start' => $settings->quietHours->start,
                'end' => $settings->quietHours->end,
                'timezone' => $settings->quietHours->timezone->getName(),
            ] : null,
            'opt_outs' => $settings->optOuts,
            'locale' => $settings->locale,
        ], \JSON_THROW_ON_ERROR);

        $this->connection->transactional(static function (Connection $connection) use ($recipient, $json): void {
            // MySQL counts the rows an update changes, not those it finds: whether the row is there is asked first.
            if (false === $connection->fetchOne(\sprintf('SELECT 1 FROM %s WHERE recipient = ?', self::TABLE), [$recipient])) {
                $connection->insert(self::TABLE, ['recipient' => Identifiers::id($recipient, 'recipient'), 'settings' => $json]);
            } else {
                $connection->update(self::TABLE, ['settings' => $json], ['recipient' => $recipient]);
            }
        });
    }

    public function forget(string $recipient): void
    {
        $this->connection->delete(self::TABLE, ['recipient' => $recipient]);
    }

    /**
     * @throws InvalidNotification when the quiet hours are not an object of three strings
     */
    private function quietHours(mixed $quiet, string $recipient): QuietHours
    {
        if (!\is_array($quiet) || !\is_string($quiet['start'] ?? null) || !\is_string($quiet['end'] ?? null) || !\is_string($quiet['timezone'] ?? null)) {
            throw InvalidNotification::because(\sprintf('The stored quiet hours of "%s" have a start, an end and a time zone.', $recipient));
        }

        return QuietHours::of($quiet['start'], $quiet['end'], $quiet['timezone']);
    }
}

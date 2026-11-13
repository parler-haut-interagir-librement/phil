<?php

declare(strict_types=1);

namespace Phil\IncidentMode;

use Phil\IncidentMode\Exception\UnknownPreset;

/**
 * The catalogue of presets an incident is started from, in the order they are offered.
 *
 * `Presets::defaults()` holds the built-in presets; `withDefaults()` adds the application's own after them, a preset of
 * the same name replacing the built-in one in its place, and leaves out the built-in presets it is told to.
 */
final readonly class Presets
{
    public const string SUSPEND_SIGNUPS = 'suspend-signups';

    public const string SUSPEND_INVITATIONS = 'suspend-invitations';

    public const string SUSPEND_NEW_MESSAGES = 'suspend-new-messages';

    public const string SUSPEND_CONTRIBUTIONS = 'suspend-contributions';

    public const string READ_ONLY = 'read-only';

    /** @var array<string, Preset> */
    private array $presets;

    /**
     * @param iterable<Preset> $presets a later preset of the same name replaces the earlier one in its place
     */
    public function __construct(iterable $presets)
    {
        $byName = [];

        foreach ($presets as $preset) {
            $byName[$preset->name] = $preset;
        }

        $this->presets = $byName;
    }

    public static function defaults(): self
    {
        return new self(self::builtIn());
    }

    /**
     * @param iterable<Preset> $presets the application's presets
     * @param list<string>     $without the names of the built-in presets to leave out
     */
    public static function withDefaults(iterable $presets, array $without = []): self
    {
        $builtIn = array_filter(self::builtIn(), static fn (Preset $preset): bool => !\in_array($preset->name, $without, true));

        return new self([...$builtIn, ...$presets]);
    }

    /**
     * @return list<Preset>
     */
    public static function builtIn(): array
    {
        return [
            Preset::of(self::SUSPEND_SIGNUPS, 'Suspend sign-ups', [Restriction::SIGNUPS], Duration::ofHours(2), Duration::ofDays(7)),
            Preset::of(self::SUSPEND_INVITATIONS, 'Suspend invitations', [Restriction::INVITATIONS], Duration::ofHours(2), Duration::ofDays(7)),
            Preset::of(self::SUSPEND_NEW_MESSAGES, 'Suspend new messages', [Restriction::NEW_MESSAGES], Duration::ofHours(1), Duration::ofDays(3)),
            Preset::of(
                self::SUSPEND_CONTRIBUTIONS,
                'Suspend new contributions',
                [Restriction::SIGNUPS, Restriction::INVITATIONS, Restriction::NEW_MESSAGES, Restriction::NEW_POSTS, Restriction::NEW_COMMENTS, Restriction::UPLOADS],
                Duration::ofHours(1),
                Duration::ofDays(3),
            ),
            Preset::of(self::READ_ONLY, 'Read only', [Restriction::READ_ONLY], Duration::ofMinutes(30), Duration::ofDays(2)),
        ];
    }

    public function get(string $name): Preset
    {
        return $this->presets[$name] ?? throw UnknownPreset::named($name);
    }

    public function has(string $name): bool
    {
        return isset($this->presets[$name]);
    }

    /**
     * @return list<Preset>
     */
    public function all(): array
    {
        return array_values($this->presets);
    }
}

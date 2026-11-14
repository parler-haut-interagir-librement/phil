<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Application;

use Override;
use Phil\SettingsBundle\Application\Contract\CurrentUser;
use Phil\SettingsBundle\Application\Contract\VisitorPreferences;
use Phil\SettingsBundle\Application\Event\SettingsChanged;
use Phil\SettingsBundle\Domain\Contract\SettingRepository;
use Phil\SettingsBundle\Domain\Exception\InvalidSetting;
use Phil\SettingsBundle\Domain\Exception\InvalidSettingValue;
use Phil\SettingsBundle\Domain\Exception\UnknownSetting;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingType;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Reads and writes the settings. A value is read typed, and falls back on the default when nothing is stored or what
 * is stored no longer keeps the setting's rules; a user's setting is the current user's, or for a visitor the
 * default — or their cookie's value, for a setting kept for visitors. Only what differs from the default is stored,
 * so a default changed in the code reaches everyone who never chose.
 *
 * The values of a subject are read in one query, once per request, and each value is decoded once: the service
 * forgets them on `reset()`, which the container calls between two requests of a worker.
 */
final class Settings implements ResetInterface
{
    /** @var array<string, array<string, string>> the encoded values, by subject */
    private array $stored = [];

    /** @var array<string, array<string, bool|int|string|list<string>>> the values read, decoded, by subject then key */
    private array $read = [];

    public function __construct(
        private readonly SettingCatalog $catalog,
        private readonly SettingRepository $repository,
        private readonly CurrentUser $currentUser,
        private readonly VisitorPreferences $visitor,
        private readonly ClockInterface $clock,
        private readonly ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * The value of a setting: the site's, or the user's given, else the current user's, else the visitor's.
     *
     * @return bool|int|string|list<string>
     *
     * @throws UnknownSetting when no setting has the key
     */
    public function get(string $key, ?string $user = null): bool|int|string|array
    {
        $definition = $this->catalog->get($key);

        if (Scope::Site === $definition->scope) {
            return $this->read($definition, Subject::site());
        }

        $user ??= $this->currentUser->id();

        return null === $user ? $this->visitorValue($definition) : $this->read($definition, Subject::user($user));
    }

    /**
     * @throws InvalidSetting when the setting is not a boolean
     */
    public function bool(string $key, ?string $user = null): bool
    {
        $value = $this->typed($key, SettingType::Boolean, $user);
        \assert(\is_bool($value));

        return $value;
    }

    /**
     * @throws InvalidSetting when the setting is not an integer
     */
    public function int(string $key, ?string $user = null): int
    {
        $value = $this->typed($key, SettingType::Integer, $user);
        \assert(\is_int($value));

        return $value;
    }

    /**
     * The value of a text or of a choice.
     *
     * @throws InvalidSetting when the setting is neither
     */
    public function string(string $key, ?string $user = null): string
    {
        $value = $this->typed($key, SettingType::Choice === $this->catalog->get($key)->type ? SettingType::Choice : SettingType::Text, $user);
        \assert(\is_string($value));

        return $value;
    }

    /**
     * @return list<string>
     *
     * @throws InvalidSetting when the setting is not a list of choices
     */
    public function list(string $key, ?string $user = null): array
    {
        $value = $this->typed($key, SettingType::Choices, $user);
        \assert(\is_array($value));

        return $value;
    }

    /**
     * The values of the settings given, of a subject, or of the current visitor when it is null.
     *
     * @param array<SettingDefinition> $definitions
     *
     * @return array<string, bool|int|string|list<string>> by key
     */
    public function values(?Subject $subject, array $definitions): array
    {
        $values = [];

        foreach ($definitions as $definition) {
            $values[$definition->key] = $subject instanceof Subject ? $this->read($definition, $subject) : $this->visitorValue($definition);
        }

        return $values;
    }

    /**
     * What saving these values would do, without saving them: for each setting given, its value now and the value it
     * would have, a value back to its default included. A subject of null previews the current visitor's values.
     *
     * @param array<SettingDefinition> $definitions the settings shown, in their order
     * @param array<string, mixed>     $values      the values proposed, by key; a setting without one keeps its value
     *
     * @return list<SettingPreview>
     *
     * @throws InvalidSettingValue when a setting's rules refuse its proposed value
     */
    public function preview(?Subject $subject, array $definitions, array $values): array
    {
        $current = $this->values($subject, $definitions);
        $previews = [];

        foreach ($definitions as $definition) {
            $now = $current[$definition->key];
            $proposed = \array_key_exists($definition->key, $values) ? $definition->normalize($values[$definition->key]) : $now;
            $previews[] = new SettingPreview($definition, $now, $proposed);
        }

        return $previews;
    }

    /**
     * Sets values of a subject, and says which changed: a value back to its default is no longer stored. Every value is
     * checked first, so that a refused value leaves the others unchanged.
     *
     * @param array<string, mixed> $values by key
     *
     * @return list<string> the keys whose value changed
     *
     * @throws UnknownSetting      when no setting has a key
     * @throws InvalidSetting      when a setting is not of the subject's scope
     * @throws InvalidSettingValue when a setting's rules refuse its value
     */
    public function save(Subject $subject, array $values): array
    {
        $write = [];
        $removed = [];
        $changed = [];

        foreach ($values as $key => $value) {
            $definition = $this->catalog->get($key);

            if ($definition->scope !== $subject->scope) {
                throw InvalidSetting::because(\sprintf('The setting "%s" is not a %s setting.', $key, $subject->scope->value));
            }

            $value = $definition->normalize($value);

            if ($value === $this->read($definition, $subject)) {
                continue;
            }

            $changed[] = $key;

            if ($value === $definition->default) {
                $removed[] = $key;
            } else {
                $write[$key] = $definition->encode($value);
            }
        }

        if ([] !== $changed) {
            $this->repository->save($subject, $write, $removed, $this->clock->now());
            unset($this->stored[$subject->key()], $this->read[$subject->key()]);
            $this->events?->dispatch(new SettingsChanged($subject->scope->value, $subject->id, $changed));
        }

        return $changed;
    }

    /**
     * Brings settings of a subject back to their default.
     *
     * @param list<string> $keys
     *
     * @return list<string> the keys whose value changed
     */
    public function restoreDefaults(Subject $subject, array $keys): array
    {
        $defaults = [];

        foreach ($keys as $key) {
            $defaults[$key] = $this->catalog->get($key)->default;
        }

        return $this->save($subject, $defaults);
    }

    /**
     * Erases every value of a subject, those of settings no longer declared included.
     *
     * @return list<string> the keys erased
     */
    public function forget(Subject $subject): array
    {
        $erased = $this->repository->erase($subject);
        unset($this->stored[$subject->key()], $this->read[$subject->key()]);

        if ([] !== $erased) {
            $this->events?->dispatch(new SettingsChanged($subject->scope->value, $subject->id, $erased));
        }

        return $erased;
    }

    #[Override]
    public function reset(): void
    {
        $this->stored = [];
        $this->read = [];
    }

    /**
     * @return bool|int|string|list<string>
     */
    private function typed(string $key, SettingType $type, ?string $user): bool|int|string|array
    {
        if ($this->catalog->get($key)->type !== $type) {
            throw InvalidSetting::because(\sprintf('The setting "%s" is not read as a %s.', $key, $type->value));
        }

        return $this->get($key, $user);
    }

    /**
     * @return bool|int|string|list<string>
     */
    private function read(SettingDefinition $definition, Subject $subject): bool|int|string|array
    {
        $subjectKey = $subject->key();

        if (isset($this->read[$subjectKey][$definition->key])) {
            return $this->read[$subjectKey][$definition->key];
        }

        $stored = ($this->stored[$subjectKey] ??= $this->repository->values($subject))[$definition->key] ?? null;

        return $this->read[$subjectKey][$definition->key] = (null === $stored ? null : $definition->decode($stored)) ?? $definition->default;
    }

    /**
     * @return bool|int|string|list<string>
     */
    private function visitorValue(SettingDefinition $definition): bool|int|string|array
    {
        $value = $definition->anonymous ? $this->visitor->values()[$definition->key] ?? null : null;

        try {
            return null === $value ? $definition->default : $definition->normalize($value);
        } catch (InvalidSettingValue) {
            return $definition->default;
        }
    }
}

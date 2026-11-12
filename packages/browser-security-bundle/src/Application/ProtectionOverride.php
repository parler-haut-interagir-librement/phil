<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application;

use Phil\BrowserSecurityBundle\Domain\Enum\Protection;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Phil\BrowserSecurityBundle\Domain\Syntax\ProtectionSyntax;

/**
 * What a route, a controller or a service changes in the protections of its responses, by the name of the header or
 * of the protection: values added to a collection, values replaced, protections removed.
 *
 * `Content-Security-Policy` is one of the names: its values are sources by directive, and removing it sends no policy.
 * Every value is checked here, against the grammar of its protection.
 *
 * @phpstan-import-type Value from ProtectionSyntax
 */
final readonly class ProtectionOverride
{
    public const string ROUTE_DEFAULT = '_browser_security';

    private const string POLICY = 'Content-Security-Policy';

    /** What this override changes in the content security policy, if anything. */
    public ?PolicyOverride $policy;

    /** @var array<string, list<string>|array<string, list<string>>> */
    private array $add;

    /** @var array<string, Value> */
    private array $replace;

    /** @var list<string> */
    private array $remove;

    /**
     * @param array<array-key, mixed> $add     what is added, by name: `Permissions-Policy`, `Clear-Site-Data`, `Integrity-Policy` and its report-only form, `signed-cookies`, `external-redirects`, `Content-Security-Policy`
     * @param array<array-key, mixed> $replace what is written instead of the configured value, by name
     * @param list<string>            $remove  the names of the protections this response goes without
     *
     * @throws InvalidPolicy when a name is unknown or a value is not one its protection takes
     */
    public function __construct(array $add = [], array $replace = [], array $remove = [])
    {
        $this->policy = isset($add[self::POLICY]) || isset($replace[self::POLICY]) || \in_array(self::POLICY, $remove, true)
            ? PolicyOverride::fromArray(['add' => $add[self::POLICY] ?? [], 'replace' => $replace[self::POLICY] ?? [], 'disabled' => \in_array(self::POLICY, $remove, true)])
            : null;
        unset($add[self::POLICY], $replace[self::POLICY]);

        $additions = [];
        $replacements = [];

        foreach ($add as $name => $value) {
            $protection = Protection::named((string) $name);
            $additions[$protection->value] = ProtectionSyntax::collection($protection, $value);
        }

        foreach ($replace as $name => $value) {
            $protection = Protection::named((string) $name);
            $replacements[$protection->value] = ProtectionSyntax::normalize($protection, $value);
        }

        $this->add = $additions;
        $this->replace = $replacements;
        $removed = [];

        foreach ($remove as $name) {
            if (self::POLICY !== $name) {
                $removed[] = Protection::named($name)->value;
            }
        }

        $this->remove = $removed;
    }

    /**
     * The override a route declares in its `_browser_security` default: a map of `add`, `replace` and `remove`.
     *
     * @throws InvalidPolicy when the value is not such a map
     */
    public static function fromArray(mixed $value): self
    {
        if (!\is_array($value)) {
            throw new InvalidPolicy('The "_browser_security" default of a route is a map of add, replace and remove.');
        }

        $unknown = array_diff(array_keys($value), ['add', 'replace', 'remove']);

        if ([] !== $unknown) {
            throw new InvalidPolicy(\sprintf('The "_browser_security" default of a route does not take "%s".', implode('", "', $unknown)));
        }

        $add = $value['add'] ?? [];
        $replace = $value['replace'] ?? [];
        $remove = $value['remove'] ?? [];

        if (!\is_array($add) || !\is_array($replace) || !\is_array($remove) || !array_is_list($remove) || array_any($remove, static fn (mixed $name): bool => !\is_string($name))) {
            throw new InvalidPolicy('The "add" and "replace" of a "_browser_security" default are maps, and its "remove" a list of names.');
        }

        /** @var list<string> $remove */
        return new self($add, $replace, $remove);
    }

    /**
     * The value of a protection after this override: replaced, added to, then removed, which gives null.
     *
     * @param Value|null $value
     *
     * @return Value|null
     */
    public function apply(Protection $protection, string|bool|array|null $value): string|bool|array|null
    {
        $name = $protection->value;

        if (\in_array($name, $this->remove, true)) {
            return null;
        }

        $value = $this->replace[$name] ?? $value;

        if (isset($this->add[$name])) {
            $value = ProtectionSyntax::merge(\is_array($value) ? $value : [], $this->add[$name]);
        }

        return $value;
    }

    /**
     * The protections this override names, apart from the content security policy.
     *
     * @return list<Protection>
     */
    public function protections(): array
    {
        return array_map(Protection::from(...), array_values(array_unique([...array_keys($this->add), ...array_keys($this->replace), ...$this->remove])));
    }

    /**
     * @throws InvalidPolicy when it changes what acts before the controller is known, which only a route can
     */
    public function assertAfterRouting(): void
    {
        foreach ($this->protections() as $protection) {
            if ($protection->actsBeforeController()) {
                throw new InvalidPolicy(\sprintf('"%s" acts before the controller is known: only the "_browser_security" default of a route can change it.', $protection->value));
            }
        }
    }
}

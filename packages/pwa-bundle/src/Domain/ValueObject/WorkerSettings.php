<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Domain\ValueObject;

use Phil\PwaBundle\Domain\Exception\InvalidManifest;

/**
 * What the service worker is told: the prefix of its caches, the scope it controls, how it answers each path, the
 * paths it leaves alone, and the page it shows when a page cannot be fetched.
 */
final readonly class WorkerSettings
{
    public const int MAX_RULES = 20;

    /** @var non-empty-string */
    public string $cache;

    /** @var non-empty-string */
    public string $scope;

    /** @var list<non-empty-string> */
    public array $exclude;

    /**
     * @param list<CacheRule> $rules   the first rule whose prefix starts the path decides
     * @param list<string>    $exclude the prefixes of the paths it never answers
     */
    public function __construct(
        string $cache = 'phil-pwa',
        string $scope = '/',
        public array $rules = [],
        array $exclude = [],
        public bool $offline = true,
    ) {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9-]{0,31}$/D', $cache)) {
            throw InvalidManifest::because('The cache of the service worker is 1 to 32 lower-case letters, digits or "-".');
        }

        $this->cache = $cache;
        $this->scope = Rule::localPath($scope, 'scope of the service worker');

        if (!str_ends_with($this->scope, '/')) {
            throw InvalidManifest::because('The scope of the service worker is a directory: it ends with "/".');
        }

        if (\count($rules) > self::MAX_RULES || \count($exclude) > self::MAX_RULES) {
            throw InvalidManifest::because(\sprintf('The service worker has %d cache rules and %d exclusions at most.', self::MAX_RULES, self::MAX_RULES));
        }

        $this->exclude = array_map(static fn (string $prefix): string => Rule::localPath($prefix, 'excluded prefix'), $exclude);
    }
}

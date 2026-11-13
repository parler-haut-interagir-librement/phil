<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Application;

use Phil\PwaBundle\Application\Contract\AssetUrls;
use Phil\PwaBundle\Application\Contract\PrecacheProvider;
use Phil\PwaBundle\Domain\Exception\InvalidManifest;
use Phil\PwaBundle\Domain\ValueObject\CacheRule;
use Phil\PwaBundle\Domain\ValueObject\Rule;
use Phil\PwaBundle\Domain\ValueObject\WorkerSettings;

/**
 * The script served at the service worker's address: its settings, then `importScripts()` of the code of the worker,
 * an asset served by AssetMapper at its versioned address. The precache list holds the offline page, the configured
 * paths and assets, and the paths of every PrecacheProvider, each once, 200 at most; its version is a hash of the
 * settings, so a new deployment that changes an asset or a path installs new caches and drops the old ones.
 */
final readonly class ServiceWorkerScript
{
    public const int MAX_PRECACHE = 200;

    /**
     * @param list<string>               $precache  paths of the site, or logical paths of assets
     * @param iterable<PrecacheProvider> $providers
     */
    public function __construct(
        private WorkerSettings $settings,
        private AssetUrls $assets,
        private string $script,
        private array $precache = [],
        private iterable $providers = [],
    ) {
    }

    /**
     * The settings the worker reads, with their version.
     *
     * @return array{version: string, cache: string, scope: string, precache: list<string>, offline: ?string, rules: list<array{prefix: string, strategy: string}>, exclude: list<string>, script: string}
     */
    public function settings(?string $offlinePath): array
    {
        $offline = $this->settings->offline ? $offlinePath : null;
        $precache = null === $offline ? [] : [$offline];

        foreach ($this->precache as $source) {
            $source = Rule::source($source, 'precached path');
            $precache[] = str_starts_with($source, '/') ? $source : $this->assets->url($source);
        }

        foreach ($this->providers as $provider) {
            foreach ($provider->urls() as $url) {
                $precache[] = Rule::localPath($url, 'path of a precache provider');
            }
        }

        $precache = array_values(array_unique($precache));

        if (\count($precache) > self::MAX_PRECACHE) {
            throw InvalidManifest::because(\sprintf('The service worker precaches %d paths at most.', self::MAX_PRECACHE));
        }

        $settings = [
            'cache' => $this->settings->cache,
            'scope' => $this->settings->scope,
            'precache' => $precache,
            'offline' => $offline,
            'rules' => array_map(static fn (CacheRule $rule): array => ['prefix' => $rule->prefix, 'strategy' => $rule->strategy->value], $this->settings->rules),
            'exclude' => $this->settings->exclude,
            'script' => $this->assets->url(Rule::assetPath($this->script, 'script of the service worker')),
        ];

        return ['version' => substr(hash('xxh128', $this->json($settings)), 0, 16), ...$settings];
    }

    public function render(?string $offlinePath): string
    {
        $settings = $this->settings($offlinePath);

        return "// The service worker of phil/pwa-bundle: its settings, then its code.\n"
            .'self.philPwa = '.$this->json($settings).";\n"
            .'importScripts('.$this->json($settings['script']).");\n";
    }

    private function json(mixed $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }
}

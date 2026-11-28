<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\SitesConsoleBundle\Domain\Document\Fields;
use Phil\SitesConsoleBundle\Domain\Document\Json;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;
use Phil\SitesConsoleBundle\Domain\Version\Packages;

/**
 * What an agent answers to `status`: the site, when the answer was made, the versions of PHP and Symfony, the
 * installed packages with their versions, and the health of the site.
 */
final readonly class StatusDocument
{
    public const int MAX_PACKAGES = 2000;

    /**
     * @param array<string, string> $packages the versions by package name, sorted by name
     */
    public function __construct(
        public string $site,
        public DateTimeImmutable $generatedAt,
        public string $php,
        public string $symfony,
        public array $packages,
        public HealthSummary $health,
    ) {
    }

    /**
     * @throws MalformedDocument
     */
    public static function fromJson(string $json): self
    {
        return self::fromArray(Json::decodeObject($json));
    }

    /**
     * @throws MalformedDocument
     */
    public static function fromArray(mixed $value): self
    {
        $object = Fields::object($value, 'status');
        $generatedAt = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, Fields::string($object, 'generated_at', 32));

        if (false === $generatedAt) {
            throw MalformedDocument::because('"generated_at" is a date in the ATOM format.');
        }

        return new self(
            Fields::string($object, 'site', 64),
            $generatedAt,
            self::version($object, 'php'),
            self::version($object, 'symfony'),
            self::packages($object['packages'] ?? []),
            HealthSummary::fromArray($object['health'] ?? null),
        );
    }

    /**
     * @return array{site: string, generated_at: string, php: string, symfony: string, packages: array<string, string>, health: array{status: string, checks: list<array{name: string, status: string, summary: string}>}}
     */
    public function toArray(): array
    {
        return [
            'site' => $this->site,
            'generated_at' => $this->generatedAt->format(DateTimeInterface::ATOM),
            'php' => $this->php,
            'symfony' => $this->symfony,
            'packages' => $this->packages,
            'health' => $this->health->toArray(),
        ];
    }

    /**
     * @param array<array-key, mixed> $object
     */
    private static function version(array $object, string $name): string
    {
        $version = Fields::string($object, $name, Packages::VERSION_MAX_LENGTH);

        if (!Packages::isVersion($version)) {
            throw MalformedDocument::because(\sprintf('"%s" is a version.', $name));
        }

        return $version;
    }

    /**
     * @return array<string, string>
     */
    private static function packages(mixed $value): array
    {
        $object = [] === $value ? [] : Fields::object($value, 'packages');

        if (\count($object) > self::MAX_PACKAGES) {
            throw MalformedDocument::because(\sprintf('"packages" holds at most %d packages.', self::MAX_PACKAGES));
        }

        $packages = [];

        foreach ($object as $name => $version) {
            if (!\is_string($name) || !Packages::isName($name) || !\is_string($version) || !Packages::isVersion($version)) {
                throw MalformedDocument::because('"packages" maps package names to versions.');
            }

            $packages[$name] = $version;
        }

        ksort($packages, \SORT_STRING);

        return $packages;
    }
}

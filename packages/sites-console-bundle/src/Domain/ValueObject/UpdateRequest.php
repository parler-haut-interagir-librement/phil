<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\ValueObject;

use Phil\SitesConsoleBundle\Domain\Document\Fields;
use Phil\SitesConsoleBundle\Domain\Document\Json;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;
use Phil\SitesConsoleBundle\Domain\Version\Packages;

/**
 * What the console posts to `update`: the step's identifier, which makes it idempotent, the package and the version
 * to bring the site to.
 */
final readonly class UpdateRequest
{
    public function __construct(
        public string $id,
        public string $package,
        public string $version,
    ) {
    }

    /**
     * @throws MalformedDocument
     */
    public static function fromJson(string $json, int $maxBytes = Json::MAX_BYTES): self
    {
        $object = Json::decodeObject($json, $maxBytes);
        $package = Fields::string($object, 'package', 214);
        $version = Fields::string($object, 'version', Packages::VERSION_MAX_LENGTH);

        if (!Packages::isName($package) || !Packages::isVersion($version)) {
            throw MalformedDocument::because('"package" is a package name and "version" a version.');
        }

        return new self(Fields::string($object, 'id', 128), $package, $version);
    }

    public function toJson(): string
    {
        return Json::encode(['id' => $this->id, 'package' => $this->package, 'version' => $this->version]);
    }
}

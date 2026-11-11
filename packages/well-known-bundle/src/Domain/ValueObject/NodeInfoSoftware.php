<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Domain\ValueObject;

use Phil\WellKnownBundle\Domain\Exception\InvalidValue;

/**
 * The software a server runs, as NodeInfo 2.1 names it: a canonical name of lowercase letters, digits and hyphens, a
 * version, and where its code and its home page are.
 */
final readonly class NodeInfoSoftware
{
    /**
     * @throws InvalidValue when the name is not a canonical name, the version is empty, or a link is not an `https://` URL
     */
    public function __construct(
        public string $name,
        public string $version,
        public ?string $repository = null,
        public ?string $homepage = null,
    ) {
        if (1 !== preg_match('/^[a-z0-9-]{1,64}$/D', $name)) {
            throw InvalidValue::because(\sprintf('A NodeInfo software name is 1 to 64 lowercase letters, digits and hyphens; "%s" is not one.', $name));
        }

        if ('' === $version) {
            throw InvalidValue::because('A NodeInfo software has a version.');
        }

        foreach ([$repository, $homepage] as $url) {
            if (null !== $url && !str_starts_with($url, 'https://')) {
                throw InvalidValue::because(\sprintf('The repository and home page of a NodeInfo software are https:// URLs; "%s" is not one.', $url));
            }
        }
    }

    /**
     * @return array{name: string, version: string, repository?: string, homepage?: string}
     */
    public function toArray(): array
    {
        return array_filter(['name' => $this->name, 'version' => $this->version, 'repository' => $this->repository, 'homepage' => $this->homepage], static fn (?string $value): bool => null !== $value);
    }
}

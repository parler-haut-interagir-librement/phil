<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Signature\VerifyingKey;
use Phil\SitesConsoleBundle\Domain\ValueObject\AgentUrl;

/**
 * A site the console operates: its identifier, its name, the address of its agent, the public key its agent signs
 * with, and the groups it belongs to. The console keeps no credential of the site: only what verifies the site.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final class Site
{
    public const string TABLE = 'phil_sites_console_site';

    public const string ID_PATTERN = '/^[a-z](?:[a-z0-9-]{0,62}[a-z0-9])?$/D';

    public const string GROUP_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,31}$/D';

    public const int NAME_MAX_LENGTH = 100;

    public const int MAX_GROUPS = 20;

    /**
     * @param list<string> $groups
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 64)]
        private readonly string $id,
        #[ORM\Column(name: 'name', length: self::NAME_MAX_LENGTH)]
        private string $name,
        #[ORM\Column(name: 'agent_url', length: 2048)]
        private string $agentUrl,
        #[ORM\Column(name: 'public_key', length: 64)]
        private string $publicKey,
        #[ORM\Column(name: 'site_groups', type: 'json')]
        private array $groups,
        #[ORM\Column(name: 'created_at')]
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param list<string> $groups
     */
    public static function register(string $id, string $name, AgentUrl $agentUrl, VerifyingKey $publicKey, array $groups, DateTimeImmutable $now): self
    {
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw InvalidValue::because('The identifier of a site is 1 to 64 lowercase letters, digits and inner dashes, starting with a letter.');
        }

        return new self($id, self::checkedName($name), $agentUrl->value, $publicKey->toBase64(), self::checkedGroups($groups), $now);
    }

    /**
     * @param list<string> $groups
     */
    public function change(string $name, AgentUrl $agentUrl, VerifyingKey $publicKey, array $groups): void
    {
        $this->name = self::checkedName($name);
        $this->agentUrl = $agentUrl->value;
        $this->publicKey = $publicKey->toBase64();
        $this->groups = self::checkedGroups($groups);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function agentUrl(): AgentUrl
    {
        // The address was checked when it was stored; insecure addresses were allowed then or refused.
        return AgentUrl::fromString($this->agentUrl, true);
    }

    public function verifyingKey(): VerifyingKey
    {
        return VerifyingKey::fromBase64($this->publicKey);
    }

    /**
     * @return list<string>
     */
    public function groups(): array
    {
        return $this->groups;
    }

    public function inGroup(string $group): bool
    {
        return \in_array($group, $this->groups, true);
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function checkedName(string $name): string
    {
        $trimmed = trim($name);

        if ('' === $trimmed || mb_strlen($trimmed) > self::NAME_MAX_LENGTH || 1 === preg_match('/[\x00-\x1F\x7F]/', $trimmed)) {
            throw InvalidValue::because(\sprintf('The name of a site is 1 to %d characters without control characters.', self::NAME_MAX_LENGTH));
        }

        return $trimmed;
    }

    /**
     * @param list<string> $groups
     *
     * @return list<string>
     */
    private static function checkedGroups(array $groups): array
    {
        $unique = array_values(array_unique($groups));

        if (\count($unique) > self::MAX_GROUPS) {
            throw InvalidValue::because(\sprintf('A site belongs to at most %d groups.', self::MAX_GROUPS));
        }

        foreach ($unique as $group) {
            if (1 !== preg_match(self::GROUP_PATTERN, $group)) {
                throw InvalidValue::because('A group is 1 to 32 lowercase letters, digits, dashes and underscores.');
            }
        }

        sort($unique, \SORT_STRING);

        return $unique;
    }
}

<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Mailer;

use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Mailer\Contract\TenantMailSettings;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Exception\RfcComplianceException;

/**
 * The settings of `phil_tenancy.mailer`. A tenant with its own domain names its `sender`. A tenant without one is sent
 * from the platform's sender — under the tenant's `name` when it has one — with its own address as `reply_to`, so
 * that replies reach it while the platform's domain, the one its SPF, DKIM and DMARC records cover, signs the mail.
 * Every address is checked as the container compiles.
 */
final readonly class ConfiguredTenantMailSettings implements TenantMailSettings
{
    /** @var array<string, TenantMail> */
    private array $settings;

    /**
     * @param array<array-key, array{transport?: ?string, sender?: ?string, name?: ?string, reply_to?: ?string}> $tenants
     *
     * @throws InvalidValue when an address is malformed, or a tenant without a sender has no platform sender to use
     */
    public function __construct(?string $platformSender = null, array $tenants = [])
    {
        $platform = null === $platformSender ? null : $this->address($platformSender, 'platform_sender');
        $settings = [];

        foreach ($tenants as $reference => $tenant) {
            $id = TenantId::fromString((string) $reference);
            $sender = $tenant['sender'] ?? null;
            $replyTo = $tenant['reply_to'] ?? null;
            $name = $tenant['name'] ?? null;
            $from = match (true) {
                null !== $sender => $this->named($this->address($sender, $id->value.'.sender'), $name),
                null !== $replyTo || null !== $name => $platform instanceof Address
                    ? $this->named($platform, $name)
                    : throw InvalidValue::because(\sprintf('The tenant "%s" has no sender of its own: its mail is sent from phil_tenancy.mailer.platform_sender, which is not set.', $id->value)),
                default => null,
            };

            $settings[$id->value] = new TenantMail(
                $tenant['transport'] ?? null,
                $from,
                null === $replyTo ? null : $this->address($replyTo, $id->value.'.reply_to'),
            );
        }

        $this->settings = $settings;
    }

    #[Override]
    public function for(TenantId $tenant): ?TenantMail
    {
        return $this->settings[$tenant->value] ?? null;
    }

    private function named(Address $address, ?string $name): Address
    {
        return null === $name ? $address : new Address($address->getAddress(), $name);
    }

    private function address(string $address, string $key): Address
    {
        try {
            return Address::create($address);
        } catch (InvalidArgumentException|RfcComplianceException $invalid) {
            throw InvalidValue::because(\sprintf('phil_tenancy.mailer: "%s" (%s) is not an address: %s', $address, $key, $invalid->getMessage()));
        }
    }
}

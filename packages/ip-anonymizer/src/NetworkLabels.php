<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer;

use Override;
use Phil\IpAnonymizer\Contract\NetworkLabeller;
use Phil\IpAnonymizer\Exception\InvalidNetwork;
use Phil\IpAnonymizer\Internal\PrefixMask;

/**
 * A table of networks and their labels, which the application writes: `['192.0.2.0/24' => 'office', '2001:db8::/32'
 * => 'eu-west']`. An address takes the label of the longest prefix that holds it.
 *
 * A lookup costs one mask and one hash lookup per distinct prefix length of the address's family, at most 33 for
 * IPv4 and 129 for IPv6, whatever the size of the table.
 */
final readonly class NetworkLabels implements NetworkLabeller
{
    public const int MAX_LABEL_BYTES = 64;

    /**
     * @var array<string, list<array{string, array<string, string>}>> by family, the masks of each prefix length in
     *                                                                descending order with their networks' labels
     */
    private array $tables;

    /**
     * @param array<string, string> $labels the label of each network in CIDR notation
     *
     * @throws InvalidNetwork when a network is not in CIDR notation, is listed twice, or its label is empty or longer
     *                        than 64 bytes
     */
    public function __construct(array $labels)
    {
        $byLength = [];

        foreach ($labels as $cidr => $label) {
            $network = IpNetwork::parse($cidr);

            if ('' === $label || \strlen($label) > self::MAX_LABEL_BYTES) {
                throw InvalidNetwork::because(\sprintf('The label of %s is 1 to %d bytes long.', $cidr, self::MAX_LABEL_BYTES));
            }

            $family = $network->address->family->value;
            $bytes = $network->address->bytes;

            if (isset($byLength[$family][$network->prefixLength][$bytes])) {
                throw InvalidNetwork::because(\sprintf('The network %s is listed twice.', $network->toString()));
            }

            $byLength[$family][$network->prefixLength][$bytes] = $label;
        }

        $tables = [];

        foreach ($byLength as $family => $lengths) {
            krsort($lengths);

            foreach ($lengths as $length => $networks) {
                $tables[$family][] = [PrefixMask::of(IpFamily::from($family), $length), $networks];
            }
        }

        $this->tables = $tables;
    }

    #[Override]
    public function labelOf(IpAddress $address): ?string
    {
        foreach ($this->tables[$address->family->value] ?? [] as [$mask, $networks]) {
            $label = $networks[$address->bytes & $mask] ?? null;

            if (null !== $label) {
                return $label;
            }
        }

        return null;
    }
}

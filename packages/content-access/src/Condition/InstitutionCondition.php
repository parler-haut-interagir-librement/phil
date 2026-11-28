<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Condition;

use InvalidArgumentException;
use Phil\ContentAccess\AccessFacts;
use Phil\ContentAccess\Viewer;
use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Operand\Operand;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Operand\OperandSchema;
use Phil\Rules\Type\ConditionType;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * `access.institution`: the viewer belongs to an institution, by the verified domain of their email address — the
 * domain itself or one of its subdomains — or by the network address of the request inside a declared range, such as
 * `192.0.2.0/24` or `2001:db8::/32`. Nothing about the viewer is kept or logged: the verdict names how they matched,
 * never the domain or the address.
 *
 * A misspelt domain or range fails the condition, closed, with the reason `access.institution-invalid`.
 */
final readonly class InstitutionCondition implements ConditionType
{
    public const int MAX_ENTRIES = 100;

    public function name(): string
    {
        return 'access.institution';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema(
            Operand::scalarList('domains', required: false, maxItems: self::MAX_ENTRIES, maxLength: 253),
            Operand::scalarList('networks', required: false, maxItems: self::MAX_ENTRIES, maxLength: 43),
        );
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $domains = $operands->has('domains') ? $operands->scalars('domains') : [];
        $networks = $operands->has('networks') ? $operands->scalars('networks') : [];

        if ([] === $domains && [] === $networks) {
            return Verdict::fail('access.institution-empty');
        }

        $canonical = [];

        foreach ($domains as $index => $domain) {
            try {
                $canonical[] = Viewer::domain(\is_string($domain) ? $domain : '');
            } catch (InvalidArgumentException) {
                return Verdict::fail('access.institution-invalid', ['operand' => 'domains', 'index' => $index]);
            }
        }

        $ranges = [];

        foreach ($networks as $index => $network) {
            if (!\is_string($network) || !self::isNetwork($network)) {
                return Verdict::fail('access.institution-invalid', ['operand' => 'networks', 'index' => $index]);
            }

            $ranges[] = $network;
        }

        $viewerDomain = $facts->has(AccessFacts::VIEWER_EMAIL_DOMAIN) ? $facts->get(AccessFacts::VIEWER_EMAIL_DOMAIN) : null;

        if (\is_string($viewerDomain)) {
            foreach ($canonical as $domain) {
                if ($viewerDomain === $domain || str_ends_with($viewerDomain, '.'.$domain)) {
                    return Verdict::pass('access.institution-domain');
                }
            }
        }

        $ip = $facts->has(AccessFacts::VIEWER_IP) ? $facts->get(AccessFacts::VIEWER_IP) : null;

        if (\is_string($ip) && IpUtils::checkIp($ip, $ranges)) {
            return Verdict::pass('access.institution-network');
        }

        return Verdict::fail('access.institution-none');
    }

    /**
     * Whether the text is an IPv4 or IPv6 address, with an optional prefix length within the bits of its family.
     */
    public static function isNetwork(string $network): bool
    {
        $parts = explode('/', $network);

        if (\count($parts) > 2) {
            return false;
        }

        if (false === filter_var($parts[0], \FILTER_VALIDATE_IP)) {
            return false;
        }

        $bits = false === filter_var($parts[0], \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4) ? 128 : 32;

        if (!isset($parts[1])) {
            return true;
        }

        return 1 === preg_match('/^(?:0|[1-9][0-9]{0,2})$/D', $parts[1]) && (int) $parts[1] <= $bits;
    }
}

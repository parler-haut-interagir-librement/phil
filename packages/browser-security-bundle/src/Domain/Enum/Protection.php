<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Domain\Enum;

use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;

/**
 * Everything a page may adjust beside its content security policy: the response headers, by their name, and three
 * protections that are not headers.
 */
enum Protection: string
{
    case PermissionsPolicy = 'Permissions-Policy';
    case ReferrerPolicy = 'Referrer-Policy';
    case FrameOptions = 'X-Frame-Options';
    case ContentTypeOptions = 'X-Content-Type-Options';
    case StrictTransportSecurity = 'Strict-Transport-Security';
    case OpenerPolicy = 'Cross-Origin-Opener-Policy';
    case OpenerPolicyReportOnly = 'Cross-Origin-Opener-Policy-Report-Only';
    case EmbedderPolicy = 'Cross-Origin-Embedder-Policy';
    case EmbedderPolicyReportOnly = 'Cross-Origin-Embedder-Policy-Report-Only';
    case ResourcePolicy = 'Cross-Origin-Resource-Policy';
    case PermittedCrossDomainPolicies = 'X-Permitted-Cross-Domain-Policies';
    case OriginAgentCluster = 'Origin-Agent-Cluster';
    case IntegrityPolicy = 'Integrity-Policy';
    case IntegrityPolicyReportOnly = 'Integrity-Policy-Report-Only';
    case ClearSiteData = 'Clear-Site-Data';
    case SignedCookies = 'signed-cookies';
    case ExternalRedirects = 'external-redirects';
    case HttpsRedirect = 'https-redirect';

    private const array OPENER = ['same-origin', 'same-origin-allow-popups', 'noopener-allow-popups', 'unsafe-none'];

    private const array EMBEDDER = ['require-corp', 'credentialless', 'unsafe-none'];

    /**
     * @throws InvalidPolicy when nothing has that name
     */
    public static function named(string $name): self
    {
        return self::tryFrom($name) ?? throw new InvalidPolicy(\sprintf('"%s" is not something a page can adjust: %s.', $name, implode(', ', array_column(self::cases(), 'value'))));
    }

    /**
     * The only values a header of one keyword takes; none for the others.
     *
     * @return list<string>
     */
    public function keywords(): array
    {
        return match ($this) {
            self::ReferrerPolicy => [
                'no-referrer', 'no-referrer-when-downgrade', 'origin', 'origin-when-cross-origin', 'same-origin',
                'strict-origin', 'strict-origin-when-cross-origin', 'unsafe-url',
            ],
            self::FrameOptions => ['DENY', 'SAMEORIGIN'],
            self::ContentTypeOptions => ['nosniff'],
            self::OpenerPolicy, self::OpenerPolicyReportOnly => self::OPENER,
            self::EmbedderPolicy, self::EmbedderPolicyReportOnly => self::EMBEDDER,
            self::ResourcePolicy => ['same-site', 'same-origin', 'cross-origin'],
            self::PermittedCrossDomainPolicies => ['none', 'master-only', 'by-content-type', 'by-ftp-filename', 'all', 'none-this-response'],
            self::OriginAgentCluster => ['?1', '?0'],
            default => [],
        };
    }

    /**
     * Whether a value is written as a response header.
     */
    public function isHeader(): bool
    {
        return !\in_array($this, [self::SignedCookies, self::ExternalRedirects, self::HttpsRedirect], true);
    }

    /**
     * Whether its value is a list or a map, which a page can add to.
     */
    public function isCollection(): bool
    {
        return [] === $this->keywords() && !\in_array($this, [self::StrictTransportSecurity, self::HttpsRedirect], true);
    }

    /**
     * Whether it acts before the controller is known, so that only a route can change it.
     */
    public function actsBeforeController(): bool
    {
        return \in_array($this, [self::SignedCookies, self::HttpsRedirect], true);
    }

    /**
     * Whether its header names the endpoint its reports go to.
     */
    public function reports(): bool
    {
        return \in_array($this, [self::OpenerPolicyReportOnly, self::EmbedderPolicyReportOnly, self::IntegrityPolicy, self::IntegrityPolicyReportOnly], true);
    }
}

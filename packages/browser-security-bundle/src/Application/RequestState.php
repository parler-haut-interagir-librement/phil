<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application;

use Phil\BrowserSecurityBundle\Domain\Enum\InlineKind;
use Phil\BrowserSecurityBundle\Domain\Enum\Protection;
use Phil\BrowserSecurityBundle\Domain\Syntax\ProtectionSyntax;
use Phil\BrowserSecurityBundle\Domain\Syntax\SourceSyntax;
use Symfony\Component\HttpFoundation\Request;

/**
 * What one request adds to its policy: the overrides of its route and controller, its nonces, created when a template
 * first asks for one, and the hashes of its inline code. It lives in the attributes of the main request and goes with
 * it: no service keeps it, so a worker never serves a request with another's nonce.
 *
 * @phpstan-import-type Value from ProtectionSyntax
 */
final class RequestState
{
    public const string ATTRIBUTE = '_phil_browser_security';

    /** @var list<PolicyOverride> */
    public private(set) array $overrides = [];

    /** @var list<ProtectionOverride> */
    public private(set) array $protectionOverrides = [];

    /** @var array<string, string> */
    private array $nonces = [];

    /** @var array<string, list<string>> */
    private array $hashes = [];

    /**
     * Whether the script nonce also allows styles: the `csp-nonce` meta tag gives one nonce to the scripts and the
     * styles that Turbo adds.
     */
    public private(set) bool $sharedNonce = false;

    /** @var array<string, string> */
    public array $headers = [];

    public ?string $policyName = null;

    /**
     * The state of a request, created on first use.
     */
    public static function of(Request $request): self
    {
        $state = $request->attributes->get(self::ATTRIBUTE);

        if (!$state instanceof self) {
            $state = new self();
            $request->attributes->set(self::ATTRIBUTE, $state);
        }

        return $state;
    }

    /**
     * A nonce of 128 random bits in base64, the same for every element of its kind in the response.
     */
    public function nonce(InlineKind $kind): string
    {
        return $this->nonces[$kind->value] ??= self::newNonce();
    }

    public function shareScriptNonce(): string
    {
        $this->sharedNonce = true;

        return $this->nonce(InlineKind::Script);
    }

    public static function newNonce(): string
    {
        return sodium_bin2base64(random_bytes(16), \SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    public function addHash(InlineKind $kind, string $content): void
    {
        $hash = SourceSyntax::hashOf($content);

        if (!\in_array($hash, $this->hashes[$kind->value] ?? [], true)) {
            $this->hashes[$kind->value][] = $hash;
        }
    }

    public function addOverride(PolicyOverride $override): void
    {
        $this->overrides[] = $override;
    }

    /**
     * Notes what a page changes in its protections, its content security policy included.
     */
    public function addProtectionOverride(ProtectionOverride $override): void
    {
        $this->protectionOverrides[] = $override;

        if ($override->policy instanceof PolicyOverride) {
            $this->addOverride($override->policy);
        }
    }

    /**
     * The value of a protection for this request.
     *
     * @param Value|null $value the configured value
     *
     * @return Value|null null when the protection does not apply
     */
    public function protection(Protection $protection, string|bool|array|null $value): string|bool|array|null
    {
        foreach ($this->protectionOverrides as $override) {
            $value = $override->apply($protection, $value);
        }

        return $value;
    }

    /**
     * The nonce and hash sources of a kind, in the order they go in the header.
     *
     * @return list<string>
     */
    public function sources(InlineKind $kind): array
    {
        $sources = isset($this->nonces[$kind->value]) ? ["'nonce-".$this->nonces[$kind->value]."'"] : [];

        if (InlineKind::Style === $kind && $this->sharedNonce) {
            $sources[] = "'nonce-".$this->nonce(InlineKind::Script)."'";
        }

        return [...$sources, ...$this->hashes[$kind->value] ?? []];
    }

    /**
     * @return array<string, string> the nonces created, by kind
     */
    public function nonces(): array
    {
        return $this->nonces;
    }
}

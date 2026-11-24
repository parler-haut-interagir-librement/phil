<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Check;

use Override;
use Phil\MailAuthCheck\Contract\Check;
use Phil\MailAuthCheck\Contract\TxtResolver;
use Phil\MailAuthCheck\Dkim\DkimKeyParser;
use Phil\MailAuthCheck\Dkim\DkimKeyType;
use Phil\MailAuthCheck\Exception\DnsLookupFailed;
use Phil\MailAuthCheck\Exception\InvalidRecord;
use Phil\MailAuthCheck\Finding;
use Phil\MailAuthCheck\SenderProfile;

/**
 * The DKIM key of each expected selector, at `selector._domainkey.` of the sending domain: one record, `v=DKIM1`, a
 * key that is not revoked, RSA of at least 1024 bits (a warning under 2048) or Ed25519.
 */
final readonly class DkimCheck implements Check
{
    public const string NAME = 'dkim';

    public const int MIN_RSA_BITS = 1024;

    public const int RECOMMENDED_RSA_BITS = 2048;

    public function __construct(
        private DkimKeyParser $parser = new DkimKeyParser(),
    ) {
    }

    #[Override]
    public function run(SenderProfile $profile, TxtResolver $dns): array
    {
        if ([] === $profile->dkimSelectors) {
            return [Finding::warning(self::NAME, 'dkim.no-selector', 'No DKIM selector is expected: DMARC then rests on SPF alignment alone, which forwarding breaks.')];
        }

        $findings = [];
        $onlyEd25519 = true;

        foreach ($profile->dkimSelectors as $selector) {
            [$finding, $type] = $this->selector($selector.'._domainkey.'.$profile->sendingDomain, $dns);
            $findings[] = $finding;
            $onlyEd25519 = $onlyEd25519 && DkimKeyType::Ed25519 === $type;
        }

        if ($onlyEd25519) {
            $findings[] = Finding::warning(self::NAME, 'dkim.ed25519-only', 'Every expected DKIM key is Ed25519, which not every receiver verifies yet: sign with an RSA key as well.');
        }

        return $findings;
    }

    /**
     * @return array{Finding, ?DkimKeyType} the finding, and the type of a key that passed
     */
    private function selector(string $name, TxtResolver $dns): array
    {
        try {
            $records = $dns->txt($name);
        } catch (DnsLookupFailed) {
            return [Finding::fail(self::NAME, 'dkim.lookup-failed', \sprintf('The DKIM key at %s could not be read: the DNS lookup failed.', $name)), null];
        }

        if ([] === $records) {
            return [Finding::fail(self::NAME, 'dkim.missing', \sprintf('No DKIM key is published at %s.', $name)), null];
        }

        if (\count($records) > 1) {
            return [Finding::fail(self::NAME, 'dkim.multiple', \sprintf('%s publishes more than one TXT record: keep the DKIM key alone.', $name)), null];
        }

        try {
            $key = $this->parser->parse($records[0]);
        } catch (InvalidRecord $invalidRecord) {
            return [Finding::fail(self::NAME, 'dkim.invalid', \sprintf('The DKIM key at %s cannot be read: %s', $name, $invalidRecord->getMessage())), null];
        }

        if ($key->revoked) {
            return [Finding::fail(self::NAME, 'dkim.revoked', \sprintf('The DKIM key at %s is revoked (empty "p="): signatures with it no longer verify.', $name)), null];
        }

        if ($key->testing) {
            return [Finding::warning(self::NAME, 'dkim.testing', \sprintf('The DKIM key at %s says it is being tested ("t=y"): receivers may treat its signatures as absent.', $name)), null];
        }

        if (DkimKeyType::Ed25519 === $key->type) {
            return [Finding::pass(self::NAME, 'dkim.ok', \sprintf('The DKIM key at %s is an Ed25519 key.', $name)), $key->type];
        }

        if ($key->bits < self::MIN_RSA_BITS) {
            return [Finding::fail(self::NAME, 'dkim.key-too-short', \sprintf('The DKIM key at %s is RSA of %d bits; receivers refuse keys under %d bits.', $name, $key->bits, self::MIN_RSA_BITS)), null];
        }

        if ($key->bits < self::RECOMMENDED_RSA_BITS) {
            return [Finding::warning(self::NAME, 'dkim.key-short', \sprintf('The DKIM key at %s is RSA of %d bits: move to %d bits.', $name, $key->bits, self::RECOMMENDED_RSA_BITS)), $key->type];
        }

        return [Finding::pass(self::NAME, 'dkim.ok', \sprintf('The DKIM key at %s is RSA of %d bits.', $name, $key->bits)), $key->type];
    }
}

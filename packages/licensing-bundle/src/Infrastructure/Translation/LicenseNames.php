<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Infrastructure\Translation;

use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\ValueObject\License;
use Phil\LicensingBundle\Domain\ValueObject\Violation;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The name of a license in the reader's language: `license.<code>` of the domain `phil_licensing` when it is
 * translated, else the name of the catalog. A license code the catalog does not hold is shown as it is. The messages
 * of the violations name their license the same way.
 */
final readonly class LicenseNames
{
    public const string DOMAIN = 'phil_licensing';

    public function __construct(
        private LicenseCatalog $catalog,
        private ?TranslatorInterface $translator = null,
    ) {
    }

    public function name(License|string $license, ?string $locale = null): string
    {
        $license = \is_string($license) ? ($this->catalog->find($license) ?? $license) : $license;

        if (\is_string($license)) {
            return $license;
        }

        $key = 'license.'.$license->code;
        $translated = $this->translator?->trans($key, [], self::DOMAIN, $locale) ?? $key;

        return $key === $translated ? $license->name : $translated;
    }

    /**
     * The message of a violation, the license named in the reader's language.
     */
    public function violation(Violation $violation, ?string $locale = null): string
    {
        return $this->trans($violation->code->messageKey(), [
            '%license%' => null === $violation->license ? '' : $this->name($violation->license, $locale),
            '%resource%' => $violation->resourceTitle ?? '',
        ], $locale);
    }

    /**
     * A message of the domain `phil_licensing`, or its key without a translator.
     *
     * @param array<string, string> $parameters
     */
    public function trans(string $key, array $parameters = [], ?string $locale = null): string
    {
        return $this->translator?->trans($key, $parameters, self::DOMAIN, $locale) ?? strtr($key, $parameters);
    }
}

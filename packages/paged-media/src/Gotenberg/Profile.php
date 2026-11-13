<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg;

use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;

/**
 * A standard Gotenberg repairs a PDF to, or checks it against: a PDF/A level for archiving, a PDF/UA level for
 * accessibility, or Factur-X for electronic invoices.
 */
enum Profile: string
{
    case PdfA1a = 'PDF/A-1a';
    case PdfA1b = 'PDF/A-1b';
    case PdfA2a = 'PDF/A-2a';
    case PdfA2b = 'PDF/A-2b';
    case PdfA2u = 'PDF/A-2u';
    case PdfA3a = 'PDF/A-3a';
    case PdfA3b = 'PDF/A-3b';
    case PdfA3u = 'PDF/A-3u';
    case PdfA4 = 'PDF/A-4';
    case PdfA4e = 'PDF/A-4e';
    case PdfA4f = 'PDF/A-4f';
    case PdfUa1 = 'PDF/UA-1';
    case PdfUa2 = 'PDF/UA-2';
    case FacturX = 'Factur-X';

    /**
     * Whether the profile is an accessibility one, PDF/UA.
     */
    public function isAccessibility(): bool
    {
        return self::PdfUa1 === $this || self::PdfUa2 === $this;
    }

    /**
     * The PDF/UA profile of the same PDF version, which this one pairs with: PDF/UA-1 for PDF/A-1 to PDF/A-3 and
     * Factur-X, PDF/UA-2 for PDF/A-4; none for a PDF/UA profile.
     */
    public function accessibilityPartner(): ?self
    {
        return match ($this) {
            self::PdfUa1, self::PdfUa2 => null,
            self::PdfA4, self::PdfA4e, self::PdfA4f => self::PdfUa2,
            default => self::PdfUa1,
        };
    }

    /**
     * One profile, or a PDF/A or Factur-X profile with the PDF/UA profile of its PDF version.
     *
     * @param self|list<self> $profiles
     *
     * @return non-empty-list<self>
     */
    public static function checked(self|array $profiles): array
    {
        $profiles = $profiles instanceof self ? [$profiles] : $profiles;

        $valid = match (\count($profiles)) {
            1 => true,
            2 => $profiles[0]->isAccessibility() ? $profiles[1]->accessibilityPartner() === $profiles[0] : $profiles[0]->accessibilityPartner() === $profiles[1],
            default => false,
        };

        if ($valid) {
            return $profiles;
        }

        throw InvalidStage::because(\sprintf('A profile is one standard, or a PDF/A or Factur-X profile with the PDF/UA profile of its PDF version: PDF/A-1 to PDF/A-3 and Factur-X with PDF/UA-1, PDF/A-4 with PDF/UA-2; "%s" is not.', implode(', ', array_map(static fn (self $profile): string => $profile->value, $profiles))));
    }
}

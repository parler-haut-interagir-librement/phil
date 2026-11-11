<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Translation;

use Override;
use Phil\EnumsBundle\Application\Translation\EnumTranslation;
use Phil\EnumsBundle\Infrastructure\Discovery\EnumFinder;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Gives `translation:extract` the keys of the translatable enums it finds: the label key and, when the case has
 * one, the description key of every case, in the enum's domain. Without it, the keys an enum translates by its
 * metadata appear in no catalogue, and `debug:translation` cannot say they are missing.
 *
 * The message of a new key is the key with the command's prefix, as Symfony's own extractors write it.
 */
final class EnumTranslationExtractor implements ExtractorInterface
{
    private string $prefix = '';

    #[Override]
    public function extract(string|iterable $resource, MessageCatalogue $catalogue): void
    {
        foreach (EnumFinder::find($resource) as $enum) {
            if (!is_subclass_of($enum, TranslatableInterface::class)) {
                continue;
            }

            foreach ($enum::cases() as $case) {
                $domain = EnumTranslation::domain($case);

                foreach ([EnumTranslation::labelKey($case), EnumTranslation::descriptionKey($case)] as $key) {
                    if (null !== $key) {
                        $catalogue->set($key, $this->prefix.$key, $domain);
                    }
                }
            }
        }
    }

    #[Override]
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }
}

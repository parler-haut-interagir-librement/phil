<?php

declare(strict_types=1);

namespace Phil\CreationDisclosure;

use Phil\CreationDisclosure\Exception\InvalidDisclosure;

/**
 * How a piece of media was made, as the IPTC Digital Source Type vocabulary names it: the value of
 * `Iptc4xmpExt:DigitalSourceType` in the XMP of an image, of a C2PA assertion, and of schema.org's `digitalSourceType`.
 *
 * The cases are the codes of the vocabulary, the retired ones included, so that metadata written earlier still reads;
 * a retired code is never declared anew, its `current()` replacement is.
 */
enum DigitalSourceType: string
{
    /** The address every code is appended to, as the vocabulary publishes it. */
    public const string URI = 'http://cv.iptc.org/newscodes/digitalsourcetype/';

    /** The XMP property that carries the address of a type. */
    public const string XMP_PROPERTY = 'Iptc4xmpExt:DigitalSourceType';

    case DigitalCapture = 'digitalCapture';
    case ComputationalCapture = 'computationalCapture';
    case NegativeFilm = 'negativeFilm';
    case PositiveFilm = 'positiveFilm';
    case Print = 'print';
    case HumanEdits = 'humanEdits';
    case CompositeWithTrainedAlgorithmicMedia = 'compositeWithTrainedAlgorithmicMedia';
    case AlgorithmicallyEnhanced = 'algorithmicallyEnhanced';
    case DigitalCreation = 'digitalCreation';
    case DataDrivenMedia = 'dataDrivenMedia';
    case TrainedAlgorithmicMedia = 'trainedAlgorithmicMedia';
    case AlgorithmicMedia = 'algorithmicMedia';
    case ScreenCapture = 'screenCapture';
    case VirtualRecording = 'virtualRecording';
    case Composite = 'composite';
    case CompositeCapture = 'compositeCapture';
    case CompositeSynthetic = 'compositeSynthetic';

    /** Retired by IPTC: `humanEdits` replaces it. */
    case MinorHumanEdits = 'minorHumanEdits';

    /** Retired by IPTC: `digitalCreation` replaces it. */
    case SoftwareImage = 'softwareImage';

    /** Retired by IPTC: `digitalCreation` replaces it. */
    case DigitalArt = 'digitalArt';

    /**
     * The type of a code, `trainedAlgorithmicMedia`.
     *
     * @throws InvalidDisclosure when the vocabulary has no such code
     */
    public static function fromCode(string $code): self
    {
        return self::tryFrom($code) ?? throw InvalidDisclosure::unknownCode($code);
    }

    /**
     * The type of an address of the vocabulary, over `http` or `https`, with or without a trailing slash.
     *
     * @throws InvalidDisclosure when the address is not one of the vocabulary
     */
    public static function fromUri(string $uri): self
    {
        $code = 1 === preg_match('{^https?://cv\.iptc\.org/newscodes/digitalsourcetype/([A-Za-z]{1,64})/?$}D', trim($uri), $matches) ? $matches[1] : null;

        return null === $code ? throw InvalidDisclosure::unknownUri($uri) : self::tryFrom($code) ?? throw InvalidDisclosure::unknownUri($uri);
    }

    /**
     * The types that may be declared: every code but the retired ones.
     *
     * @return list<self>
     */
    public static function declarable(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $type): bool => !$type->isRetired()));
    }

    /**
     * The address of the type, as XMP and C2PA carry it.
     */
    public function uri(): string
    {
        return self::URI.$this->value;
    }

    public function isRetired(): bool
    {
        return \in_array($this, [self::MinorHumanEdits, self::SoftwareImage, self::DigitalArt], true);
    }

    /**
     * The type that replaces a retired one; the type itself otherwise.
     */
    public function current(): self
    {
        return match ($this) {
            self::MinorHumanEdits => self::HumanEdits,
            self::SoftwareImage, self::DigitalArt => self::DigitalCreation,
            default => $this,
        };
    }

    /**
     * What the type says of generative AI. A composite of unstated elements and media altered by algorithms are
     * `Possible`: neither is AI-free.
     */
    public function generativeAi(): GenerativeAi
    {
        return match ($this) {
            self::TrainedAlgorithmicMedia => GenerativeAi::Full,
            self::CompositeWithTrainedAlgorithmicMedia, self::CompositeSynthetic => GenerativeAi::Partial,
            self::AlgorithmicallyEnhanced, self::Composite => GenerativeAi::Possible,
            default => GenerativeAi::None,
        };
    }

    /**
     * The English name the vocabulary gives the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::DigitalCapture => 'Digital capture sampled from real life',
            self::ComputationalCapture => 'Multi-frame computational capture sampled from real life',
            self::NegativeFilm => 'Digitised from a transparent negative',
            self::PositiveFilm => 'Digitised from a transparent positive',
            self::Print => 'Digitised from a non-transparent medium',
            self::HumanEdits => 'Human-edited media',
            self::CompositeWithTrainedAlgorithmicMedia => 'Edited using Generative AI',
            self::AlgorithmicallyEnhanced => 'Algorithmically-altered media',
            self::DigitalCreation => 'Digital creation',
            self::DataDrivenMedia => 'Data-driven media',
            self::TrainedAlgorithmicMedia => 'Created using Generative AI',
            self::AlgorithmicMedia => 'Pure algorithmic media',
            self::ScreenCapture => 'Screen capture',
            self::VirtualRecording => 'Virtual event recording',
            self::Composite => 'Composite of elements',
            self::CompositeCapture => 'Composite of captured elements',
            self::CompositeSynthetic => 'Composite including generative AI elements',
            self::MinorHumanEdits => 'Original media with minor human edits',
            self::SoftwareImage => 'Created by software',
            self::DigitalArt => 'Digital art',
        };
    }
}

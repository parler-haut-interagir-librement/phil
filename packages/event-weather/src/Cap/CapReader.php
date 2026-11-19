<?php

declare(strict_types=1);

namespace Phil\EventWeather\Cap;

use DateTimeImmutable;
use Dom\Element;
use Dom\XMLDocument;
use Exception;
use Phil\EventWeather\AlertSeverity;
use Phil\EventWeather\Exception\InvalidCapDocument;
use Phil\EventWeather\Internal\Data;
use Phil\EventWeather\WeatherAlert;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * Reads a Common Alerting Protocol 1.2 alert, the format in which Environment Canada and most national weather
 * services publish their warnings: one `WeatherAlert` per `<info>` block, in the language asked for or in all.
 * Only an actual alert is read: a test, an exercise, a system message, a draft and a cancellation give no alert.
 *
 * The document is UTF-8, read without the network, refused with a document type — which is how entity expansion attacks
 * arrive —, and bounded in bytes and in `<info>` blocks.
 */
final readonly class CapReader
{
    public const string CAP_NAMESPACE = 'urn:oasis:names:tc:emergency:cap:1.2';

    /**
     * @param ?string $language a language of RFC 3066, `fr` taking `fr-CA`; null for every language
     */
    public function __construct(
        private ?string $language = null,
        private int $maxBytes = 1_048_576,
        private int $maxInfos = 64,
    ) {
    }

    /**
     * @return list<WeatherAlert>
     *
     * @throws InvalidCapDocument when the document is not a CAP 1.2 alert within the limits
     */
    public function read(string $xml): array
    {
        $alert = $this->root($xml);
        $identifier = $this->required($alert, 'identifier');
        $sender = $this->required($alert, 'sender');
        $sent = Data::instant($this->required($alert, 'sent'));

        if (!$sent instanceof DateTimeImmutable) {
            throw new InvalidCapDocument('The "sent" of a CAP alert is an instant with its offset.');
        }

        if ('Actual' !== $this->required($alert, 'status') || 'Cancel' === $this->required($alert, 'msgType')) {
            return [];
        }

        $infos = self::children($alert, 'info');

        if (\count($infos) > $this->maxInfos) {
            throw new InvalidCapDocument(\sprintf('A CAP alert holds more than %d info blocks.', $this->maxInfos));
        }

        $alerts = [];

        foreach ($infos as $info) {
            $language = self::text($info, 'language') ?? 'en-US';

            if (null === $this->language || 0 === stripos($language.'-', $this->language.'-')) {
                $alerts[] = $this->alert($identifier, $sender, $sent, $info, $language);
            }
        }

        return $alerts;
    }

    private function root(string $xml): Element
    {
        if (\strlen($xml) > $this->maxBytes) {
            throw new InvalidCapDocument(\sprintf('A CAP alert is %d bytes at most.', $this->maxBytes));
        }

        if (!mb_check_encoding($xml, 'UTF-8') || 1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $xml) || 1 === preg_match('/^\s*<\?xml[^>]*encoding\s*=\s*["\'](?!utf-8["\'])/i', $xml)) {
            throw new InvalidCapDocument('A CAP alert is written in UTF-8.');
        }

        if (1 === preg_match('/<!DOCTYPE/i', $xml)) {
            throw new InvalidCapDocument('A CAP alert carries no document type.');
        }

        try {
            $document = XMLDocument::createFromString($xml, \LIBXML_NONET | \LIBXML_NOERROR | \LIBXML_NOWARNING);
        } catch (Exception $exception) {
            throw new InvalidCapDocument('The document is not XML.', 0, $exception);
        }

        $root = $document->documentElement;

        if (!$root instanceof Element || 'alert' !== $root->localName || self::CAP_NAMESPACE !== $root->namespaceURI) {
            throw new InvalidCapDocument('The document is not a CAP 1.2 alert.');
        }

        return $root;
    }

    private function alert(string $identifier, string $sender, DateTimeImmutable $sent, Element $info, string $language): WeatherAlert
    {
        $event = self::text($info, 'event');

        if (null === $event) {
            throw new InvalidCapDocument('Each info block of a CAP alert names its "event".');
        }

        $areas = array_values(array_filter(array_map(static fn (Element $area): ?string => self::text($area, 'areaDesc'), self::children($info, 'area')), static fn (?string $area): bool => null !== $area));

        return new WeatherAlert(
            $identifier.'|'.$language,
            $event,
            AlertSeverity::tryFrom(self::text($info, 'severity') ?? '') ?? AlertSeverity::Unknown,
            self::text($info, 'senderName') ?? $sender,
            Data::instant(self::text($info, 'onset')) ?? Data::instant(self::text($info, 'effective')) ?? $sent,
            Data::instant(self::text($info, 'expires')),
            self::text($info, 'headline'),
            self::text($info, 'description'),
            self::text($info, 'instruction'),
            [] === $areas ? null : implode('; ', $areas),
            $language,
            $this->url(self::text($info, 'web')),
        );
    }

    /**
     * @return list<Element>
     */
    private static function children(Element $parent, string $name): array
    {
        $children = [];

        foreach ($parent->children as $child) {
            if ($name === $child->localName && self::CAP_NAMESPACE === $child->namespaceURI) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private static function text(Element $parent, string $name): ?string
    {
        $child = self::children($parent, $name)[0] ?? null;

        return null === $child ? null : Data::string(trim((string) $child->textContent));
    }

    private function required(Element $parent, string $name): string
    {
        return self::text($parent, $name) ?? throw new InvalidCapDocument(\sprintf('A CAP alert names its "%s".', $name));
    }

    private function url(?string $text): ?Url
    {
        try {
            return null === $text ? null : Url::fromString($text);
        } catch (InvalidUrl) {
            return null;
        }
    }
}

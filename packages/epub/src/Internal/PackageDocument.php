<?php

declare(strict_types=1);

namespace Phil\Epub\Internal;

use DateTimeZone;
use Phil\Epub\Accessibility\AccessibilityFeature;
use Phil\Epub\Accessibility\AccessMode;
use Phil\Epub\Asset;
use Phil\Epub\AssetKind;
use Phil\Epub\Creator;
use Phil\Epub\Direction;
use Phil\Epub\Identifier;
use Phil\Epub\Layout;
use Phil\Epub\Metadata;
use Phil\Epub\Narration;
use Phil\Epub\Orientation;
use Phil\Epub\Rendition;
use Phil\Epub\Spread;

/**
 * The package document, `package.opf`: the metadata — with the layout of a fixed-layout book and the durations of its
 * media overlays —, the manifest of every file and the spine of the reading order.
 *
 * @internal
 */
final class PackageDocument
{
    /**
     * @param list<ManifestItem>         $items     in manifest order; those with a `linear` value form the spine, in that order
     * @param list<AccessMode>           $modes     the access modes the content shows, added to those declared
     * @param list<AccessibilityFeature> $features  the features the writer made, added unless none is declared
     * @param array<string, int>         $durations the duration of each media overlay in milliseconds, by its identifier
     */
    public static function write(Metadata $metadata, array $items, array $modes, array $features, Rendition $rendition = new Rendition(), Narration $narration = new Narration(), array $durations = [], bool $ncx = false): string
    {
        $direction = Direction::Default === $metadata->direction ? '' : ' dir="'.$metadata->direction->value.'"';
        $manifest = '';
        $spine = '';

        foreach ($items as $item) {
            $manifest .= '    <item id="'.$item->id.'" href="'.XhtmlSerialization::attribute($item->href).'" media-type="'.$item->mediaType.'"'
                .(null === $item->fallback ? '' : ' fallback="'.$item->fallback.'"')
                .(null === $item->mediaOverlay ? '' : ' media-overlay="'.$item->mediaOverlay.'"')
                .([] === $item->properties ? '' : ' properties="'.implode(' ', $item->properties).'"').'/>'."\n";

            if (null !== $item->linear) {
                $spine .= '    <itemref idref="'.$item->id.'"'.($item->linear ? '' : ' linear="no"')
                    .([] === $item->spineProperties ? '' : ' properties="'.implode(' ', $item->spineProperties).'"').'/>'."\n";
            }
        }

        return Xml::DECLARATION
            .'<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="'.XhtmlSerialization::attribute($metadata->language).'"'.$direction.'>'."\n"
            .'  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">'."\n"
            .self::metadata($metadata, $modes, $features)
            .self::rendition($rendition)
            .self::overlays($narration, $durations)
            .'  </metadata>'."\n"
            .'  <manifest>'."\n".$manifest.'  </manifest>'."\n"
            .'  <spine'.($ncx ? ' toc="ncx"' : '').(Direction::Default === $metadata->direction ? '' : ' page-progression-direction="'.$metadata->direction->value.'"').'>'."\n".$spine.'  </spine>'."\n"
            .'</package>'."\n";
    }

    /**
     * @param list<AccessMode>           $modes
     * @param list<AccessibilityFeature> $features
     */
    private static function metadata(Metadata $metadata, array $modes, array $features): string
    {
        $xml = self::identifier('pub-id', $metadata->identifier);

        foreach ($metadata->otherIdentifiers as $index => $identifier) {
            $xml .= self::identifier('identifier-'.($index + 2), $identifier);
        }

        $xml .= self::element('dc:title', $metadata->title)
            .self::element('dc:language', $metadata->language)
            .self::creators('creator', $metadata->creators)
            .self::creators('contributor', $metadata->contributors)
            .self::element('dc:publisher', $metadata->publisher)
            .self::element('dc:date', $metadata->published)
            .self::element('dc:description', $metadata->description);

        foreach ($metadata->subjects as $subject) {
            $xml .= self::element('dc:subject', $subject);
        }

        $xml .= self::element('dc:rights', $metadata->rights)
            .self::element('dc:source', $metadata->source)
            .self::meta('dcterms:modified', $metadata->modified->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'));

        return $xml.self::accessibility($metadata, $modes, $features);
    }

    /**
     * The rendition properties that differ from the defaults: the layout, the orientation and the spread.
     */
    private static function rendition(Rendition $rendition): string
    {
        return (Layout::Reflowable === $rendition->layout ? '' : self::meta('rendition:layout', $rendition->layout->value))
            .(Orientation::Auto === $rendition->orientation ? '' : self::meta('rendition:orientation', $rendition->orientation->value))
            .(Spread::Auto === $rendition->spread ? '' : self::meta('rendition:spread', $rendition->spread->value));
    }

    /**
     * The duration of each media overlay and of all of them, their narrators and their classes.
     *
     * @param array<string, int> $durations
     */
    private static function overlays(Narration $narration, array $durations): string
    {
        if ([] === $durations) {
            return '';
        }

        $xml = self::meta('media:duration', Smil::clock(array_sum($durations)));

        foreach ($durations as $id => $duration) {
            $xml .= '    <meta property="media:duration" refines="#'.$id.'">'.Smil::clock($duration).'</meta>'."\n";
        }

        foreach ($narration->narrators as $narrator) {
            $xml .= self::meta('media:narrator', $narrator);
        }

        return $xml
            .self::meta('media:active-class', $narration->activeClass)
            .self::meta('media:playback-active-class', $narration->playbackActiveClass);
    }

    /**
     * @param list<AccessMode>           $found
     * @param list<AccessibilityFeature> $made
     */
    private static function accessibility(Metadata $metadata, array $found, array $made): string
    {
        $accessibility = $metadata->accessibility;
        $modes = array_map(static fn (AccessMode $mode): string => $mode->value, [...$accessibility->accessModes, AccessMode::Textual, ...$found]);
        $declared = $accessibility->features;
        $none = \in_array(AccessibilityFeature::None, $declared, true);
        $features = array_map(static fn (AccessibilityFeature $feature): string => $feature->value, $none ? $declared : [...$declared, AccessibilityFeature::TableOfContents, ...$made]);

        $xml = '';

        foreach (array_unique($modes) as $mode) {
            $xml .= self::meta('schema:accessMode', $mode);
        }

        foreach ($accessibility->sufficientModes as $set) {
            $xml .= self::meta('schema:accessModeSufficient', implode(',', array_map(static fn (AccessMode $mode): string => $mode->value, $set)));
        }

        foreach (array_unique($features) as $feature) {
            $xml .= self::meta('schema:accessibilityFeature', $feature);
        }

        foreach ($accessibility->hazards as $hazard) {
            $xml .= self::meta('schema:accessibilityHazard', $hazard->value);
        }

        return $xml
            .self::meta('schema:accessibilitySummary', $accessibility->summary)
            .self::meta('dcterms:conformsTo', $accessibility->conformsTo)
            .self::meta('a11y:certifiedBy', $accessibility->certifiedBy);
    }

    private static function identifier(string $id, Identifier $identifier): string
    {
        $code = $identifier->type->onixCode();

        return '    <dc:identifier id="'.$id.'">'.XhtmlSerialization::text($identifier->value).'</dc:identifier>'."\n"
            .(null === $code ? '' : '    <meta refines="#'.$id.'" property="identifier-type" scheme="onix:codelist5">'.$code.'</meta>'."\n");
    }

    /**
     * @param list<Creator> $creators
     */
    private static function creators(string $element, array $creators): string
    {
        $xml = '';

        foreach ($creators as $index => $creator) {
            $id = $element.'-'.($index + 1);
            $xml .= '    <dc:'.$element.' id="'.$id.'">'.XhtmlSerialization::text($creator->name).'</dc:'.$element.'>'."\n";

            if (null !== $creator->role) {
                $xml .= '    <meta refines="#'.$id.'" property="role" scheme="marc:relators">'.$creator->role.'</meta>'."\n";
            }

            if (null !== $creator->fileAs) {
                $xml .= '    <meta refines="#'.$id.'" property="file-as">'.XhtmlSerialization::text($creator->fileAs).'</meta>'."\n";
            }
        }

        return $xml;
    }

    private static function element(string $name, ?string $value): string
    {
        return null === $value ? '' : '    <'.$name.'>'.XhtmlSerialization::text($value).'</'.$name.'>'."\n";
    }

    private static function meta(string $property, ?string $value): string
    {
        return null === $value ? '' : '    <meta property="'.$property.'">'.XhtmlSerialization::text($value).'</meta>'."\n";
    }

    /**
     * The access modes the files of the book show: visual for images and video, auditory for audio and video.
     *
     * @param array<string, Asset> $assets
     *
     * @return list<AccessMode>
     */
    public static function modes(array $assets): array
    {
        $kinds = array_map(static fn (Asset $asset): AssetKind => $asset->kind, $assets);
        $modes = [];

        if (\in_array(AssetKind::Image, $kinds, true) || \in_array(AssetKind::Video, $kinds, true)) {
            $modes[] = AccessMode::Visual;
        }

        if (\in_array(AssetKind::Audio, $kinds, true) || \in_array(AssetKind::Video, $kinds, true)) {
            $modes[] = AccessMode::Auditory;
        }

        return $modes;
    }
}

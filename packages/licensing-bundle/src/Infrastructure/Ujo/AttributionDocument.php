<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Infrastructure\Ujo;

use Phil\LicensingBundle\Application\AttributedResource;
use Phil\LicensingBundle\Application\AttributionPage;
use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\LicensingBundle\Domain\ValueObject\License;
use Phil\LicensingBundle\Infrastructure\Translation\LicenseNames;
use Phil\Ujo\Block;
use Phil\Ujo\Document;

/**
 * The license and attribution page of a work as a UJO (Universal JSON Output) document, for a book laid out from
 * blocks: headers, paragraphs and lists, with the same texts as the HTML fragment. Every text is escaped; the
 * addresses, http or https only, are links.
 */
final readonly class AttributionDocument
{
    public function __construct(
        private LicenseNames $names,
    ) {
    }

    public function of(AttributionPage $page, ?string $locale = null): Document
    {
        $blocks = [$this->header('page.license', $locale)];

        if (!$page->license instanceof License) {
            $blocks[] = $this->paragraph($this->escape($this->names->trans('page.unknown_license', ['%license%' => $page->licenseCode], $locale)));
        } else {
            $blocks[] = $this->paragraph(trim($this->escape($this->names->trans('page.licensed_under', ['%license%' => $this->names->name($page->license, $locale)], $locale)).' '.$this->link($page->license->url)));

            if (null !== $page->license->reference) {
                $blocks[] = $this->paragraph($this->escape($this->names->trans('page.reference', ['%reference%' => $page->license->reference], $locale)));
            }
        }

        foreach (['page.designated_content' => $page->designatedContent, 'page.reserved_material' => $page->reservedMaterial, 'page.product_identity' => $page->productIdentity] as $heading => $text) {
            if (null !== $text) {
                $blocks[] = $this->header($heading, $locale, 3);
                $blocks[] = $this->paragraph($this->lines($text));
            }
        }

        if ([] !== $page->resources) {
            $blocks[] = $this->header('page.attributions', $locale);

            foreach (ResourceKind::cases() as $kind) {
                $resources = $page->resourcesOf($kind);

                if ([] !== $resources) {
                    $blocks[] = $this->header('kind.'.$kind->value, $locale, 3);
                    $blocks[] = $this->block('list', ['style' => 'unordered', 'meta' => [], 'items' => array_map(fn (AttributedResource $resource): array => ['content' => $this->resource($resource, $locale), 'meta' => [], 'items' => []], $resources)]);
                }
            }
        }

        if ([] !== $page->notices) {
            $blocks[] = $this->header('page.notices', $locale);

            foreach ($page->notices as $notice) {
                $blocks[] = $this->paragraph($this->lines($notice));
            }
        }

        if (null !== $page->licenseText) {
            $blocks[] = $this->header('page.license_text', $locale);

            foreach ($this->split($page->licenseText) as $line) {
                if ('' !== trim($line)) {
                    $blocks[] = $this->paragraph($this->escape(trim($line)));
                }
            }
        }

        return new Document(array_map(static fn (Block $block, int $index): Block => new Block('phil-licensing-'.($index + 1), $block->type, $block->data), $blocks, array_keys($blocks)));
    }

    private function resource(AttributedResource $resource, ?string $locale): string
    {
        $attribution = $resource->attribution;
        $title = null === $attribution->url ? $this->escape($attribution->title) : $this->anchor($attribution->url, $attribution->title);
        $author = null === $attribution->author ? '' : ', '.$this->escape($this->names->trans('resource.by', ['%author%' => $attribution->author], $locale));
        $license = $this->escape($this->names->trans('resource.license', ['%license%' => $this->names->name($attribution->license, $locale)], $locale));
        $html = $title.$author.'. '.trim($license.' '.$this->link($resource->license?->url));

        if (null !== $attribution->changes) {
            $html .= '<br>'.$this->escape($this->names->trans('resource.changes', ['%changes%' => $attribution->changes], $locale));
        }

        return $html;
    }

    private function header(string $key, ?string $locale, int $level = 2): Block
    {
        return $this->block('header', ['text' => $this->escape($this->names->trans($key, [], $locale)), 'level' => $level]);
    }

    private function paragraph(string $html): Block
    {
        return $this->block('paragraph', ['text' => $html]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function block(string $type, array $data): Block
    {
        return new Block('phil-licensing', $type, $data);
    }

    private function link(?string $url): string
    {
        return null === $url ? '' : $this->anchor($url, $url);
    }

    private function anchor(string $url, string $text): string
    {
        return '<a href="'.$this->escape($url).'">'.$this->escape($text).'</a>';
    }

    private function lines(string $text): string
    {
        return implode('<br>', array_map($this->escape(...), $this->split($text)));
    }

    /**
     * @return list<string>
     */
    private function split(string $text): array
    {
        return explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}

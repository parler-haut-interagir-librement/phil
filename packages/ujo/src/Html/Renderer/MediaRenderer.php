<?php

declare(strict_types=1);

namespace Phil\Ujo\Html\Renderer;

use Override;
use Phil\Ujo\Block;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\BlockRenderer;
use Phil\Ujo\Html\Rendering;

/**
 * The blocks that show or point to something else: images, galleries, frames of embedded services, videos, sounds,
 * link previews, attached files, people, buttons, charts and QR codes. Images load lazily; a frame is sandboxed and
 * sends only its origin as referrer; a link preview's link is `nofollow`. A frame, a video and a sound are followed
 * by their fallback for paper, `.ujo-print-fallback`, which a screen style hides: the poster, the title and the
 * address, and a QR code of the address when the block asks for one.
 */
final readonly class MediaRenderer implements BlockRenderer
{
    public const array TYPES = ['image', 'gallery', 'embed', 'video', 'audio', 'linkTool', 'attaches', 'personality', 'button', 'chart', 'qrCode'];

    #[Override]
    public function render(Block $block, Rendering $rendering): string
    {
        $data = $block->data;

        return match ($block->type) {
            'image' => $this->image($block, $rendering),
            'gallery' => $rendering->element('div', $block, $this->gallery($data['files'] ?? null, $rendering), [], ['ujo-gallery--'.Rendering::text($data['style'] ?? 'fit')]),
            'embed' => $rendering->element('figure', $block, Rendering::tag('iframe', [
                'src' => \is_string($data['embed'] ?? null) ? $data['embed'] : null,
                'title' => Rendering::text($data['service'] ?? null),
                'width' => \is_int($data['width'] ?? null) ? $data['width'] : null,
                'height' => \is_int($data['height'] ?? null) ? $data['height'] : null,
                'loading' => 'lazy',
                'allowfullscreen' => true,
                'sandbox' => 'allow-scripts allow-same-origin allow-popups allow-presentation',
                'referrerpolicy' => 'strict-origin-when-cross-origin',
            ]).$this->fallback($data, $data['source'] ?? null).TextRenderer::caption($rendering->inline($data['caption'] ?? null)), [], ['ujo-embed--'.Rendering::text($data['service'] ?? null)]),
            'video', 'audio' => $this->player($block, $rendering),
            'linkTool' => $this->linkTool($block, $rendering),
            'attaches' => $this->attaches($block, $rendering),
            'personality' => $this->personality($block, $rendering),
            'button' => $rendering->element('a', $block, Rendering::text($data['text'] ?? null), ['href' => $this->url($data['link'] ?? null)]),
            'chart' => $rendering->element('div', $block, '', ['data-chart' => json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)]),
            'qrCode' => $rendering->element('div', $block, Rendering::text($data['data'] ?? null), ['data-qr-code' => $this->url($data['data'] ?? null)]),
            default => throw UnknownBlockType::renderer($block->type, 'HTML'),
        };
    }

    private function player(Block $block, Rendering $rendering): string
    {
        $data = $block->data;
        $sources = Rendering::rows($data['sources'] ?? null);
        $html = '';

        foreach ($sources as $source) {
            $html .= Rendering::tag('source', ['src' => $this->url($source['url'] ?? null), 'type' => \is_string($source['type'] ?? null) ? $source['type'] : null]);
        }

        foreach (Rendering::rows($data['tracks'] ?? null) as $track) {
            $html .= Rendering::tag('track', [
                'kind' => \is_string($track['kind'] ?? null) ? $track['kind'] : null,
                'src' => $this->url($track['url'] ?? null),
                'srclang' => \is_string($track['language'] ?? null) ? $track['language'] : null,
                'label' => \is_string($track['label'] ?? null) ? $track['label'] : null,
                'default' => true === ($track['default'] ?? false),
            ]);
        }

        $player = Rendering::tag($block->type, [
            'controls' => false !== ($data['controls'] ?? true),
            'loop' => true === ($data['loop'] ?? false),
            'muted' => true === ($data['muted'] ?? false),
            'poster' => $this->url($data['poster'] ?? null),
            'width' => \is_int($data['width'] ?? null) ? $data['width'] : null,
            'height' => \is_int($data['height'] ?? null) ? $data['height'] : null,
            'preload' => 'metadata',
            'title' => \is_string($data['title'] ?? null) ? $data['title'] : null,
        ], $html);

        return $rendering->element('figure', $block, $player.$this->fallback($data, $sources[0]['url'] ?? null).TextRenderer::caption($rendering->inline($data['caption'] ?? null)));
    }

    /**
     * What a frame, a video or a sound shows on paper: its poster, its title and its address, and a QR code of the
     * address when the block asks for one.
     *
     * @param array<array-key, mixed> $data
     */
    private function fallback(array $data, mixed $address): string
    {
        $url = $this->url($address);
        $poster = null === $this->url($data['poster'] ?? null) ? '' : $this->img($data['poster'], '', 'ujo-print-fallback__poster');

        return Rendering::tag('a', ['class' => 'ujo-print-fallback', 'href' => $url], $poster
            .TextRenderer::optional('span', 'ujo-print-fallback__title', Rendering::text($data['title'] ?? null))
            .'<span class="ujo-print-fallback__url">'.Rendering::text($url).'</span>')
            .(true === ($data['qrCode'] ?? false) && null !== $url ? Rendering::tag('div', ['class' => 'ujo-print-fallback ujo-qr-code', 'data-qr-code' => $url], Rendering::text($url)) : '');
    }

    private function image(Block $block, Rendering $rendering): string
    {
        $data = $block->data;
        $unsplash = Rendering::array($data['unsplash'] ?? null);
        $credit = [] === $unsplash ? '' : ' <small class="ujo-image__credit">'.Rendering::tag('a', ['href' => $this->url($unsplash['profileLink'] ?? null), 'rel' => 'noopener'], Rendering::text($unsplash['author'] ?? null)).'</small>';

        return $rendering->element('figure', $block, $this->img(Rendering::array($data['file'] ?? null)['url'] ?? null, \is_string($data['alt'] ?? null) ? $data['alt'] : $rendering->plain($data['caption'] ?? null)).TextRenderer::caption(ltrim($rendering->inline($data['caption'] ?? null).$credit)), [], array_keys(array_filter([
            'ujo-image--border' => true === ($data['withBorder'] ?? false),
            'ujo-image--background' => true === ($data['withBackground'] ?? false),
            'ujo-image--stretched' => true === ($data['stretched'] ?? false),
        ])));
    }

    private function gallery(mixed $files, Rendering $rendering): string
    {
        $html = '';

        foreach (Rendering::rows($files) as $file) {
            $html .= '<figure>'.$this->img($file['url'] ?? null, $rendering->plain($file['caption'] ?? null)).TextRenderer::caption($rendering->inline($file['caption'] ?? null)).'</figure>';
        }

        return $html;
    }

    private function linkTool(Block $block, Rendering $rendering): string
    {
        $link = $this->url($block->data['link'] ?? null);
        $meta = Rendering::array($block->data['meta'] ?? null);
        $image = Rendering::array($meta['image'] ?? null)['url'] ?? null;
        $title = Rendering::text($meta['title'] ?? null);

        return $rendering->element('a', $block, (null === $image ? '' : $this->img($image, '', 'ujo-link-tool__image'))
            .'<span class="ujo-link-tool__title">'.('' === $title ? Rendering::text($link) : $title).'</span>'
            .TextRenderer::optional('span', 'ujo-link-tool__description', Rendering::text($meta['description'] ?? null))
            .TextRenderer::optional('span', 'ujo-link-tool__site', Rendering::text($meta['site_name'] ?? null)), ['href' => $link, 'rel' => 'noopener nofollow']);
    }

    private function attaches(Block $block, Rendering $rendering): string
    {
        $file = Rendering::array($block->data['file'] ?? null);
        $label = array_find([$block->data['title'] ?? null, $file['name'] ?? null, $file['url'] ?? null], static fn (mixed $value): bool => \is_string($value) && '' !== $value);

        return $rendering->element('a', $block, Rendering::text($label), [
            'href' => $this->url($file['url'] ?? null),
            'download' => true,
            'data-size' => \is_int($file['size'] ?? null) ? $file['size'] : null,
            'data-extension' => \is_string($file['extension'] ?? null) ? $file['extension'] : null,
        ]);
    }

    private function personality(Block $block, Rendering $rendering): string
    {
        $data = $block->data;
        $name = Rendering::text($data['name'] ?? null);
        $link = $this->url($data['link'] ?? null);

        return $rendering->element('div', $block, (null === ($data['photo'] ?? null) ? '' : $this->img($data['photo'], '', 'ujo-personality__photo'))
            .'<p class="ujo-personality__name">'.(null === $link ? $name : Rendering::tag('a', ['href' => $link, 'rel' => 'noopener'], $name)).'</p>'
            .TextRenderer::optional('p', 'ujo-personality__description', Rendering::text($data['description'] ?? null)));
    }

    private function img(mixed $url, string $alt, ?string $class = null): string
    {
        return Rendering::tag('img', ['class' => $class, 'src' => $this->url($url), 'alt' => $alt, 'loading' => 'lazy']);
    }

    private function url(mixed $url): ?string
    {
        return \is_string($url) ? $url : null;
    }
}

<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Http;

use DateTimeImmutable;
use Phil\ContentBundle\Application\Publication\PublishedContent;
use Phil\ContentBundle\Application\Publication\PublishedContents;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\Locale;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The Atom feed of a kind of content in the request's locale: its latest published entries, each at its absolute
 * URL, with its title, summary and when it was published. A kind mounted on no route, or a locale not served, is a
 * `404`; the feed's title is the route's `title` default, or the kind.
 */
final readonly class FeedController
{
    public function __construct(
        private DeclaredLocales $locales,
        private ResourceRoutes $resourceRoutes,
        private PublishedContents $published,
        private ContentUrlGenerator $urls,
        private int $limit,
    ) {
    }

    public function __invoke(Request $request, string $resourceKey): Response
    {
        if (null === $this->resourceRoutes->routeOf($resourceKey)) {
            throw new NotFoundHttpException(\sprintf('No route serves the kind of content "%s".', $resourceKey));
        }

        try {
            $locale = Locale::fromString($request->getLocale());
            $this->locales->chainFor($locale);
        } catch (InvalidLocaleDimension $invalidLocaleDimension) {
            throw new NotFoundHttpException($invalidLocaleDimension->getMessage(), $invalidLocaleDimension);
        }

        $entries = '';
        $updated = null;

        $latest = $this->published->latest($resourceKey, $locale, $this->limit, true);
        $addresses = $this->urls->generateAll(array_map(static fn (PublishedContent $content): ContentReference => $content->reference, $latest), $locale, true);

        foreach ($latest as $content) {
            $url = $addresses[$content->reference->toString()] ?? null;

            if (null === $url) {
                continue;
            }

            $published = $content->publishedAt() ?? new DateTimeImmutable('@0');
            $updated = max($updated, $published);
            $summary = $content->summary();
            $entries .= '  <entry>'."\n"
                .'    <id>'.Xml::text($url).'</id>'."\n"
                .'    <title>'.Xml::text($content->title()).'</title>'."\n"
                .'    <link rel="alternate" href="'.Xml::text($url).'"/>'."\n"
                .'    <updated>'.$published->format(\DATE_ATOM).'</updated>'."\n"
                .(null === $summary ? '' : '    <summary>'.Xml::text($summary).'</summary>'."\n")
                .'  </entry>'."\n";
        }

        $self = $request->getUri();
        $title = $request->attributes->get('title');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="'.str_replace('_', '-', $locale->value).'">'."\n"
            .'  <id>'.Xml::text($self).'</id>'."\n"
            .'  <title>'.Xml::text(\is_string($title) ? $title : $resourceKey).'</title>'."\n"
            .'  <link rel="self" href="'.Xml::text($self).'"/>'."\n"
            .'  <updated>'.($updated ?? new DateTimeImmutable('@0'))->format(\DATE_ATOM).'</updated>'."\n"
            .$entries
            .'</feed>'."\n";

        return new Response($xml, Response::HTTP_OK, ['Content-Type' => 'application/atom+xml; charset=UTF-8']);
    }
}

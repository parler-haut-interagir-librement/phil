<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Infrastructure\Http;

use Phil\HttpCacheTagsBundle\Application\ResponseTags;
use Phil\SocialCardsBundle\Application\SocialCards;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `/social-cards/{fingerprint}.png`: the card of a page. The fingerprint changes with whatever the card shows, so the
 * answer is `public` and `immutable`, kept `$maxAge` seconds — a year by default — with the fingerprint as its `ETag`,
 * and a `304` to a request that holds it. With phil/http-cache-tags-bundle, it is tagged `social-card` and
 * `social-card:<fingerprint>`, for a purge of every card or of one.
 */
final readonly class SocialCardController
{
    public const string ROUTE = 'phil_social_cards_image';

    public const string TAG = 'social-card';

    public function __construct(
        private SocialCards $cards,
        private int $maxAge = 31_536_000,
        private ?ResponseTags $tags = null,
    ) {
    }

    public function __invoke(Request $request, string $fingerprint): Response
    {
        $png = $this->cards->image($fingerprint) ?? throw new NotFoundHttpException(\sprintf('No page has the card "%s".', $fingerprint));
        $response = new Response($png, Response::HTTP_OK, ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff']);
        $response->setEtag($fingerprint);
        $response->setPublic();
        $response->setMaxAge($this->maxAge);
        $response->setImmutable();
        $this->tags?->add(self::TAG, self::TAG.':'.$fingerprint);
        $response->isNotModified($request);

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Infrastructure\Twig\Components;

use Phil\FaqBundle\Application\FaqPages;
use Phil\FaqBundle\Application\FaqStructuredData;
use Phil\FaqBundle\Application\View\FaqPage;
use Phil\FaqBundle\Application\VoteTokens;
use Phil\FaqBundle\Domain\Exception\InvalidFaq;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Phil\FaqBundle\Infrastructure\Http\FaqController;
use Phil\FaqBundle\Infrastructure\Http\FaqGuard;
use Phil\FaqBundle\Infrastructure\Twig\KitComponents;
use Phil\StructuredData\JsonLd;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The questions on a page, `<twig:PhilFaq />`: a search form that filters on the server and, with the Stimulus
 * controller, as one types; each section under its heading; each question a `details` element whose `id` is its
 * anchor, with a link to share it and a "Was this useful?" form; the `FAQPage` JSON-LD of what it shows.
 *
 * The search, the language and the question just voted for are read from the request unless given.
 */
#[AsTwigComponent(name: 'PhilFaq', template: '@PhilFaq/components/Faq.html.twig', exposePublicProps: false)]
final class FaqComponent
{
    /** The page to show, already read by a controller; read from the request when null. */
    public ?FaqPage $page = null;

    /** The search; the `q` of the request when null. */
    public ?string $query = null;

    /** The language; the request's when null. */
    public ?string $locale = null;

    /** The address the search form is sent to; the current path when null. */
    public ?string $action = null;

    /** The level of the section headings, 2 under the `h1` of a page. */
    public int $headingLevel = 2;

    /** Whether the `FAQPage` JSON-LD is written. */
    public bool $structuredData = true;

    /** The nonce of the page's content security policy, for the JSON-LD script. */
    public ?string $nonce = null;

    private ?string $voteToken = null;

    public function __construct(
        private readonly FaqPages $pages,
        private readonly FaqStructuredData $structured,
        private readonly VoteTokens $tokens,
        private readonly FaqGuard $guard,
        private readonly KitComponents $kit,
        private readonly RequestStack $requests,
        private readonly UrlGeneratorInterface $urls,
        private readonly ?string $contactUrl,
    ) {
    }

    public function getFaq(): FaqPage
    {
        if (!$this->page instanceof FaqPage) {
            $request = $this->requests->getCurrentRequest();
            $this->page = $this->pages->page($this->language($request?->getLocale()), $this->query ?? $request?->query->getString('q') ?? '');
        }

        return $this->page;
    }

    /**
     * The vote token of this render, one for every question of the page.
     */
    public function getVoteToken(): string
    {
        return $this->voteToken ??= $this->tokens->issue();
    }

    public function getCsrfToken(): string
    {
        return $this->guard->token();
    }

    public function getContactUrl(): ?string
    {
        return $this->contactUrl;
    }

    public function getSearchAction(): string
    {
        return $this->action ?? $this->requests->getCurrentRequest()?->getPathInfo() ?? '/';
    }

    /**
     * The page a plain vote form comes back to: the current one, without the mark of an earlier vote.
     */
    public function getBack(): string
    {
        $request = $this->requests->getCurrentRequest();

        if (!$request instanceof Request) {
            return '/';
        }

        $query = $request->query->all();
        unset($query['voted']);

        return $request->getBaseUrl().$request->getPathInfo().([] === $query ? '' : '?'.http_build_query($query));
    }

    /**
     * The anchor of the question a plain form just voted for, to thank its reader.
     */
    public function getVoted(): string
    {
        return $this->requests->getCurrentRequest()?->query->getString('voted') ?? '';
    }

    public function getPageUrl(): string
    {
        $request = $this->requests->getCurrentRequest();

        return $request instanceof Request ? $request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo() : '';
    }

    public function voteUrl(string $anchor): string
    {
        return $this->urls->generate(FaqController::VOTE_ROUTE, ['anchor' => $anchor]);
    }

    public function getUnansweredUrl(): string
    {
        return $this->urls->generate(FaqController::UNANSWERED_ROUTE);
    }

    public function getLanguageTag(): string
    {
        return Locale::fromString($this->getFaq()->locale)->tag();
    }

    /**
     * The JSON-LD script of the questions shown, escaped so that it cannot close its element; empty when there is none.
     */
    public function getJsonLd(): string
    {
        $page = $this->getFaq();

        return $this->structuredData ? JsonLd::script($this->structured->graph($page, '' === $this->getPageUrl() ? null : $this->getPageUrl()), $this->nonce) : '';
    }

    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }

    private function language(?string $requested): Locale
    {
        try {
            return Locale::fromString($this->locale ?? $requested ?? 'en');
        } catch (InvalidFaq) {
            return Locale::fromString('en');
        }
    }
}

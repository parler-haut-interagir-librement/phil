<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Infrastructure\Twig\Components;

use Phil\CommentsBundle\Application\AuthorProvider;
use Phil\CommentsBundle\Application\CommentPolicy;
use Phil\CommentsBundle\Application\Comments;
use Phil\CommentsBundle\Application\Discussion;
use Phil\CommentsBundle\Application\Exception\CommentRefused;
use Phil\CommentsBundle\Infrastructure\Http\ClientConfig;
use Phil\CommentsBundle\Infrastructure\Http\DiscussionJson;
use Phil\CommentsBundle\Infrastructure\Http\PageForm;
use Phil\CommentsBundle\Infrastructure\Twig\KitComponents;
use Phil\Resource\ResourceReference;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The comments on a whole page, drawn by the server, `<twig:PhilComments:Page subject="article:42" />`: the threads on
 * the page, oldest first, each with its comments and the plain forms the viewer may use — reply, edit, delete, resolve,
 * reopen —, then the form that opens a thread. Everything works without JavaScript; the parts are recipes of the kit.
 * Threads on passages are left to the comments controller.
 */
#[AsTwigComponent(name: 'PhilComments:Page', template: '@PhilComments/components/Page.html.twig', exposePublicProps: false)]
final class PageCommentsComponent
{
    public string $subject = '';

    /** The page the forms come back to. */
    public string $back = '/';

    private ?ResourceReference $viewer = null;

    public function __construct(
        private readonly Comments $comments,
        private readonly CommentPolicy $policy,
        private readonly DiscussionJson $json,
        private readonly PageForm $form,
        private readonly ClientConfig $config,
        private readonly KitComponents $kit,
        private readonly RequestStack $requests,
        private readonly ?AuthorProvider $authors = null,
    ) {
    }

    public function mount(ResourceReference|string $subject): void
    {
        $this->subject = ($subject instanceof ResourceReference ? $subject : ResourceReference::fromString($subject))->toString();
        $this->back = $this->localPath($this->requests->getCurrentRequest()?->getRequestUri() ?? '/');
        $this->viewer = $this->authors?->author();
    }

    /**
     * Whether someone is signed in: nobody else reads or writes.
     */
    public function isSignedIn(): bool
    {
        return $this->viewer instanceof ResourceReference;
    }

    /**
     * Whether the viewer may read the threads of the page.
     */
    public function isReadable(): bool
    {
        return $this->viewer instanceof ResourceReference && $this->policy->mayRead($this->viewer, $this->reference());
    }

    /**
     * Whether the viewer may open a thread on the page.
     */
    public function mayComment(): bool
    {
        return $this->viewer instanceof ResourceReference && $this->policy->mayComment($this->viewer, $this->reference());
    }

    /**
     * The threads on the page as the endpoints answer them, with what the viewer may do; none for a viewer who may not
     * read them. Read once by the template.
     *
     * @return list<array<string, mixed>>
     */
    public function getThreads(): array
    {
        $viewer = $this->viewer;

        if (!$viewer instanceof ResourceReference) {
            return [];
        }

        try {
            $discussions = $this->comments->discussions($this->reference(), $viewer);
        } catch (CommentRefused) {
            return [];
        }

        return array_values(array_map(
            fn (Discussion $discussion): array => $this->json->of($discussion, $viewer),
            array_filter($discussions, static fn (Discussion $discussion): bool => $discussion->thread->isOnPage()),
        ));
    }

    public function getFormAction(): string
    {
        return $this->form->action($this->subject, $this->back);
    }

    public function getToken(): string
    {
        return $this->form->token();
    }

    public function getSection(): string
    {
        return PageForm::SECTION;
    }

    /**
     * Whether the roles of WAI-ARIA 1.3 are written, as the configuration says.
     */
    public function hasAriaRoles(): bool
    {
        return $this->config->hasAriaRoles();
    }

    /**
     * The address of the member search that gives the text areas their `@` autocomplete, `null` without it.
     */
    public function getMentions(): ?string
    {
        return $this->config->mentionSearch();
    }

    /**
     * The name of a recipe of the kit, where the application installed it.
     */
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }

    private function reference(): ResourceReference
    {
        return ResourceReference::fromString($this->subject);
    }

    /**
     * The page to come back to, on this site only: a request line such as `//other.example/x` would otherwise send the
     * visitor to another host after the form.
     */
    private function localPath(string $uri): string
    {
        return str_starts_with($uri, '/') && !str_starts_with($uri, '//') && !str_starts_with($uri, '/\\') ? $uri : '/';
    }
}

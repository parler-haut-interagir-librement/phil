<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Infrastructure\Twig\Components;

use Closure;
use Phil\ReactionsBundle\Application\Contract\CurrentMember;
use Phil\ReactionsBundle\Application\Exception\ReactionLimitReached;
use Phil\ReactionsBundle\Application\Exception\ReactionRefused;
use Phil\ReactionsBundle\Application\Exception\TooManyReactions;
use Phil\ReactionsBundle\Application\Exception\UnknownReaction;
use Phil\ReactionsBundle\Application\ReactionBoard;
use Phil\ReactionsBundle\Application\Reactions;
use Phil\ReactionsBundle\Application\View\ReactionPanel;
use Phil\ReactionsBundle\Infrastructure\Http\ReactionGuard;
use Phil\ReactionsBundle\Infrastructure\Twig\KitComponents;
use Phil\Resource\ResourceReference;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The reactions of a content on a page, `<twig:PhilReactions content="article:42" />`. Its props are signed, so a browser
 * cannot change the content it reacts to; the member is always the one of the request, and every change asks its CSRF
 * check again. Each button is a plain form's button the component takes over when it can.
 */
#[AsLiveComponent(name: 'PhilReactions', template: '@PhilReactions/components/Reactions.html.twig', exposePublicProps: false)]
final class ReactionsComponent
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    /** The browser event telling the other components of the same content to draw themselves again. */
    public const string CHANGED = 'phil-reactions-changed';

    #[LiveProp]
    public string $content = '';

    /** The page a plain form comes back to. */
    #[LiveProp]
    public string $path = '/';

    /** Tells this component from another one of the same content, at the start and the end of an article. */
    #[LiveProp]
    public string $instance = '';

    /** What the live region says after a change, for this render. */
    public string $notice = '';

    public function __construct(
        private readonly Reactions $reactions,
        private readonly ReactionBoard $board,
        private readonly CurrentMember $member,
        private readonly ReactionGuard $guard,
        private readonly KitComponents $kit,
        private readonly RequestStack $requests,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function mount(ResourceReference|string $content): void
    {
        $this->content = ($content instanceof ResourceReference ? $content : ResourceReference::fromString($content))->toString();
        $this->path = $this->requests->getCurrentRequest()?->getRequestUri() ?? '/';
        $this->instance = Uuid::v4()->toRfc4122();
    }

    public function getPanel(): ReactionPanel
    {
        return $this->board->panel(ResourceReference::fromString($this->content));
    }

    public function getToken(): string
    {
        return $this->guard->token();
    }

    public function getFormAction(): string
    {
        return $this->guard->action($this->content, $this->path);
    }

    /**
     * The language the labels are translated in, which the list of names is written in.
     */
    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }

    /**
     * The name of a recipe of the kit, where the application installed it.
     */
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }

    #[LiveAction]
    public function react(#[LiveArg] string $code, #[LiveArg] string $token): void
    {
        $this->change($token, 'reactions.added', fn (ResourceReference $content, string $member): bool => $this->reactions->react($content, $member, $code));
    }

    #[LiveAction]
    public function withdraw(#[LiveArg] string $token, #[LiveArg] string $code = ''): void
    {
        $this->change($token, 'reactions.removed', fn (ResourceReference $content, string $member): bool => $this->reactions->withdraw($content, $member, '' === $code ? null : $code));
    }

    /**
     * @param Closure(ResourceReference, string): bool $change
     */
    private function change(string $token, string $done, Closure $change): void
    {
        try {
            $this->guard->assertTrusted($token);
            $member = $this->member->current() ?? throw ReactionRefused::noMember();
            $content = ResourceReference::fromString($this->content);

            if ($change($content, $member)) {
                $this->board->forget($content);
                $this->dispatchBrowserEvent(self::CHANGED, ['content' => $this->content, 'instance' => $this->instance]);
            }

            $this->notice = $this->translator->trans($done, [], ReactionBoard::DOMAIN);
        } catch (ReactionRefused|UnknownReaction) {
            $this->notice = $this->translator->trans('reactions.refused', [], ReactionBoard::DOMAIN);
        } catch (TooManyReactions) {
            $this->notice = $this->translator->trans('reactions.too_many', [], ReactionBoard::DOMAIN);
        } catch (ReactionLimitReached) {
            $this->notice = $this->translator->trans('reactions.limit', [], ReactionBoard::DOMAIN);
        }
    }
}

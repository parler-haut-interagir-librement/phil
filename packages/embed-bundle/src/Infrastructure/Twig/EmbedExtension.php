<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Infrastructure\Twig;

use Phil\EmbedBundle\Application\EmbedCodes;
use Phil\EmbedBundle\Application\EmbedContext;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Phil\EmbedBundle\Infrastructure\Http\EmbedRequestListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * `phil_embed()`, the embed the page is answered for, if any; `phil_embed_frame_attributes()`, the attributes of the
 * `<html>` element that start the frame controller and set the theme, nothing outside an embed; `phil_embed_code()`,
 * the code another site pastes.
 */
final readonly class EmbedExtension
{
    public function __construct(
        private RequestStack $requests,
        private EmbedCodes $codes,
        private bool $allowLocalhost = false,
    ) {
    }

    #[AsTwigFunction(name: 'phil_embed')]
    public function context(): ?EmbedContext
    {
        $context = $this->requests->getMainRequest()?->attributes->get(EmbedRequestListener::ATTRIBUTE);

        return $context instanceof EmbedContext ? $context : null;
    }

    #[AsTwigFunction(name: 'phil_embed_frame_attributes', isSafe: ['html'])]
    public function frameAttributes(): string
    {
        $context = $this->context();

        if (!$context instanceof EmbedContext) {
            return '';
        }

        $attributes = [
            'data-controller' => 'phil--embed-bundle--frame',
            'data-phil--embed-bundle--frame-origin-value' => $context->origin,
            'data-phil--embed-bundle--frame-themes-value' => json_encode($context->themes, \JSON_THROW_ON_ERROR),
        ];

        if (null !== $context->theme) {
            $attributes['data-theme'] = $context->theme;
        }

        return implode(' ', array_map(static fn (string $name, string $value): string => $name.'="'.htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'"', array_keys($attributes), $attributes));
    }

    /**
     * @param array<string, scalar> $parameters
     */
    #[AsTwigFunction(name: 'phil_embed_code')]
    public function code(string $embed, string $origin, string $route, array $parameters, string $fallback, string $title, ?string $theme = null, ?string $subject = null): string
    {
        return $this->codes->snippet($embed, Origin::fromString($origin, $this->allowLocalhost), $route, $parameters, $fallback, $title, $theme, $subject);
    }
}

<?php

declare(strict_types=1);

namespace Phil\UxKit\Application\Contract;

/**
 * Gives the props of a `Picture` from an object of the application or of another package — a medium of a library, an
 * image of a catalogue —, so that a template writes `<twig:Picture {{ ...picture_props(medium) }} />` without knowing
 * where its files are. A service that implements it is tagged `phil_ux_kit.picture_props` when it is autoconfigured,
 * and `picture_props()` asks the providers in turn, the first that supports the object answering.
 *
 * The props are those that describe the image: its files, `src`, `srcset`, `sizes`, `sources`, `dark`, `still` and
 * `forced`; its size, `width` and `height`; its text, `alt`, `caption`, `description`, `credit`, `license`,
 * `licenseHref`, `creation` and `creationLabel`; its box, `aspect`, `fit` and `position`; and what shows while it loads,
 * `placeholder` and `preview`. Any other key is dropped: how the image loads and where it stands are the page's choice.
 */
interface PicturePropsProvider
{
    /**
     * Whether this provider gives the props of a picture for that object.
     */
    public function supports(object $subject): bool;

    /**
     * The props of a `Picture` for that object, which `supports()` accepted.
     *
     * @param array<string, mixed> $options what the template asks of the provider, such as the widths of a set
     *
     * @return array<string, mixed>
     */
    public function props(object $subject, array $options = []): array;
}

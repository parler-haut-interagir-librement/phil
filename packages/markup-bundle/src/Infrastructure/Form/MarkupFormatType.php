<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Infrastructure\Form;

use Override;
use Phil\MarkupBundle\Infrastructure\Format\MarkupFormats;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A choice of the format a text is written in, among the enabled ones, in their configured order: the model is the
 * name of the format. The labels — those of the built-in formats, and those add-ons give theirs — are translated in
 * the `phil_markup` domain.
 *
 * @extends AbstractType<string|null>
 */
final class MarkupFormatType extends AbstractType
{
    /** The label of each built-in format, by its name. */
    public const array LABELS = [
        'markdown' => 'Markdown',
        'html' => 'HTML',
        'bbcode' => 'BBCode',
        'mediawiki' => 'MediaWiki',
        'dokuwiki' => 'DokuWiki',
        'creole' => 'Creole',
        'text' => 'Plain text',
        'ujo' => 'Block editor (UJO)',
    ];

    public function __construct(
        private readonly MarkupFormats $formats,
    ) {
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => $this->formats->enabled(),
            'choice_label' => fn (string $format): string => self::LABELS[$format] ?? $this->formats->label($format) ?? $format,
            'choice_translation_domain' => 'phil_markup',
        ]);
    }

    #[Override]
    public function getParent(): string
    {
        return ChoiceType::class;
    }
}

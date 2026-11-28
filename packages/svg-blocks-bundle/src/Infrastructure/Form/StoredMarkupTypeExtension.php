<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle\Infrastructure\Form;

use Override;
use Phil\BlockEditorBundle\Infrastructure\Form\BlockEditorType;
use Phil\SvgBlocks\BlockMarkup;
use Phil\Ujo\Ujo;
use Phil\UjoBundle\Infrastructure\Form\UjoType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Draws the blocks of every document a UJO field receives — `phil/ujo-bundle`'s `UjoType`, and the block editor's
 * field when it is installed —, after the field has checked it; `svg_blocks: false` leaves a field's documents
 * as they are.
 */
final class StoredMarkupTypeExtension extends AbstractTypeExtension
{
    /** The block editor's field, extended when it is installed. */
    public const string BLOCK_EDITOR_TYPE = BlockEditorType::class;

    public function __construct(
        private readonly BlockMarkup $markup,
        private readonly Ujo $ujo,
    ) {
    }

    #[Override]
    public static function getExtendedTypes(): iterable
    {
        yield UjoType::class;

        if (class_exists(self::BLOCK_EDITOR_TYPE)) {
            yield self::BLOCK_EDITOR_TYPE;
        }
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (true === $options['svg_blocks']) {
            // Prepended, so that it runs after the field's own transformer when the submitted value goes to the model.
            $builder->addModelTransformer(new StoredMarkupTransformer($this->markup, $this->ujo), true);
        }
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('svg_blocks')
            ->default(true)
            ->allowedTypes('bool')
            ->info('Whether the math, mermaid, chart and qrCode blocks are drawn and their drawings kept when submitted.');
    }
}

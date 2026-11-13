<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Pdf\Form;

use Closure;
use Phil\PagedMedia\Pdf\Number;
use Phil\PagedMedia\Pdf\Objects;

/**
 * Writes a document's form fields once its fonts are written: their appearance streams as form XObjects, then either
 * their widget annotations and the catalog's interactive form, or, when the form is flattened, the drawing of each
 * appearance into its page.
 *
 * @internal
 */
final class FormWriter
{
    /** @var list<list<array<string, int>>> each appearance's object, by state, by widget, by field */
    private array $appearances = [];

    /** @var list<string> the references of the form's fields, in the order their pages are written */
    private array $form = [];

    /**
     * @param list<array{int, list<Widget>, string|null}> $fields each field's page, its widgets, and the entries of a radio group's parent
     * @param array<string, int>                          $fonts  each font's object, by resource name
     * @param Closure(string, string): string             $stream a stream object of the entries and data given
     */
    public function __construct(
        private readonly Objects $objects,
        private readonly array $fields,
        private readonly array $fonts,
        Closure $stream,
        private readonly bool $flatten,
    ) {
        foreach ($fields as [, $widgets]) {
            $byWidget = [];

            foreach ($widgets as $widget) {
                $resources = null === $widget->font ? '<< >>' : \sprintf('<< /Font << /%s %d 0 R >> >>', $widget->font, $fonts[$widget->font]);
                $states = $flatten ? [$widget->state ?? '' => $widget->shown()] : $widget->appearances;
                $byState = [];

                foreach ($states as $state => $content) {
                    $byState[$state] = $objects->add($stream(\sprintf('/Type /XObject /Subtype /Form /BBox [0 0 %s %s] /Resources %s ', Number::format($widget->width), Number::format($widget->height), $resources), $content));
                }

                $byWidget[] = $byState;
            }

            $this->appearances[] = $byWidget;
        }
    }

    /**
     * When the form is flattened, the XObject resources of the appearances and what each page draws of them.
     *
     * @return array{list<string>, array<int, string>} the resource entries, and the operators by page
     */
    public function flattened(): array
    {
        $resources = [];
        $drawn = [];

        if (!$this->flatten) {
            return [$resources, $drawn];
        }

        foreach ($this->fields as $index => [$page, $widgets]) {
            foreach ($widgets as $number => $widget) {
                $name = \sprintf('Fm%d_%d', $index + 1, $number + 1);
                $resources[] = \sprintf('/%s %d 0 R', $name, $this->appearances[$index][$number][$widget->state ?? '']);
                $drawn[$page] = ($drawn[$page] ?? '').\sprintf("q 1 0 0 1 %s %s cm /%s Do Q\n", Number::format($widget->x), Number::format($widget->y), $name);
            }
        }

        return [$resources, $drawn];
    }

    /**
     * Whether a page holds widget annotations: interactive fields placed on it.
     */
    public function annotates(int $page): bool
    {
        return !$this->flatten && \in_array($page, array_column($this->fields, 0), true);
    }

    /**
     * The widget annotations of a page's fields, and the parent of each radio group; each field joins the form.
     *
     * @param int $page   the page's index
     * @param int $object the page's object
     *
     * @return list<string> the references of the annotations
     */
    public function annotations(int $page, int $object): array
    {
        $annotations = [];

        foreach ($this->fields as $index => [$on, $widgets, $parent]) {
            if ($on !== $page) {
                continue;
            }

            $parentObject = null === $parent ? null : $this->objects->reserve();
            $kids = [];

            foreach ($widgets as $number => $widget) {
                $kids[] = $this->widget($widget, $this->appearances[$index][$number], $object, $parentObject).' 0 R';
            }

            if (null !== $parentObject) {
                $this->objects->set($parentObject, \sprintf('<< %s /Kids [%s] >>', $parent, implode(' ', $kids)));
            }

            $this->form[] = null === $parentObject ? $kids[0] : $parentObject.' 0 R';
            array_push($annotations, ...$kids);
        }

        return $annotations;
    }

    /**
     * The catalog's interactive form, with the fonts its fields type in; nothing when no field is interactive.
     */
    public function catalogEntry(): string
    {
        if ([] === $this->form) {
            return '';
        }

        $used = [];

        foreach ($this->fields as [, $widgets]) {
            foreach ($widgets as $widget) {
                if (null !== $widget->font) {
                    $used[$widget->font] = \sprintf('/%s %d 0 R', $widget->font, $this->fonts[$widget->font]);
                }
            }
        }

        ksort($used);

        return \sprintf(' /AcroForm << /Fields [%s]%s >>', implode(' ', $this->form), [] === $used ? '' : ' /DR << /Font << '.implode(' ', $used).' >> >>');
    }

    /**
     * @param array<int|string, int> $states each appearance's object, by state; a state named by a number is an integer key
     */
    private function widget(Widget $widget, array $states, int $page, ?int $parent): int
    {
        if (null === $widget->state) {
            $normal = \sprintf('%d 0 R', $states['']);
        } else {
            $named = [];

            foreach ($states as $state => $object) {
                $named[] = \sprintf('%s %d 0 R', Fields::name((string) $state), $object);
            }

            $normal = '<< '.implode(' ', $named).' >>';
        }

        return $this->objects->add(\sprintf(
            '<< /Type /Annot /Subtype /Widget %s /Rect [%s %s %s %s] /F 4 /P %d 0 R /AP << /N %s >>%s >>',
            trim((null === $parent ? '' : \sprintf('/Parent %d 0 R', $parent)).' '.trim($widget->entries)),
            Number::format($widget->x),
            Number::format($widget->y),
            Number::format($widget->x + $widget->width),
            Number::format($widget->y + $widget->height),
            $page,
            $normal,
            null === $widget->state ? '' : ' /AS '.Fields::name($widget->state),
        ));
    }
}

<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine;

use Phil\ContentBundle\Domain\Content\AbstractContentRoot;
use Phil\ContentBundle\Domain\Content\AbstractDimensionContent;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;

/**
 * The root classes the application declares (`phil_content.roots`), and the row class of each.
 *
 * Each root class names its row class (`AbstractContentRoot::dimensionContentClass()`), so declaring the roots
 * is enough: the mapping listener reads here which root a row class points to. One row class belongs to
 * one root class; two roots sharing a row class would make the target of its `root` association ambiguous.
 *
 * Each root also has a resource key (`AbstractContentRoot::resourceKey()`), the name routes, links and console
 * commands give its kind of content; two roots with one key are refused.
 */
final readonly class ContentRoots
{
    /**
     * @param list<class-string<AbstractContentRoot>>                                          $roots
     * @param array<class-string<AbstractDimensionContent>, class-string<AbstractContentRoot>> $rootOfDimension
     * @param array<string, class-string<AbstractContentRoot>>                                 $rootOfKey
     */
    private function __construct(
        public array $roots,
        private array $rootOfDimension,
        private array $rootOfKey,
    ) {
    }

    /**
     * @param array<mixed> $classes
     *
     * @throws InvalidContentMapping when a class is not a concrete content root, or two roots share a row class
     *                               or a resource key
     */
    public static function fromClassNames(array $classes): self
    {
        $roots = [];
        $rootOfDimension = [];
        $rootOfKey = [];

        foreach ($classes as $class) {
            if (!\is_string($class) || !is_subclass_of($class, AbstractContentRoot::class)) {
                throw InvalidContentMapping::because(\sprintf('"%s" is not a subclass of %s.', \is_string($class) ? $class : get_debug_type($class), AbstractContentRoot::class));
            }

            $dimension = $class::dimensionContentClass();

            if (isset($rootOfDimension[$dimension])) {
                throw InvalidContentMapping::because(\sprintf('The row class "%s" is named by two roots, "%s" and "%s".', $dimension, $rootOfDimension[$dimension], $class));
            }

            $key = $class::resourceKey();

            if (!ContentReference::isResourceKey($key)) {
                throw InvalidContentMapping::because(\sprintf('The resource key "%s" of "%s" is not a lowercase letter followed by at most 63 lowercase letters, digits or underscores.', $key, $class));
            }

            if (isset($rootOfKey[$key])) {
                throw InvalidContentMapping::because(\sprintf('The resource key "%s" is used by two roots, "%s" and "%s".', $key, $rootOfKey[$key], $class));
            }

            $roots[] = $class;
            $rootOfDimension[$dimension] = $class;
            $rootOfKey[$key] = $class;
        }

        return new self($roots, $rootOfDimension, $rootOfKey);
    }

    /**
     * @param class-string<AbstractDimensionContent> $dimension
     *
     * @return class-string<AbstractContentRoot>
     *
     * @throws InvalidContentMapping when no declared root names this row class
     */
    public function rootOf(string $dimension): string
    {
        return $this->rootOfDimension[$dimension] ?? throw InvalidContentMapping::because(\sprintf('The row class "%s" belongs to no root declared under "phil_content.roots".', $dimension));
    }

    /**
     * @return list<string>
     */
    public function resourceKeys(): array
    {
        return array_keys($this->rootOfKey);
    }

    /**
     * @return class-string<AbstractContentRoot>
     *
     * @throws InvalidContentMapping when no declared root has this resource key
     */
    public function classOf(string $resourceKey): string
    {
        return $this->rootOfKey[$resourceKey] ?? throw InvalidContentMapping::because(\sprintf('No root declared under "phil_content.roots" has the resource key "%s"; the keys are: %s.', $resourceKey, implode(', ', $this->resourceKeys())));
    }

    /**
     * The resource key of the declared root `$root` is an instance of.
     *
     * @throws InvalidContentMapping when `$root` is of no declared root class
     */
    public function resourceKeyOf(object $root): string
    {
        foreach ($this->rootOfKey as $key => $class) {
            if ($root instanceof $class) {
                return $key;
            }
        }

        throw InvalidContentMapping::because(\sprintf('"%s" is not a root declared under "phil_content.roots".', $root::class));
    }
}

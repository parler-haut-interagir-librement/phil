<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\OpenApi;

use Nelmio\ApiDocBundle\Model\Model;
use Nelmio\ApiDocBundle\ModelDescriber\ModelDescriberInterface;
use OpenApi\Annotations\Schema;
use Override;
use Phil\EnumsBundle\Application\Schema\EnumSchema;
use Symfony\Component\TypeInfo\Type\ObjectType;
use UnitEnum;

/**
 * Describes the enums of NelmioApiDocBundle's models with `EnumSchema`: beside the type and the values NelmioApiDoc
 * writes itself, the names and descriptions client generators read (`x-enum-varnames`, `x-enum-descriptions`) and
 * the `#[DefaultCase]`, for pure enums as well as backed ones. Deprecated cases are left out, as in `EnumSchema`.
 *
 * It runs before NelmioApiDoc's own enum describer, which then never sees an enum.
 */
final class NelmioEnumModelDescriber implements ModelDescriberInterface
{
    #[Override]
    public function describe(Model $model, Schema $schema): void
    {
        $type = $model->getTypeInfo();
        $enum = $type instanceof ObjectType ? $type->getClassName() : '';

        if (!enum_exists($enum)) {
            return;
        }

        $described = EnumSchema::of($enum);
        $schema->type = $described['type'];
        $schema->enum = $described['enum'];
        $schema->x = ['enum-varnames' => $described['x-enum-varnames'], 'enum-descriptions' => $described['x-enum-descriptions']];

        if (\array_key_exists('default', $described)) {
            $schema->default = $described['default'];
        }
    }

    #[Override]
    public function supports(Model $model): bool
    {
        $type = $model->getTypeInfo();

        return $type instanceof ObjectType && is_subclass_of($type->getClassName(), UnitEnum::class);
    }
}

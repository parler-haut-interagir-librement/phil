<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use Phil\CommerceBundle\Application\Contract\DocumentSeries;
use Phil\CommerceBundle\Application\Contract\DocumentTypes;
use Phil\CommerceBundle\Domain\ValueObject\DocumentType;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The register of document types: every type the core and the modules declare, each numbered in its own series
 * without gaps, and seen by its buyer as far as its visibility says. Two types never share a code or a prefix, so two
 * series never give the same number.
 *
 * The register is read once per instance and holds nothing else; its numbers come from `DocumentSeries`, inside the
 * transaction that stores the document.
 */
final class DocumentRegister
{
    /** @var array<string, DocumentType>|null */
    private ?array $types = null;

    /**
     * @param iterable<DocumentTypes> $declarations
     */
    public function __construct(
        private readonly iterable $declarations,
        private readonly ?DocumentSeries $series = null,
    ) {
    }

    /**
     * @return array<string, DocumentType> every type, by code, in the order declared
     */
    public function types(): array
    {
        if (null !== $this->types) {
            return $this->types;
        }

        $types = [];
        $prefixes = [];

        foreach ($this->declarations as $declaration) {
            foreach ($declaration->documentTypes() as $type) {
                if (isset($types[$type->code])) {
                    throw InvalidValue::because(\sprintf('The document type "%s" is declared twice.', $type->code));
                }

                if (isset($prefixes[$type->prefix])) {
                    throw InvalidValue::because(\sprintf('The document types "%s" and "%s" share the prefix "%s": their numbers would meet.', $prefixes[$type->prefix], $type->code, $type->prefix));
                }

                $types[$type->code] = $type;
                $prefixes[$type->prefix] = $type->code;
            }
        }

        return $this->types = $types;
    }

    /**
     * The types a buyer's list of documents shows, by code, in the order declared; the others are reached by their
     * direct link, or kept for the store, as their visibility says.
     *
     * @return array<string, DocumentType>
     */
    public function listed(): array
    {
        return array_filter($this->types(), static fn (DocumentType $type): bool => $type->visibility->isListed());
    }

    public function type(string $code): DocumentType
    {
        return $this->types()[$code] ?? throw InvalidValue::because(\sprintf('"%s" is not a document type of the register.', $code));
    }

    /**
     * The next number of the type's series, inside the transaction that stores the document.
     */
    public function next(string $code): string
    {
        $type = $this->type($code);

        return ($this->series ?? throw InvalidValue::because('The document register numbers through a DocumentSeries: none is wired.'))->nextIn($type);
    }
}

<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Infrastructure\Form;

use Override;
use Phil\Contact\ContactCard;
use Phil\Contact\Email;
use Phil\Contact\Exception\ContactException;
use Phil\Contact\PersonName;
use Phil\Contact\PhoneLine;
use Phil\InstantMessenger\Handle;
use Phil\Url\TypedUrl;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Between a `ContactCard` and the fields of its form, the structured name among them when the form asks for it; an
 * empty card is none.
 *
 * @phpstan-type Fields array{name: ?string, organization: ?string, title: ?string, emails: array<array-key, ?Email>, phones: array<array-key, ?PhoneLine>, urls: array<array-key, ?TypedUrl>, handles: array<array-key, ?Handle>, structuredName?: ?PersonName}
 *
 * @implements DataTransformerInterface<ContactCard, Fields>
 */
final class ContactCardTransformer implements DataTransformerInterface
{
    #[Override]
    public function transform(mixed $value): array
    {
        return [
            // A name the structured name gives is left to it, so that it follows the parts when they change.
            'name' => null !== $value && $value->name === $value->structuredName?->display() ? null : $value?->name,
            'organization' => $value?->organization,
            'title' => $value?->title,
            'emails' => $value->emails ?? [],
            'phones' => $value->phones ?? [],
            'urls' => $value->urls ?? [],
            'handles' => $value->handles ?? [],
            'structuredName' => $value?->structuredName,
        ];
    }

    #[Override]
    public function reverseTransform(mixed $value): ?ContactCard
    {
        try {
            $card = ContactCard::of(
                $value['name'] ?? null,
                $value['organization'] ?? null,
                $value['title'] ?? null,
                $this->list($value['emails'] ?? []),
                $this->list($value['phones'] ?? []),
                $this->list($value['urls'] ?? []),
                $this->list($value['handles'] ?? []),
                $value['structuredName'] ?? null,
            );
        } catch (ContactException $contactException) {
            throw new TransformationFailedException($contactException->getMessage(), $contactException->getCode(), previous: $contactException);
        }

        return $card->isEmpty() ? null : $card;
    }

    /**
     * @template T of object
     *
     * @param array<array-key, ?T> $entries
     *
     * @return list<T>
     */
    private function list(array $entries): array
    {
        return array_values(array_filter($entries, static fn (?object $entry): bool => null !== $entry));
    }
}

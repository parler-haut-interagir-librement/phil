<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Infrastructure\Http;

use Phil\AddressBundle\Application\CountryFields;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Locales;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The format of a country's addresses as the Stimulus controller reads it: its fields in order with their labels and
 * whether they are required, and its states or provinces to choose from, cached a day.
 *
 * The language is the `locale` query parameter, which the address form writes in the URL it gives the controller:
 * the URL then names the answer, which shared caches may keep. Without it, the language is the request's, which the
 * application may take from a session or a preference no cache sees, so the answer is private.
 */
final readonly class AddressFormatController
{
    public function __construct(
        private ?TranslatorInterface $translator = null,
    ) {
    }

    public function __invoke(Request $request, string $country): JsonResponse
    {
        $country = strtoupper($country);

        if (!Countries::exists($country)) {
            return new JsonResponse(['error' => \sprintf('"%s" is not a country.', $country)], JsonResponse::HTTP_NOT_FOUND);
        }

        $asked = $request->query->getString('locale');
        $locale = '' !== $asked && Locales::exists($asked) ? $asked : $request->getLocale();

        $response = new JsonResponse([
            'country' => $country,
            'fields' => array_map(fn (array $field): array => [...$field, 'label' => $this->translator?->trans($field['label'], [], 'phil_address', $locale) ?? $field['label']], CountryFields::of($country, $request->query->getBoolean('recipient'))),
            'subdivisions' => CountryFields::subdivisions($country, $locale),
        ]);
        $locale === $asked ? $response->setPublic() : $response->setPrivate();
        $response->setMaxAge(86400);

        return $response;
    }
}

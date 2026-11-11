<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\AddressBundle\Infrastructure\Http\AddressFormatController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

#[CoversClass(AddressFormatController::class)]
final class AddressFormatControllerTest extends TestCase
{
    public function testACountrysFormatIsJsonInTheRequestsLanguage(): void
    {
        $translator = new class implements TranslatorInterface {
            use TranslatorTrait;

            /**
             * @param array<array-key, mixed> $parameters
             */
            #[Override]
            public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return \sprintf('%s/%s/%s', $id, $domain, $locale);
            }
        };
        $request = Request::create('/address-format/ca');
        $request->setLocale('fr');

        $response = new AddressFormatController($translator)($request, 'ca');
        $format = $this->json((string) $response->getContent());

        self::assertSame('CA', $format['country']);
        self::assertContains(['name' => 'administrativeArea', 'label' => 'address.administrative_area.province/phil_address/fr', 'required' => true], $this->list($format, 'fields'));
        self::assertContains(['code' => 'QC', 'name' => 'Québec (QC)'], $this->list($format, 'subdivisions'));
        self::assertSame([null, true, '86400', []], [$response->headers->getCacheControlDirective('public'), $response->headers->getCacheControlDirective('private'), $response->headers->getCacheControlDirective('max-age'), $response->getVary()]);
    }

    public function testTheLanguageNamedInTheUrlIsTakenAndMayBeShared(): void
    {
        $request = Request::create('/address-format/ca?locale=en');
        $request->setLocale('fr');

        $response = new AddressFormatController()($request, 'ca');

        self::assertContains(['code' => 'QC', 'name' => 'Quebec (QC)'], $this->list($this->json((string) $response->getContent()), 'subdivisions'));
        self::assertSame([true, null, '86400'], [$response->headers->getCacheControlDirective('public'), $response->headers->getCacheControlDirective('private'), $response->headers->getCacheControlDirective('max-age')]);

        $unknown = Request::create('/address-format/ca?locale=xx-nowhere');
        $unknown->setLocale('fr');

        $answer = new AddressFormatController()($unknown, 'ca');
        self::assertContains(['code' => 'QC', 'name' => 'Québec (QC)'], $this->list($this->json((string) $answer->getContent()), 'subdivisions'));
        self::assertTrue($answer->headers->getCacheControlDirective('private'));
    }

    public function testTheRecipientAndKeysWithoutTranslator(): void
    {
        $format = $this->json((string) new AddressFormatController()(Request::create('/x?recipient=1'), 'FR')->getContent());

        self::assertSame(['name' => 'organization', 'label' => 'address.field.organization', 'required' => false], $this->list($format, 'fields')[0] ?? null);
        self::assertSame([], $this->list($format, 'subdivisions'));
        self::assertSame(['name' => 'addressLine1', 'label' => 'address.field.addressLine1', 'required' => true], $this->list($this->json((string) new AddressFormatController()(Request::create('/x'), 'FR')->getContent()), 'fields')[0] ?? null);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function json(string $content): array
    {
        $data = json_decode($content, true);
        self::assertIsArray($data);

        return $data;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function list(array $data, string $key): array
    {
        $list = $data[$key] ?? null;
        self::assertIsArray($list);

        return $list;
    }

    public function testAnUnknownCountryIsNotFound(): void
    {
        $response = new AddressFormatController()(Request::create('/x'), 'zz');

        self::assertSame([404, ['error' => '"ZZ" is not a country.']], [$response->getStatusCode(), json_decode((string) $response->getContent(), true)]);
    }
}

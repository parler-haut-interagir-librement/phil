<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Infrastructure;

use Closure;
use Phil\CommerceLogisticsBundle\Domain\Carrier\LabelRequest;
use Phil\CommerceLogisticsBundle\Domain\Carrier\Parcel;
use Phil\CommerceLogisticsBundle\Domain\Carrier\RateQuote;
use Phil\CommerceLogisticsBundle\Domain\Carrier\RateRequest;
use Phil\CommerceLogisticsBundle\Domain\Carrier\ShippingAddress;
use Phil\CommerceLogisticsBundle\Domain\Exception\CarrierRefused;
use Phil\CommerceLogisticsBundle\Infrastructure\CanadaPost\CanadaPostCarrier;
use Phil\CommerceLogisticsBundle\Infrastructure\CanadaPost\CanadaPostCredentials;
use Phil\CommerceLogisticsBundle\Infrastructure\CanadaPost\CanadaPostPayloads;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Canada Post asked through Symfony's mock client, with answers shaped as the API's, kept in `tests/Fixtures`: no
 * request leaves the machine.
 */
#[CoversClass(CanadaPostCarrier::class)]
#[CoversClass(CanadaPostPayloads::class)]
final class CanadaPostCarrierTest extends TestCase
{
    private const string API = 'https://api.test.example';

    private const string TOKEN = 'https://auth.test.example/oauth2/token';

    /** @var list<array{method: string, url: string, headers: list<string>, body: string, options: array<array-key, mixed>}> */
    private array $requests = [];

    public function testItAsksTheRatesOfAParcel(): void
    {
        $carrier = $this->carrier([$this->fixture('token.json'), $this->fixture('rates.json')], contract: '0042708517');

        $quotes = $carrier->rates(new RateRequest(
            ShippingAddress::of('CA', 'k2b 8j6'),
            ShippingAddress::of('CA', 'J0E1X0'),
            Parcel::of(1_250, 300, 200, 105),
        ));

        self::assertSame([
            ['canada_post', 'DOM.XP', 'Xpresspost', 2_107, 'CAD', 1],
            ['canada_post', 'DOM.RP', 'Regular Parcel', 1_266, 'CAD', 3],
            ['canada_post', 'DOM.EP', 'Expedited Parcel', 1_400, 'CAD', null],
        ], array_map(static fn (RateQuote $quote): array => [$quote->carrier, $quote->service, $quote->serviceName, $quote->cost->minorUnits, $quote->cost->currency->code, $quote->transitDays], $quotes));

        $token = $this->request(0);
        $prices = $this->request(1);
        self::assertSame(['POST', self::TOKEN], [$token['method'], $token['url']]);
        self::assertContains('Authorization: Basic '.base64_encode('key:secret'), $token['headers']);
        self::assertSame('grant_type=client_credentials', $token['body']);
        self::assertContains('Accept: application/json', $token['headers']);

        self::assertSame(['POST', self::API.'/prices'], [$prices['method'], $prices['url']]);
        self::assertContains('Authorization: Bearer test-access-token', $prices['headers']);
        self::assertContains('Accept: application/json', $prices['headers']);
        self::assertContains('Accept-Language: fr-CA', $prices['headers']);
        self::assertContains('Content-Type: application/json', $prices['headers']);
        self::assertSame([
            'customer-number' => '1234567',
            'contract-id' => '0042708517',
            'origin-postal-code' => 'K2B8J6',
            'parcel-characteristics' => ['weight' => 1.25, 'dimensions' => ['length' => 30.0, 'width' => 20.0, 'height' => 10.5]],
            'destination' => ['domestic' => ['postal-code' => 'J0E1X0']],
        ], json_decode($prices['body'], true));
        self::assertSame(10.0, $prices['options']['timeout']);
        self::assertSame(30.0, $prices['options']['max_duration']);
        self::assertSame(0, $prices['options']['max_redirects']);
    }

    /**
     * @return iterable<string, array{ShippingAddress, array<string, mixed>}>
     */
    public static function destinations(): iterable
    {
        yield 'the United States' => [ShippingAddress::of('US', '90210'), ['united-states' => ['zip-code' => '90210']]];
        yield 'another country' => [ShippingAddress::of('FR', '75001'), ['international' => ['country-code' => 'FR']]];
    }

    /**
     * @param array<string, mixed> $destination
     */
    #[DataProvider('destinations')]
    public function testADestinationAbroad(ShippingAddress $to, array $destination): void
    {
        $this->carrier([$this->fixture('token.json'), $this->fixture('rates.json')])->rates(new RateRequest(ShippingAddress::of('CA', 'K2B8J6'), $to, Parcel::of(500)));

        $body = json_decode($this->request(1)['body'], true);
        self::assertIsArray($body);
        self::assertSame($destination, $body['destination']);
        self::assertSame(['weight' => 0.5], $body['parcel-characteristics']);
        self::assertArrayNotHasKey('contract-id', $body);
    }

    public function testItBuysALabel(): void
    {
        $label = $this->carrier([$this->fixture('token.json'), $this->fixture('shipment.json'), $this->fixture('label.pdf', 200, 'application/pdf')])->label($this->labelRequest());

        self::assertSame(['canada_post', 'DOM.RP', '1234567890123456', 1_266, 'application/pdf'], [$label->carrier, $label->service, $label->tracking, $label->cost->minorUnits, $label->mediaType]);
        self::assertStringStartsWith('%PDF-', $label->document);

        $shipment = $this->request(1);
        $document = $this->request(2);
        self::assertSame(['POST', self::API.'/shipments'], [$shipment['method'], $shipment['url']]);
        self::assertSame([
            'customer-number' => '1234567',
            'requested-shipping-point' => 'K2B8J6',
            'delivery-spec' => [
                'service-code' => 'DOM.RP',
                'sender' => [
                    'name' => 'Boutique',
                    'address-details' => ['address-line-1' => '2701 Riverside Drive', 'city' => 'Ottawa', 'prov-state' => 'ON', 'country-code' => 'CA', 'postal-zip-code' => 'K2B8J6'],
                    'contact-phone' => '613-555-0100',
                ],
                'destination' => [
                    'name' => 'Buyer',
                    'address-details' => ['address-line-1' => '1 Main Street', 'address-line-2' => 'Unit 4', 'city' => 'Mont-Tremblant', 'prov-state' => 'QC', 'country-code' => 'CA', 'postal-zip-code' => 'J0E1X0'],
                    'client-voice-number' => '',
                ],
                'parcel-characteristics' => ['weight' => 2.0],
                'preferences' => ['show-packing-instructions' => true],
                'references' => ['customer-ref-1' => 'ORDER-42'],
            ],
        ], json_decode($shipment['body'], true));
        self::assertSame(['GET', 'https://api.test.example/artifacts/20389041'], [$document['method'], $document['url']]);
        self::assertContains('Accept: application/pdf', $document['headers']);
        self::assertContains('Authorization: Bearer test-access-token', $document['headers']);
    }

    public function testALabelWithoutReferenceAndItsPriceFromItsLink(): void
    {
        $label = $this->carrier([$this->fixture('token.json'), $this->fixture('shipment-without-price.json'), $this->fixture('price.json'), $this->fixture('label.pdf', 200, 'application/pdf')])->label($this->labelRequest(''));

        self::assertSame(['1234567890123457', 1_401], [$label->tracking, $label->cost->minorUnits]);
        $body = json_decode($this->request(1)['body'], true);
        self::assertIsArray($body);
        self::assertIsArray($body['delivery-spec']);
        self::assertArrayNotHasKey('references', $body['delivery-spec']);
        self::assertSame(['GET', 'https://api.test.example/shipments/406951321983787353/price'], [$this->request(2)['method'], $this->request(2)['url']]);
    }

    public function testTheTokenIsKeptUntilShortlyBeforeItExpiresAndDroppedOnReset(): void
    {
        $clock = new MockClock('2026-11-11 10:00:00');
        $carrier = $this->carrier([
            $this->fixture('token.json'), $this->fixture('rates.json'), $this->fixture('rates.json'),
            $this->fixture('token.json'), $this->fixture('rates.json'),
            $this->fixture('token.json'), $this->fixture('rates.json'),
        ], clock: $clock);

        $carrier->rates($this->rateRequest());

        $clock->modify('+3539 seconds');
        $carrier->rates($this->rateRequest());
        self::assertSame(3, $this->sent());

        $clock->modify('+1 second');
        $carrier->rates($this->rateRequest());
        self::assertSame(self::TOKEN, $this->request(3)['url']);

        $carrier->reset();
        $carrier->rates($this->rateRequest());
        self::assertSame(self::TOKEN, $this->request(5)['url']);
        self::assertSame(7, $this->sent());
    }

    public function testARefusalNamesTheCarriersMessages(): void
    {
        $this->expectException(CarrierRefused::class);
        $this->expectExceptionMessageIsOrContains('The carrier canada_post did not answer: HTTP 400, 9111 The postal code is not valid.; E002 The weight is missing.');

        $this->carrier([$this->fixture('token.json'), $this->fixture('error.json', 400)])->rates($this->rateRequest());
    }

    /**
     * @return iterable<string, array{list<MockResponse>, string}>
     */
    public static function failures(): iterable
    {
        $token = file_get_contents(__DIR__.'/../../Fixtures/CanadaPost/token.json');
        self::assertIsString($token);

        yield 'a refused token' => [[new MockResponse('{"error":"invalid_client"}', ['http_code' => 401])], 'no access token was given (HTTP 401).'];
        yield 'a token answer not in JSON' => [[new MockResponse('<html>', ['http_code' => 200])], 'its token answer is not JSON.'];
        yield 'a token without lifetime' => [[new MockResponse('{"access_token":"t"}')], 'no access token was given (HTTP 200).'];
        yield 'a token of zero seconds' => [[new MockResponse('{"access_token":"t","expires_in":0}')], 'no access token was given (HTTP 200).'];
        yield 'a token with a space' => [[new MockResponse('{"access_token":"a b","expires_in":60}')], 'no access token was given (HTTP 200).'];
        yield 'a token answer that is a list' => [[new MockResponse('["t"]')], 'no access token was given (HTTP 200).'];
        yield 'a token ending in a line break' => [[new MockResponse('{"access_token":"t\\n","expires_in":60}')], 'no access token was given (HTTP 200).'];
        yield 'a token answer too deep' => [[new MockResponse('{"access_token":"t","expires_in":60,"x":[[[[[[[1]]]]]]]}')], 'its token answer is not JSON.'];
        yield 'a redirection' => [[new MockResponse($token), new MockResponse('{}', ['http_code' => 300])], 'HTTP 300, an error without a message'];
        yield 'an error without message' => [[new MockResponse($token), new MockResponse('{}', ['http_code' => 503])], 'HTTP 503, an error without a message'];
        yield 'an error in HTML' => [[new MockResponse($token), new MockResponse('<html>', ['http_code' => 502])], 'its answer is not JSON (HTTP 502).'];
        yield 'an answer too deep' => [[new MockResponse($token), new MockResponse(str_repeat('[', 40).str_repeat(']', 40))], 'its answer is not JSON (HTTP 200).'];
        yield 'an answer one level too deep' => [[new MockResponse($token), new MockResponse(str_repeat('[', 32).str_repeat(']', 32))], 'its answer is not JSON (HTTP 200).'];
        yield 'an answer at the deepest' => [[new MockResponse($token), new MockResponse(str_repeat('[', 31).str_repeat(']', 31))], 'its answer has no list "price-quotes".'];
        yield 'an answer too large' => [[new MockResponse($token), new MockResponse('{"price-quotes":"'.str_repeat('x', 1_048_576).'"}')], 'its answer is too large.'];
        yield 'an answer at the largest' => [[new MockResponse($token), new MockResponse('{"price-quotes":"'.str_repeat('x', 1_048_576 - 19).'"}')], 'its answer has no list "price-quotes".'];
        yield 'no list of quotes' => [[new MockResponse($token), new MockResponse('{"price-quotes":{"a":1}}')], 'its answer has no list "price-quotes".'];
        yield 'a quote without price' => [[new MockResponse($token), new MockResponse('{"price-quotes":[{"service-code":"DOM.RP","service-name":"Regular"}]}')], 'its answer has no object "price-details".'];
        yield 'a quote without name' => [[new MockResponse($token), new MockResponse('{"price-quotes":[{"service-code":"DOM.RP","price-details":{"due":1}}]}')], 'its answer has no text "service-name".'];
        yield 'a quote with an empty name' => [[new MockResponse($token), new MockResponse('{"price-quotes":[{"service-code":"DOM.RP","service-name":"","price-details":{"due":1}}]}')], 'its answer has no text "service-name".'];
        yield 'a quote with a name too long' => [[new MockResponse($token), new MockResponse('{"price-quotes":[{"service-code":"DOM.RP","service-name":"'.str_repeat('n', 2_049).'","price-details":{"due":1}}]}')], 'its answer has no text "service-name".'];
        yield 'a service code with a space' => [[new MockResponse($token), new MockResponse('{"price-quotes":[{"service-code":"DOM RP","service-name":"R","price-details":{"due":1}}]}')], 'its "service-code" is not a code.'];
        yield 'a service code ending in a line break' => [[new MockResponse($token), new MockResponse('{"price-quotes":[{"service-code":"DOM.RP\\n","service-name":"R","price-details":{"due":1}}]}')], 'its "service-code" is not a code.'];
        yield 'a price as a boolean' => [[new MockResponse($token), new MockResponse('{"price-quotes":[{"service-code":"DOM.RP","service-name":"R","price-details":{"due":true}}]}')], 'its answer has no amount "due".'];
        yield 'a price too precise' => [[new MockResponse($token), new MockResponse('{"price-quotes":[{"service-code":"DOM.RP","service-name":"R","price-details":{"due":"1.234"}}]}')], 'its "due" is not an amount.'];
        yield 'a negative price' => [[new MockResponse($token), new MockResponse('{"price-quotes":[{"service-code":"DOM.RP","service-name":"R","price-details":{"due":-1}}]}')], 'its "due" is negative.'];
        yield 'no answer at all' => [[new MockResponse($token), new MockResponse([new TransportException('timeout')])], 'no answer came back.'];
    }

    /**
     * @param list<MockResponse> $responses
     */
    #[DataProvider('failures')]
    public function testAnAnswerItCannotTrustIsRefused(array $responses, string $reason): void
    {
        $this->expectException(CarrierRefused::class);
        $this->expectExceptionMessageIsOrContains('The carrier canada_post did not answer: '.$reason);

        $this->carrier($responses)->rates($this->rateRequest());
    }

    public function testAnOddTransitTimeIsLeftOut(): void
    {
        $quotes = $this->carrier([$this->fixture('token.json'), new MockResponse('{"price-quotes":[{"service-code":"A","service-name":"A","price-details":{"due":1},"service-standard":{"expected-transit-time":400}},{"service-code":"B","service-name":"B","price-details":{"due":1},"service-standard":{"expected-transit-time":-1}},{"service-code":"C","service-name":"C","price-details":{"due":1},"service-standard":{"expected-transit-time":365}},{"service-code":"D","service-name":"D","price-details":{"due":1},"service-standard":{"expected-transit-time":0}}]}')])->rates($this->rateRequest());

        self::assertSame([null, null, 365, 0], array_map(static fn (RateQuote $quote): ?int => $quote->transitDays, $quotes));
    }

    /**
     * A name of 2048 bytes is the longest text read, and a price given as a float is rounded to the cent.
     */
    public function testTheLimitsOfAQuoteAreRead(): void
    {
        $quotes = $this->carrier([$this->fixture('token.json'), new MockResponse('{"price-quotes":[{"service-code":"A","service-name":"'.str_repeat('n', 2_048).'","price-details":{"due":12.666}}]}')])->rates($this->rateRequest());

        self::assertSame([2_048, 1_267], [\strlen($quotes[0]->serviceName), $quotes[0]->cost->minorUnits]);
    }

    public function testTheWeightAndTheSidesOfAParcelAreSentInKilogramsAndCentimetres(): void
    {
        $this->carrier([$this->fixture('token.json'), $this->fixture('rates.json')])->rates(new RateRequest(ShippingAddress::of('CA', 'K2B8J6'), ShippingAddress::of('CA', 'J0E1X0'), Parcel::of(1_234, 305, 205, 110)));

        $body = json_decode($this->request(1)['body'], true);
        self::assertIsArray($body);
        self::assertSame(['weight' => 1.234, 'dimensions' => ['length' => 30.5, 'width' => 20.5, 'height' => 11.0]], $body['parcel-characteristics']);
    }

    /**
     * Five of the messages the carrier gave, each cut at 200 characters, and none it gave in another shape.
     */
    public function testARefusalNamesFiveMessagesAtMost(): void
    {
        $messages = [['code' => 5, 'description' => 'A code that is not text.'], ['code' => 'E1', 'description' => str_repeat('é', 300)]];

        foreach (['E2', 'E3', 'E4', 'E5', 'E6'] as $code) {
            $messages[] = ['code' => $code, 'description' => 'Refused.'];
        }

        try {
            $this->carrier([$this->fixture('token.json'), new MockResponse(json_encode(['messages' => $messages], \JSON_THROW_ON_ERROR), ['http_code' => 400])])->rates($this->rateRequest());
            self::fail('The carrier refused the request.');
        } catch (CarrierRefused $carrierRefused) {
            self::assertSame('The carrier canada_post did not answer: HTTP 400, E1 '.str_repeat('é', 197).'; E2 Refused.; E3 Refused.; E4 Refused.; E5 Refused.', $carrierRefused->getMessage());
        }
    }

    /**
     * A token whose lifetime is shorter than the margin serves one request; the next asks for another, and a token
     * of a deep but readable answer is taken.
     */
    public function testAShortLivedTokenServesOneRequest(): void
    {
        $carrier = $this->carrier([
            new MockResponse('{"access_token":"t","expires_in":1,"scope":[[[[[["Merchant"]]]]]]}'), $this->fixture('rates.json'),
            $this->fixture('token.json'), $this->fixture('rates.json'),
        ]);

        $carrier->rates($this->rateRequest());
        $carrier->rates($this->rateRequest());

        self::assertSame([self::TOKEN, self::API.'/prices', self::TOKEN, self::API.'/prices'], array_column($this->requests, 'url'));
        self::assertContains('Authorization: Bearer t', $this->request(1)['headers']);
    }

    public function testAnAddressOfTheApiEndingInASlashIsJoinedToItsPaths(): void
    {
        $this->carrier([$this->fixture('token.json'), $this->fixture('rates.json')], api: self::API.'/')->rates($this->rateRequest());

        self::assertSame(self::API.'/prices', $this->request(1)['url']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function labelFailures(): iterable
    {
        $shipment = static fn (string $links, string $price = ',"shipment-price":{"due-amount":1}'): string => '{"tracking-pin":"123"'.$price.',"links":['.$links.']}';

        yield 'a label on another host' => [$shipment('{"rel":"label","href":"https://elsewhere.example/artifacts/1"}'), 'it linked to a host other than its API.'];
        yield 'a label over plain HTTP' => [$shipment('{"rel":"label","href":"http://api.test.example/artifacts/1"}'), 'it linked to a host other than its API.'];
        yield 'a label on another port' => [$shipment('{"rel":"label","href":"https://api.test.example:8443/artifacts/1"}'), 'it linked to a host other than its API.'];
        yield 'a label with credentials' => [$shipment('{"rel":"label","href":"https://user@api.test.example/artifacts/1"}'), 'it linked to a host other than its API.'];
        yield 'a label whose user information looks like the host' => [$shipment('{"rel":"label","href":"https://api.test.example@elsewhere.example/artifacts/1"}'), 'it linked to a host other than its API.'];
        yield 'a label on another host before a backslash' => [$shipment('{"rel":"label","href":"https://elsewhere.example\\\\@api.test.example/artifacts/1"}'), 'it linked to a host other than its API.'];
        yield 'a label that is not an address' => [$shipment('{"rel":"label","href":"artifacts/1"}'), 'it linked to a host other than its API.'];
        yield 'a label on an IP address' => [$shipment('{"rel":"label","href":"https://192.0.2.1/artifacts/1"}'), 'it linked to a host other than its API.'];
        yield 'no label link' => [$shipment('{"rel":"self","href":"https://api.test.example/s/1"}'), 'its shipment has no label link.'];
        yield 'neither price nor price link' => [$shipment('{"rel":"label","href":"https://api.test.example/artifacts/1"}', ''), 'its shipment has neither a price nor a price link.'];
        yield 'no tracking number' => ['{"links":[]}', 'its answer has no text "tracking-pin".'];
    }

    #[DataProvider('labelFailures')]
    public function testALabelItCannotTrustIsRefused(string $shipment, string $reason): void
    {
        $this->expectException(CarrierRefused::class);
        $this->expectExceptionMessageIsOrContains('The carrier canada_post did not answer: '.$reason);

        $this->carrier([$this->fixture('token.json'), new MockResponse($shipment), $this->fixture('label.pdf')])->label($this->labelRequest());
    }

    public function testALabelLinkIsFollowedAsItWasRead(): void
    {
        $shipment = '{"tracking-pin":"123","shipment-price":{"due-amount":1},"links":[{"rel":"label","href":"https://API.test.example:443/a/../artifacts/1"}]}';
        $this->carrier([$this->fixture('token.json'), new MockResponse($shipment), $this->fixture('label.pdf', 200, 'application/pdf')])->label($this->labelRequest());

        self::assertSame(['GET', 'https://api.test.example/artifacts/1'], [$this->request(2)['method'], $this->request(2)['url']]);
    }

    public function testALabelThatIsNotAPdfIsRefused(): void
    {
        $this->expectException(CarrierRefused::class);
        $this->expectExceptionMessageIsOrContains('The carrier canada_post did not answer: its label is not a PDF (HTTP 200).');

        $this->carrier([$this->fixture('token.json'), $this->fixture('shipment.json'), new MockResponse('<html>')])->label($this->labelRequest());
    }

    public function testALabelThatIsMissingIsRefused(): void
    {
        $this->expectException(CarrierRefused::class);
        $this->expectExceptionMessageIsOrContains('The carrier canada_post did not answer: its label is not a PDF (HTTP 404).');

        $this->carrier([$this->fixture('token.json'), $this->fixture('shipment.json'), $this->fixture('label.pdf', 404)])->label($this->labelRequest());
    }

    public function testItIsNamedAfterItsCarrier(): void
    {
        self::assertSame('canada_post', $this->carrier([])->carrier());
    }

    /**
     * @param list<ResponseInterface> $responses
     */
    private function carrier(array $responses, ?string $contract = null, ?MockClock $clock = null, string $api = self::API): CanadaPostCarrier
    {
        $this->requests = [];
        $record = function (string $method, string $url, array $options) use (&$responses): ResponseInterface {
            $headers = $options['headers'] ?? [];
            $body = $options['body'] ?? '';
            self::assertIsArray($headers);
            self::assertIsString($body);
            $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => array_values(array_filter($headers, is_string(...))), 'body' => $body, 'options' => $options];

            return array_shift($responses) ?? new MockResponse('', ['http_code' => 500]);
        };

        return new CanadaPostCarrier(
            new MockHttpClient(Closure::fromCallable($record)),
            new CanadaPostCredentials('key', 'secret', '1234567', $api, self::TOKEN, $contract, 'fr-CA'),
            $clock ?? new MockClock(),
        );
    }

    private function sent(): int
    {
        return \count($this->requests);
    }

    /**
     * @return array{method: string, url: string, headers: list<string>, body: string, options: array<array-key, mixed>}
     */
    private function request(int $index): array
    {
        self::assertArrayHasKey($index, $this->requests);

        return $this->requests[$index];
    }

    private function fixture(string $name, int $status = 200, string $type = 'application/json'): MockResponse
    {
        $body = file_get_contents(__DIR__.'/../../Fixtures/CanadaPost/'.$name);
        self::assertIsString($body);

        return new MockResponse($body, ['http_code' => $status, 'response_headers' => ['content-type' => $type]]);
    }

    private function rateRequest(): RateRequest
    {
        return new RateRequest(ShippingAddress::of('CA', 'K2B8J6'), ShippingAddress::of('CA', 'J0E1X0'), Parcel::of(500));
    }

    private function labelRequest(string $reference = 'ORDER-42'): LabelRequest
    {
        return LabelRequest::of(
            ShippingAddress::of('CA', 'K2B 8J6', 'Boutique', ['2701 Riverside Drive'], 'Ottawa', 'ON', '613-555-0100'),
            ShippingAddress::of('CA', 'J0E 1X0', 'Buyer', ['1 Main Street', 'Unit 4'], 'Mont-Tremblant', 'QC'),
            Parcel::of(2_000),
            'DOM.RP',
            $reference,
        );
    }
}

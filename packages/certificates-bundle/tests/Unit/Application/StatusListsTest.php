<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CertificatesBundle\Application\CertificateStatusLists;
use Phil\CertificatesBundle\Application\StatusListLayout;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCertificates;
use Phil\CertificatesBundle\Tests\Unit\Support\Kit;
use Phil\OpenBadgesBundle\Application\Contract\StatusLists;
use Phil\OpenBadgesBundle\Application\StatusListContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatusListLayout::class)]
#[CoversClass(CertificateStatusLists::class)]
final class StatusListsTest extends TestCase
{
    public function testTheIndexesFillOneListAfterTheOther(): void
    {
        $layout = new StatusListLayout('awards', 2);

        self::assertSame(['awards-0', 0], $layout->locate(0));
        self::assertSame(['awards-0', 1], $layout->locate(1));
        self::assertSame(['awards-2', 1], $layout->locate(5));
        self::assertSame([0, 2], $layout->range('awards-0'));
        self::assertSame([4, 6], $layout->range('awards-2'));
        self::assertSame([246_913_578, 246_913_580], $layout->range('awards-123456789'));
        self::assertSame(['certificates-0', 131_071], new StatusListLayout()->locate(131_071));
        self::assertSame(['certificates-1', 0], new StatusListLayout()->locate(131_072));

        foreach (['awards-01', 'awards-', 'awards-1234567890', 'awards-1x', 'other-0', 'xawards-0', 'awards0', 'awardsx0', "awards-0\n"] as $list) {
            self::assertNull($layout->range($list), $list);
        }
    }

    public function testThePrefixAndTheSizeAreChecked(): void
    {
        self::assertSame(str_repeat('a', 50), new StatusListLayout(str_repeat('a', 50), 1)->prefix);
        self::assertSame('A_b-9', new StatusListLayout('A_b-9')->prefix);

        foreach ([[str_repeat('a', 51), 1], ['-a', 1], ["a\n", 1], ['', 1], ['a', 0]] as [$prefix, $size]) {
            try {
                new StatusListLayout($prefix, $size);
                self::fail('The layout is refused.');
            } catch (InvalidValue $invalidValue) {
                self::assertNotSame('', $invalidValue->getMessage());
            }
        }
    }

    public function testAListSetsTheBitsOfItsRevokedCertificates(): void
    {
        $certificates = new InMemoryCertificates();

        foreach ([1, 2, 3, 4] as $index) {
            $certificate = Kit::awarded(code: '00000000000'.$index, index: $index);
            $certificates->save($certificate);

            if (1 !== $index) {
                $certificate->revoke('Fraud.', new DateTimeImmutable());
            }
        }

        $lists = new CertificateStatusLists($certificates, new StatusListLayout('awards', 2));

        self::assertEquals(new StatusListContent([0, 1]), $lists->find('awards-1'));
        self::assertEquals(new StatusListContent([0]), $lists->find('awards-2'));
        self::assertEquals(new StatusListContent([]), $lists->find('awards-0'));
        self::assertNull($lists->find('badges'));
    }

    public function testAListOfAnotherNameIsAskedOfTheApplicationsOwn(): void
    {
        $others = new class implements StatusLists {
            #[Override]
            public function find(string $list): ?StatusListContent
            {
                return 'badges' === $list ? new StatusListContent([7], 'suspension') : null;
            }
        };
        $lists = new CertificateStatusLists(new InMemoryCertificates(), new StatusListLayout(), $others);

        self::assertEquals(new StatusListContent([7], 'suspension'), $lists->find('badges'));
        self::assertEquals(new StatusListContent([]), $lists->find('certificates-0'));
    }
}

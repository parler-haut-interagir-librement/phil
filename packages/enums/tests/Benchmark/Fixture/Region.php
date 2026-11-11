<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Benchmark\Fixture;

use Phil\Enums\Attribute\Group;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Traits\AsCollectableEnumTrait;
use Phil\Enums\Traits\AsMetadatableEnumTrait;

/**
 * Sixty labelled and grouped cases: the size of a list of regions or of statuses a select offers.
 */
#[Meta(Label::class, Group::class)]
enum Region: string
{
    use AsCollectableEnumTrait;
    use AsMetadatableEnumTrait;

    #[Label('Region 1')] #[Group('south')]
    case R01 = 'r01';

    #[Label('Region 2')] #[Group('east')]
    case R02 = 'r02';

    #[Label('Region 3')] #[Group('west')]
    case R03 = 'r03';

    #[Label('Region 4')] #[Group('north')]
    case R04 = 'r04';

    #[Label('Region 5')] #[Group('south')]
    case R05 = 'r05';

    #[Label('Region 6')] #[Group('east')]
    case R06 = 'r06';

    #[Label('Region 7')] #[Group('west')]
    case R07 = 'r07';

    #[Label('Region 8')] #[Group('north')]
    case R08 = 'r08';

    #[Label('Region 9')] #[Group('south')]
    case R09 = 'r09';

    #[Label('Region 10')] #[Group('east')]
    case R10 = 'r10';

    #[Label('Region 11')] #[Group('west')]
    case R11 = 'r11';

    #[Label('Region 12')] #[Group('north')]
    case R12 = 'r12';

    #[Label('Region 13')] #[Group('south')]
    case R13 = 'r13';

    #[Label('Region 14')] #[Group('east')]
    case R14 = 'r14';

    #[Label('Region 15')] #[Group('west')]
    case R15 = 'r15';

    #[Label('Region 16')] #[Group('north')]
    case R16 = 'r16';

    #[Label('Region 17')] #[Group('south')]
    case R17 = 'r17';

    #[Label('Region 18')] #[Group('east')]
    case R18 = 'r18';

    #[Label('Region 19')] #[Group('west')]
    case R19 = 'r19';

    #[Label('Region 20')] #[Group('north')]
    case R20 = 'r20';

    #[Label('Region 21')] #[Group('south')]
    case R21 = 'r21';

    #[Label('Region 22')] #[Group('east')]
    case R22 = 'r22';

    #[Label('Region 23')] #[Group('west')]
    case R23 = 'r23';

    #[Label('Region 24')] #[Group('north')]
    case R24 = 'r24';

    #[Label('Region 25')] #[Group('south')]
    case R25 = 'r25';

    #[Label('Region 26')] #[Group('east')]
    case R26 = 'r26';

    #[Label('Region 27')] #[Group('west')]
    case R27 = 'r27';

    #[Label('Region 28')] #[Group('north')]
    case R28 = 'r28';

    #[Label('Region 29')] #[Group('south')]
    case R29 = 'r29';

    #[Label('Region 30')] #[Group('east')]
    case R30 = 'r30';

    #[Label('Region 31')] #[Group('west')]
    case R31 = 'r31';

    #[Label('Region 32')] #[Group('north')]
    case R32 = 'r32';

    #[Label('Region 33')] #[Group('south')]
    case R33 = 'r33';

    #[Label('Region 34')] #[Group('east')]
    case R34 = 'r34';

    #[Label('Region 35')] #[Group('west')]
    case R35 = 'r35';

    #[Label('Region 36')] #[Group('north')]
    case R36 = 'r36';

    #[Label('Region 37')] #[Group('south')]
    case R37 = 'r37';

    #[Label('Region 38')] #[Group('east')]
    case R38 = 'r38';

    #[Label('Region 39')] #[Group('west')]
    case R39 = 'r39';

    #[Label('Region 40')] #[Group('north')]
    case R40 = 'r40';

    #[Label('Region 41')] #[Group('south')]
    case R41 = 'r41';

    #[Label('Region 42')] #[Group('east')]
    case R42 = 'r42';

    #[Label('Region 43')] #[Group('west')]
    case R43 = 'r43';

    #[Label('Region 44')] #[Group('north')]
    case R44 = 'r44';

    #[Label('Region 45')] #[Group('south')]
    case R45 = 'r45';

    #[Label('Region 46')] #[Group('east')]
    case R46 = 'r46';

    #[Label('Region 47')] #[Group('west')]
    case R47 = 'r47';

    #[Label('Region 48')] #[Group('north')]
    case R48 = 'r48';

    #[Label('Region 49')] #[Group('south')]
    case R49 = 'r49';

    #[Label('Region 50')] #[Group('east')]
    case R50 = 'r50';

    #[Label('Region 51')] #[Group('west')]
    case R51 = 'r51';

    #[Label('Region 52')] #[Group('north')]
    case R52 = 'r52';

    #[Label('Region 53')] #[Group('south')]
    case R53 = 'r53';

    #[Label('Region 54')] #[Group('east')]
    case R54 = 'r54';

    #[Label('Region 55')] #[Group('west')]
    case R55 = 'r55';

    #[Label('Region 56')] #[Group('north')]
    case R56 = 'r56';

    #[Label('Region 57')] #[Group('south')]
    case R57 = 'r57';

    #[Label('Region 58')] #[Group('east')]
    case R58 = 'r58';

    #[Label('Region 59')] #[Group('west')]
    case R59 = 'r59';

    #[Label('Region 60')] #[Group('north')]
    case R60 = 'r60';
}

<?php

declare(strict_types=1);

/*
 * The families and units UnitRegistry::standard() holds.
 *
 * Codes are UCUM codes (https://ucum.org/ucum), case-sensitive; a temperature difference carries the annotation
 * {difference}, which UCUM ignores, so that its code differs from the temperature's. Factors are the exact definitions
 * of each unit in the standard unit of its family: the international inch, foot, yard and mile (1959), the
 * international avoirdupois pound (0.45359237 kg), the US customary and imperial (4.54609 L) volumes, the thermochemical
 * calorie (4.184 J). The `cldr` keys name each unit in the CLDR data of data/cldr/.
 */
return [
    'length' => [
        'standard' => 'm',
        'cldr' => 'length',
        'units' => [
            'm' => ['factor' => '1', 'cldr' => 'length-meter'],
            'km' => ['factor' => '1000', 'cldr' => 'length-kilometer'],
            'dm' => ['factor' => '0.1', 'cldr' => 'length-decimeter'],
            'cm' => ['factor' => '0.01', 'cldr' => 'length-centimeter'],
            'mm' => ['factor' => '0.001', 'cldr' => 'length-millimeter'],
            'um' => ['factor' => '0.000001', 'cldr' => 'length-micrometer'],
            '[in_i]' => ['factor' => '0.0254', 'cldr' => 'length-inch'],
            '[ft_i]' => ['factor' => '0.3048', 'cldr' => 'length-foot'],
            '[yd_i]' => ['factor' => '0.9144', 'cldr' => 'length-yard'],
            '[mi_i]' => ['factor' => '1609.344', 'cldr' => 'length-mile'],
            '[nmi_i]' => ['factor' => '1852', 'cldr' => 'length-nautical-mile'],
        ],
    ],
    'mass' => [
        'standard' => 'kg',
        'cldr' => 'mass',
        'units' => [
            'kg' => ['factor' => '1', 'cldr' => 'mass-kilogram'],
            'g' => ['factor' => '0.001', 'cldr' => 'mass-gram'],
            'mg' => ['factor' => '0.000001', 'cldr' => 'mass-milligram'],
            't' => ['factor' => '1000', 'cldr' => 'mass-tonne'],
            '[lb_av]' => ['factor' => '0.45359237', 'cldr' => 'mass-pound'],
            '[oz_av]' => ['factor' => '0.028349523125', 'cldr' => 'mass-ounce'],
            '[stone_av]' => ['factor' => '6.35029318', 'cldr' => 'mass-stone'],
            '[ston_av]' => ['factor' => '907.18474', 'cldr' => 'mass-ton'],
            '[ozt_tr]' => ['factor' => '0.0311034768', 'cldr' => 'mass-ounce-troy'],
            '[car_m]' => ['factor' => '0.0002', 'cldr' => 'mass-carat'],
        ],
    ],
    'volume' => [
        'standard' => 'm3',
        'cldr' => 'volume',
        'units' => [
            'm3' => ['factor' => '1', 'cldr' => 'volume-cubic-meter'],
            'L' => ['factor' => '0.001', 'cldr' => 'volume-liter'],
            'dL' => ['factor' => '0.0001', 'cldr' => 'volume-deciliter'],
            'cL' => ['factor' => '0.00001', 'cldr' => 'volume-centiliter'],
            'mL' => ['factor' => '0.000001', 'cldr' => 'volume-milliliter'],
            'cm3' => ['factor' => '0.000001', 'cldr' => 'volume-cubic-centimeter'],
            '[cin_i]' => ['factor' => '0.000016387064', 'cldr' => 'volume-cubic-inch'],
            '[cft_i]' => ['factor' => '0.028316846592', 'cldr' => 'volume-cubic-foot'],
            '[gal_us]' => ['factor' => '0.003785411784', 'cldr' => 'volume-gallon'],
            '[qt_us]' => ['factor' => '0.000946352946', 'cldr' => 'volume-quart'],
            '[pt_us]' => ['factor' => '0.000473176473', 'cldr' => 'volume-pint'],
            '[cup_us]' => ['factor' => '0.0002365882365', 'cldr' => 'volume-cup'],
            '[foz_us]' => ['factor' => '0.0000295735295625', 'cldr' => 'volume-fluid-ounce'],
            '[tbs_us]' => ['factor' => '0.00001478676478125', 'cldr' => 'volume-tablespoon'],
            '[tsp_us]' => ['factor' => '0.00000492892159375', 'cldr' => 'volume-teaspoon'],
            '[gal_br]' => ['factor' => '0.00454609', 'cldr' => 'volume-gallon-imperial'],
            '[pt_br]' => ['factor' => '0.00056826125', 'cldr' => 'volume-pint-imperial'],
            '[foz_br]' => ['factor' => '0.0000284130625', 'cldr' => 'volume-fluid-ounce-imperial'],
        ],
    ],
    'area' => [
        'standard' => 'm2',
        'cldr' => 'area',
        'units' => [
            'm2' => ['factor' => '1', 'cldr' => 'area-square-meter'],
            'cm2' => ['factor' => '0.0001', 'cldr' => 'area-square-centimeter'],
            'km2' => ['factor' => '1000000', 'cldr' => 'area-square-kilometer'],
            'har' => ['factor' => '10000', 'cldr' => 'area-hectare'],
            '[sin_i]' => ['factor' => '0.00064516', 'cldr' => 'area-square-inch'],
            '[sft_i]' => ['factor' => '0.09290304', 'cldr' => 'area-square-foot'],
            '[syd_i]' => ['factor' => '0.83612736', 'cldr' => 'area-square-yard'],
            // The UCUM acre is 43,560 square US survey feet, a survey foot being 1200/3937 m.
            '[acr_us]' => ['factor' => '62726400000/15499969', 'cldr' => 'area-acre'],
        ],
    ],
    'temperature' => [
        'standard' => 'K',
        'cldr' => 'temperature',
        'difference' => 'temperature-difference',
        'units' => [
            'K' => ['factor' => '1', 'cldr' => 'temperature-kelvin', 'difference' => 'K{difference}'],
            'Cel' => ['factor' => '1', 'offset' => '273.15', 'cldr' => 'temperature-celsius', 'difference' => 'Cel{difference}'],
            '[degF]' => ['factor' => '5/9', 'offset' => '45967/180', 'cldr' => 'temperature-fahrenheit', 'difference' => '[degF]{difference}'],
            '[degR]' => ['factor' => '5/9', 'cldr' => 'temperature-rankine', 'difference' => '[degR]{difference}'],
        ],
    ],
    'temperature-difference' => [
        'standard' => 'K{difference}',
        'units' => [
            'K{difference}' => ['factor' => '1', 'ucum' => 'K', 'cldr' => 'temperature-kelvin'],
            'Cel{difference}' => ['factor' => '1', 'ucum' => 'Cel', 'cldr' => 'temperature-celsius'],
            '[degF]{difference}' => ['factor' => '5/9', 'ucum' => '[degF]', 'cldr' => 'temperature-fahrenheit'],
            '[degR]{difference}' => ['factor' => '5/9', 'ucum' => '[degR]', 'cldr' => 'temperature-rankine'],
        ],
    ],
    'duration' => [
        'standard' => 's',
        'cldr' => 'duration',
        'units' => [
            's' => ['factor' => '1', 'cldr' => 'duration-second'],
            'ms' => ['factor' => '0.001', 'cldr' => 'duration-millisecond'],
            'min' => ['factor' => '60', 'cldr' => 'duration-minute'],
            'h' => ['factor' => '3600', 'cldr' => 'duration-hour'],
            'd' => ['factor' => '86400', 'cldr' => 'duration-day'],
            'wk' => ['factor' => '604800', 'cldr' => 'duration-week'],
        ],
    ],
    'speed' => [
        'standard' => 'm/s',
        'cldr' => 'speed',
        'units' => [
            'm/s' => ['factor' => '1', 'cldr' => 'speed-meter-per-second'],
            'km/h' => ['factor' => '5/18', 'cldr' => 'speed-kilometer-per-hour'],
            '[mi_i]/h' => ['factor' => '0.44704', 'cldr' => 'speed-mile-per-hour'],
            '[kn_i]' => ['factor' => '463/900', 'cldr' => 'speed-knot'],
        ],
    ],
    'fuel-consumption' => [
        'standard' => 'L/(100.km)',
        'cldr' => 'consumption',
        'units' => [
            'L/(100.km)' => ['factor' => '1', 'cldr' => 'consumption-liter-per-100-kilometer'],
            'L/km' => ['factor' => '100', 'cldr' => 'consumption-liter-per-kilometer'],
            // 100 km × litres per gallon ÷ kilometres per mile, divided by the miles per gallon.
            '[mi_i]/[gal_us]' => ['factor' => '378.5411784/1.609344', 'reciprocal' => true, 'cldr' => 'consumption-mile-per-gallon'],
            '[mi_i]/[gal_br]' => ['factor' => '454.609/1.609344', 'reciprocal' => true, 'cldr' => 'consumption-mile-per-gallon-imperial'],
            'km/L' => ['factor' => '100', 'reciprocal' => true],
        ],
    ],
    'energy' => [
        'standard' => 'J',
        'cldr' => 'energy',
        'units' => [
            'J' => ['factor' => '1', 'cldr' => 'energy-joule'],
            'kJ' => ['factor' => '1000', 'cldr' => 'energy-kilojoule'],
            'cal' => ['factor' => '4.184', 'cldr' => 'energy-calorie'],
            'kcal' => ['factor' => '4184', 'cldr' => 'energy-kilocalorie'],
            // The calorie of nutrition labels, a kilocalorie.
            '[Cal]' => ['factor' => '4184', 'cldr' => 'energy-foodcalorie'],
            'W.h' => ['factor' => '3600'],
            'kW.h' => ['factor' => '3600000', 'cldr' => 'energy-kilowatt-hour'],
        ],
    ],
    'pressure' => [
        'standard' => 'Pa',
        'cldr' => 'pressure',
        'units' => [
            'Pa' => ['factor' => '1', 'cldr' => 'pressure-pascal'],
            'hPa' => ['factor' => '100', 'cldr' => 'pressure-hectopascal'],
            'kPa' => ['factor' => '1000', 'cldr' => 'pressure-kilopascal'],
            'mbar' => ['factor' => '100', 'cldr' => 'pressure-millibar'],
            'bar' => ['factor' => '100000', 'cldr' => 'pressure-bar'],
            // A pound-force (0.45359237 kg × 9.80665 m/s²) on a square inch (0.00064516 m²).
            '[psi]' => ['factor' => '44482216152605/6451600000', 'cldr' => 'pressure-pound-force-per-square-inch'],
            'mm[Hg]' => ['factor' => '133.322387415', 'cldr' => 'pressure-millimeter-ofhg'],
        ],
    ],
    'power' => [
        'standard' => 'W',
        'cldr' => 'power',
        'units' => [
            'W' => ['factor' => '1', 'cldr' => 'power-watt'],
            'kW' => ['factor' => '1000', 'cldr' => 'power-kilowatt'],
        ],
    ],
];

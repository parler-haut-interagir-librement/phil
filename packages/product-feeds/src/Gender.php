<?php

declare(strict_types=1);

namespace Phil\ProductFeeds;

/**
 * Who an item is made for, by gender: readers ask it of clothing and accessories, with the age group and the size.
 */
enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Unisex = 'unisex';
}

<?php

declare(strict_types=1);

namespace Phil\UxCss\Token;

/**
 * The colour scheme a role is read in: the first or the second value of its `light-dark()`.
 */
enum Scheme: string
{
    case Light = 'light';
    case Dark = 'dark';
}

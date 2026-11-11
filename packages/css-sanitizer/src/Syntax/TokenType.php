<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Syntax;

/**
 * The kinds of token of CSS Syntax Level 3, section 4. Comments are not tokens: the tokenizer drops them.
 */
enum TokenType: string
{
    case Ident = 'ident';
    case Function = 'function';
    case AtKeyword = 'at-keyword';
    case Hash = 'hash';
    case String = 'string';
    case BadString = 'bad-string';
    case Url = 'url';
    case BadUrl = 'bad-url';
    case Delim = 'delim';
    case Number = 'number';
    case Percentage = 'percentage';
    case Dimension = 'dimension';
    case Whitespace = 'whitespace';
    case Cdo = 'CDO';
    case Cdc = 'CDC';
    case Colon = 'colon';
    case Semicolon = 'semicolon';
    case Comma = 'comma';
    case OpenSquare = '[';
    case CloseSquare = ']';
    case OpenParenthesis = '(';
    case CloseParenthesis = ')';
    case OpenCurly = '{';
    case CloseCurly = '}';
    case Eof = 'EOF';
}

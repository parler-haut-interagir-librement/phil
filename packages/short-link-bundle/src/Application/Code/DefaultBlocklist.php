<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application\Code;

/**
 * The words a drawn code avoids unless the application gives its own list: a short list of rude words in English and
 * French, which the codec compares in lower case. An application adds the words of its own languages with the
 * `code.blocklist` option.
 */
final class DefaultBlocklist
{
    public const array WORDS = [
        'anal', 'anus', 'arse', 'ass', 'bastard', 'bitch', 'boob', 'cock', 'crap', 'cul', 'cum', 'cunt', 'damn', 'dick',
        'dildo', 'fag', 'fuck', 'jizz', 'kkk', 'nazi', 'nigg', 'penis', 'piss', 'porn', 'pussy', 'rape', 'sex', 'shit',
        'slut', 'tit', 'twat', 'wank', 'whore',
        'bite', 'bordel', 'branl', 'chatte', 'chier', 'con', 'conne', 'connard', 'couill', 'encul', 'foutre', 'merde',
        'nique', 'pede', 'pute', 'putain', 'salaud', 'salope', 'tabarnak', 'tabarnac', 'calice', 'ostie', 'zob',
    ];
}

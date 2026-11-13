<?php

declare(strict_types=1);

namespace Phil\Epub\Internal;

use Phil\Epub\Direction;
use Phil\Epub\Viewport;

/**
 * The XML documents of the publication other than the package document: the container, the font obfuscation list
 * and the frame of each XHTML document.
 *
 * @internal
 */
final class Xml
{
    public const string DECLARATION = '<?xml version="1.0" encoding="UTF-8"?>'."\n";

    public static function container(string $packagePath): string
    {
        return self::DECLARATION
            .'<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">'."\n"
            .'  <rootfiles>'."\n"
            .'    <rootfile full-path="'.XhtmlSerialization::attribute($packagePath).'" media-type="application/oebps-package+xml"/>'."\n"
            .'  </rootfiles>'."\n"
            .'</container>'."\n";
    }

    /**
     * @param list<string> $paths the obfuscated fonts, from the root of the container
     */
    public static function encryption(array $paths): string
    {
        $data = '';

        foreach ($paths as $path) {
            $data .= '  <enc:EncryptedData>'."\n"
                .'    <enc:EncryptionMethod Algorithm="http://www.idpf.org/2008/embedding"/>'."\n"
                .'    <enc:CipherData><enc:CipherReference URI="'.XhtmlSerialization::attribute($path).'"/></enc:CipherData>'."\n"
                .'  </enc:EncryptedData>'."\n";
        }

        return self::DECLARATION
            .'<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container" xmlns:enc="http://www.w3.org/2001/04/xmlenc#">'."\n"
            .$data
            .'</encryption>'."\n";
    }

    /**
     * An XHTML content document around a body already written as XHTML, with the viewport of its page when it is
     * pre-paginated.
     *
     * @param list<string> $stylesheets the addresses of the stylesheets, relative to the document
     */
    public static function xhtml(string $language, Direction $direction, string $title, array $stylesheets, ?string $type, string $body, ?Viewport $viewport = null): string
    {
        $lang = XhtmlSerialization::attribute($language);
        $links = '';

        foreach ($stylesheets as $href) {
            $links .= '<link rel="stylesheet" type="text/css" href="'.XhtmlSerialization::attribute($href).'"/>'."\n";
        }

        return self::DECLARATION
            .'<!DOCTYPE html>'."\n"
            .'<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" lang="'.$lang.'" xml:lang="'.$lang.'"'
            .(Direction::Default === $direction ? '' : ' dir="'.$direction->value.'"').'>'."\n"
            .'<head>'."\n"
            .'<meta charset="UTF-8"/>'."\n"
            .($viewport instanceof Viewport ? '<meta name="viewport" content="'.$viewport->content().'"/>'."\n" : '')
            .'<title>'.XhtmlSerialization::text($title).'</title>'."\n"
            .$links
            .'</head>'."\n"
            .'<body'.(null === $type ? '' : ' epub:type="'.$type.'"').'>'."\n"
            .$body."\n"
            .'</body>'."\n"
            .'</html>'."\n";
    }
}

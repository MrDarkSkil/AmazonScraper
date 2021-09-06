<?php


namespace AmazonService\Utils;

use DOMElement;
use DOMNodeList;
use Exception;
use DOMDocument;
use DOMXPath;

class DomParserUtils
{
    /**
     * @param  DOMDocument  $dom
     * @param  DOMXPath  $xPath
     * @param  array  $selectors
     * @return DOMElement|DOMNodeList|null
     */
    public static function findParentDiv(DOMDocument $dom, DOMXPath $xPath, array $selectors)
    {
        foreach ($selectors as $selector) {
            switch ($selector[0]) {
                case '#':
                    $selector = str_replace('#', '', $selector);
                    $found = $dom->getElementById($selector);
                    break;
                case '.':
                    $selector = str_replace('.', '', $selector);
                    $found = $xPath->query("//*[contains(@class, '$selector')]");
                    break;
                default:
                    throw new \Error('Filter was not found.');
            }

            if ($found instanceof DOMNodeList && $found->count() > 0 || $found instanceof DOMElement && $found->textContent !== '') {
                return $found;
            }
        }
        return null;
    }
}

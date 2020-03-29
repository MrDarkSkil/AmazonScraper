<?php


namespace AmazonService\Utils;

use Exception;
use PHPHtmlParser\Dom;

class DomParserUtils
{
    /**
     * @param Dom $dom
     * @param array $selectors
     * @return Dom\Collection|null
     */
    public static function findParentDiv(Dom $dom, array $selectors)
    {
        try {
            $parentDiv = null;
            foreach ($selectors as $selector) {
                $parentDiv = $dom->find($selector);
                if ($parentDiv && $parentDiv->count() !== 0) {
                    break;
                }
            }
        } catch (Exception $exception) {
            return null;
        }
        if (!$parentDiv || $parentDiv->count() === 0) {
            return null;
        }
        return $parentDiv;
    }
}
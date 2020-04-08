<?php


namespace AmazonService\Exceptions;


class CouldNotProcessProduct extends AmazonServiceException
{
    /**
     * @param string $want
     * @param array|string $divName
     * @param string $userAgent
     * @return static
     */
    public static function parentDivNotFound(string $want, $divName, string $userAgent = null)
    {
        if (is_array($divName)) {
            $divName = implode(',', $divName);
        }
        return new static("Could not find '$want' parentDiv with '$divName' selectors in html structure.", $userAgent);
    }

    /**
     * @param string $want
     * @param $divName
     * @param $childDiv
     * @param string|null $userAgent
     * @return static
     */
    public static function childDivNotFound(string $want, $divName, $childDiv, string $userAgent = null)
    {
        if (is_array($divName)) {
            $divName = implode(',', $divName);
        }
        return new static("Could not find '$want' childDiv with parentDiv = '$divName' | childDiv = '$childDiv' selectors in html structure.", $userAgent);
    }

    /**
     * @param string|null $userAgent
     * @return static
     */
    public static function noCategoriesFound(string $userAgent = null)
    {
        return new static("No categories found.", $userAgent);
    }

    /**
     * @param string $url
     * @param string|null $userAgent
     * @return static
     */
    public static function cannotRetrieveCategoryNameWithUrl(string $url, string $userAgent = null)
    {
        return new static("Could not find category name from this url '$url'.", $userAgent);
    }


}
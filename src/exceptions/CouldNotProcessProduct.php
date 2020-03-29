<?php


namespace AmazonService\Exceptions;


class CouldNotProcessProduct extends AmazonServiceException
{
    /**
     * @param string $want
     * @param array|string $divName
     * @return static
     */
    public static function parentDivNotFound(string $want, $divName)
    {
        if (is_array($divName)) {
            $divName = implode(',', $divName);
        }
        return new static("Could not find '$want' parentDiv with '$divName' selectors in html structure.");
    }

    /**
     * @param string $want
     * @param $divName
     * @param $childDiv
     * @return static
     */
    public static function childDivNotFound(string $want, $divName, $childDiv)
    {
        if (is_array($divName)) {
            $divName = implode(',', $divName);
        }
        return new static("Could not find '$want' childDiv with parentDiv = '$divName' | childDiv = '$childDiv' selectors in html structure.");
    }

    /**
     * @return static
     */
    public static function noCategoriesFound()
    {
        return new static("No categories found.");
    }

    /**
     * @param string $url
     * @return static
     */
    public static function cannotRetrieveCategoryNameWithUrl(string $url)
    {
        return new static("Could not find category name from this url '$url'.");
    }


}
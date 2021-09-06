<?php


namespace AmazonService\Exceptions;

use Exception;
use Throwable;

class AmazonServiceException extends Exception
{
    /**
     * @var string $userAgent
     */
    private $userAgent;

    public function __construct($message = "", $userAgent = null, $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->userAgent = $userAgent;
    }

    /**
     * @return string
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    /**
     * @param string $userAgent
     * @return AmazonServiceException
     */
    public function setUserAgent(string $userAgent): AmazonServiceException
    {
        $this->userAgent = $userAgent;
        return $this;
    }


}

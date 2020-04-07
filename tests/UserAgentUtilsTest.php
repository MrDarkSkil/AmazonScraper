<?php

namespace AmazonService\Tests;

use AmazonService\Utils\UserAgentUtils;
use PHPUnit\Framework\TestCase;

class UserAgentUtilsTest extends TestCase
{
    /**
     * @test
     */
    public function get_random_user_agent_function_must_return_randomly_user_agent()
    {
        $userAgent = UserAgentUtils::getRandomUserAgent();

        $this->assertNotNull($userAgent);
        $this->assertIsString($userAgent);
    }
}

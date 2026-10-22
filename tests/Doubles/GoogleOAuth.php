<?php

declare(strict_types=1);

namespace Buddy\Repman\Tests\Doubles;

use GuzzleHttp\Psr7\Response;
use Psr\Container\ContainerInterface;

final class GoogleOAuth
{
    /**
     * @param mixed[] $userInfo claims returned by the userinfo endpoint
     */
    public static function mockTokenResponse(array $userInfo, ContainerInterface $container): void
    {
        $container->get(HttpClientStub::class)->setNextResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"access_token":"ya29.test-token","token_type":"Bearer","expires_in":3599}'),
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($userInfo + ['sub' => '1234567890'])),
        ]);
    }
}

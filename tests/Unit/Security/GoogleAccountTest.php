<?php

declare(strict_types=1);

namespace Buddy\Repman\Tests\Unit\Security;

use Buddy\Repman\Security\GoogleAccount;
use KnpU\OAuth2ClientBundle\Client\Provider\GoogleClient;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

final class GoogleAccountTest extends TestCase
{
    public function testRefuseEveryAccountWhenNoWorkspaceDomainIsConfigured(): void
    {
        $account = new GoogleAccount(new GoogleClient(new Google(), new RequestStack()), '');

        $this->expectException(IdentityProviderException::class);
        $this->expectExceptionMessage('Google sign-in requires OAUTH_GOOGLE_HOSTED_DOMAIN.');

        $account->email(new AccessToken(['access_token' => 'token']));
    }
}

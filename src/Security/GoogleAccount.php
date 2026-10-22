<?php

declare(strict_types=1);

namespace Buddy\Repman\Security;

use KnpU\OAuth2ClientBundle\Client\Provider\GoogleClient;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;

final class GoogleAccount
{
    private GoogleClient $client;
    private string $hostedDomain;

    public function __construct(GoogleClient $client, string $hostedDomain)
    {
        $this->client = $client;
        $this->hostedDomain = \mb_strtolower($hostedDomain);
    }

    /**
     * Users are matched by email, so only an address the Workspace domain vouches for is
     * accepted: a consumer Google account keeps its address after the mailbox is reassigned,
     * even with email_verified set.
     *
     * @throws IdentityProviderException
     */
    public function email(AccessToken $token): string
    {
        if ($this->hostedDomain === '') {
            throw new IdentityProviderException('Google sign-in requires OAUTH_GOOGLE_HOSTED_DOMAIN.', 0, []);
        }

        $user = $this->client->fetchUserFromToken($token);
        if (!$user instanceof GoogleUser) {
            throw new IdentityProviderException('Unexpected Google resource owner.', 0, []);
        }

        $email = \mb_strtolower((string) $user->getEmail());
        if (
            $user->getEmailVerified() !== true
            || \mb_strtolower((string) $user->getHostedDomain()) !== $this->hostedDomain
            || \mb_substr($email, -\mb_strlen('@'.$this->hostedDomain)) !== '@'.$this->hostedDomain
        ) {
            throw new IdentityProviderException(\sprintf('Only verified %s Google Workspace accounts can sign in.', $this->hostedDomain), 0, []);
        }

        return $email;
    }
}

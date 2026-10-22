<?php

declare(strict_types=1);

namespace Buddy\Repman\Security;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Exception\IdentityProviderAuthenticationException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class GoogleAuthenticator extends OAuthAuthenticator
{
    private GoogleAccount $googleAccount;

    public function __construct(ClientRegistry $clientRegistry, GoogleAccount $googleAccount, RouterInterface $router, UserProvider $userProvider)
    {
        $this->clientRegistry = $clientRegistry;
        $this->googleAccount = $googleAccount;
        $this->userProvider = $userProvider;
        $this->router = $router;
    }

    public function supports(Request $request): bool
    {
        return $request->attributes->get('_route') === 'login_google_check';
    }

    public function authenticate(Request $request): PassportInterface
    {
        try {
            $email = $this->googleAccount->email($this->fetchAccessToken($this->clientRegistry->getClient('google'), $request->attributes->get('_route')));
        } catch (IdentityProviderException $e) {
            throw new IdentityProviderAuthenticationException($e);
        }
        $user = $this->userProvider->loadUserByIdentifier($email);

        return new SelfValidatingPassport(new UserBadge($email, function () use ($user): UserInterface {
            return $user;
        }));
    }
}

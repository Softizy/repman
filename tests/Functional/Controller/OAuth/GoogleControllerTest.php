<?php

declare(strict_types=1);

namespace Buddy\Repman\Tests\Functional\Controller\OAuth;

use Buddy\Repman\Tests\Doubles\GoogleOAuth;
use Buddy\Repman\Tests\Functional\FunctionalTestCase;

final class GoogleControllerTest extends FunctionalTestCase
{
    public function testSuccessfulLoginWithWorkspaceAccount(): void
    {
        $this->fixtures->createOAuthUser($email = 'test@buddy.works');

        $this->loginWithGoogle(['email' => $email, 'email_verified' => true, 'hd' => 'buddy.works']);

        self::assertTrue($this->client->getResponse()->isRedirect($this->urlTo('index')));
        $this->client->followRedirect();
        self::assertStringContainsString($email, $this->lastResponseBody());
    }

    public function testRejectLoginFromConsumerAccountHoldingWorkspaceAddress(): void
    {
        $this->fixtures->createOAuthUser($email = 'test@buddy.works');

        $this->loginWithGoogle(['email' => $email, 'email_verified' => true]);

        $this->assertLoginRefused();
    }

    public function testRejectLoginFromAnotherWorkspaceDomain(): void
    {
        $this->fixtures->createOAuthUser($email = 'test@buddy.works');

        $this->loginWithGoogle(['email' => $email, 'email_verified' => true, 'hd' => 'evil.example']);

        $this->assertLoginRefused();
    }

    public function testRejectLoginWithUnverifiedEmail(): void
    {
        $this->fixtures->createOAuthUser($email = 'test@buddy.works');

        $this->loginWithGoogle(['email' => $email, 'email_verified' => false, 'hd' => 'buddy.works']);

        $this->assertLoginRefused();
    }

    public function testRegisterWithWorkspaceAccount(): void
    {
        $this->client->request('GET', $this->urlTo('register_google_start'));
        $params = $this->getQueryParamsFromLastResponse();

        $this->client->disableReboot();
        GoogleOAuth::mockTokenResponse(['email' => 'new@buddy.works', 'email_verified' => true, 'hd' => 'buddy.works'], $this->container());
        $this->client->request('GET', $this->urlTo('register_google_check', ['state' => $params['state'], 'code' => 'secret-token']));

        self::assertTrue($this->client->getResponse()->isRedirect($this->urlTo('organization_create', ['origin' => 'google'])));
        $this->client->followRedirect();
        self::assertStringContainsString('Your account has been created', $this->lastResponseBody());
    }

    public function testRegisterRejectsConsumerAccount(): void
    {
        $this->client->request('GET', $this->urlTo('register_google_start'));
        $params = $this->getQueryParamsFromLastResponse();

        $this->client->disableReboot();
        GoogleOAuth::mockTokenResponse(['email' => 'new@buddy.works', 'email_verified' => true], $this->container());
        $this->client->request('GET', $this->urlTo('register_google_check', ['state' => $params['state'], 'code' => 'secret-token']));

        self::assertTrue($this->client->getResponse()->isRedirect($this->urlTo('app_register')));
        $this->client->followRedirect();
        self::assertStringContainsString('Only verified buddy.works Google Workspace accounts can sign in.', $this->lastResponseBody());
    }

    /**
     * @param mixed[] $userInfo
     */
    private function loginWithGoogle(array $userInfo): void
    {
        $this->client->request('GET', $this->urlTo('auth_google_start'));
        $params = $this->getQueryParamsFromLastResponse();

        $this->client->disableReboot();
        GoogleOAuth::mockTokenResponse($userInfo, $this->container());
        $this->client->request('GET', $this->urlTo('login_google_check', ['state' => $params['state'], 'code' => 'secret-token']));
    }

    private function assertLoginRefused(): void
    {
        self::assertTrue($this->client->getResponse()->isRedirect($this->urlTo('app_login')));
        $this->client->followRedirect();
        self::assertStringContainsString('Only verified buddy.works Google Workspace accounts can sign in.', $this->lastResponseBody());

        $this->client->request('GET', $this->urlTo('user_profile'));
        self::assertTrue($this->client->getResponse()->isRedirect($this->urlTo('app_login')));
    }

    /**
     * @return mixed[]
     */
    private function getQueryParamsFromLastResponse(): array
    {
        parse_str((string) parse_url((string) $this->client->getResponse()->headers->get('location'), PHP_URL_QUERY), $params);

        return $params;
    }
}

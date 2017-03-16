<?php

namespace Tests\Strut\StrutBundle;

use Strut\UserBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

abstract class StrutTestCase extends WebTestCase
{
	/** @var Client */
    private $client = null;

    public function getClient(): Client
    {
        return $this->client;
    }

    public function setUp()
    {
        parent::setUp();

        $this->client = static::createClient();
    }

    /**
     * Login a user without making a HTTP request.
     * If we make a HTTP request we lose ability to mock service in the container.
     *
     * @param string $username User to log in
     */
    public function logInAs($username)
    {
        $container = $this->client->getContainer();
        $session = $container->get('session');

        $userManager = $container->get('fos_user.user_manager');
        $loginManager = $container->get('fos_user.security.login_manager');
        $firewallName = $container->getParameter('fos_user.firewall_name');

        /** @var User $user */
        $user = $userManager->findUserBy(['username' => $username]);
        $loginManager->loginUser($firewallName, $user);

        $session->set('_security_'.$firewallName, serialize($container->get('security.token_storage')->getToken()));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $this->client->getCookieJar()->set($cookie);
    }

    /**
     * Instead of `logInAs` this method use a HTTP request to log in the user.
     * Could be better for some tests.
     *
     * @param string $username User to log in
     */
    public function logInAsUsingHttp($username)
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('button[type=submit]')->form();
        $data = [
            '_username' => $username,
            '_password' => 'mypassword',
        ];

        $this->client->submit($form, $data);
    }

    /**
     * Return the user id of the logged in user.
     * You should be sure that you called `logInAs` before.
     *
     * @return int
     */
    public function getLoggedInUserId()
    {
        $token = static::$kernel->getContainer()->get('security.token_storage')->getToken();

        if (null !== $token) {
            return $token->getUser()->getId();
        }

        throw new \RuntimeException('No logged in User.');
    }

	/**
	 * Return the user id of the logged in user.
	 * You should be sure that you called `logInAs` before.
	 *
	 * @return int
	 */
	public function getLoggedInUser()
	{
		$token = static::$kernel->getContainer()->get('security.token_storage')->getToken();

		if (null !== $token) {
			return $token->getUser();
		}

		throw new \RuntimeException('No logged in User.');
	}
}

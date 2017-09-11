<?php

namespace GoldenPlanet\Silex\Obb\App\Controller;

use GoldenPlanet\Gpp\App\Installer\AuthorizeHandler;
use GoldenPlanet\Gpp\App\Installer\UninstalledSuccess;
use Silex\Application;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeController
{

    /**
     * @var AuthorizeHandler
     */
    private $authHandler;
    /**
     * @var EventDispatcher
     */
    private $dispatcher;

    public function __construct(AuthorizeHandler $authHandler, EventDispatcher $dispatcher)
    {
        $this->authHandler = $authHandler;
        $this->dispatcher = $dispatcher;
    }

    public function authorizeAction(Request $request, Application $app)
    {
        $shop = $request->query->get('shop');
        $code = $request->query->get('code');
        $isSecure = $request->query->get('https', 0);
        $proto = $isSecure ? 'https' : 'http';

        if (!preg_match('#^[a-z0-9.-]+$#', $shop)) {
            throw new \InvalidArgumentException('Invalid shop value');
        }

        if ($shop && !$code) {
            $app['monolog']->addDebug('first round');
            // Step 1: get the shopname from the user and redirect the user to the
            // obb authorization page where they can choose to authorize this app

            $bytes = random_bytes(24);
            $state = bin2hex($bytes);

            $url = $this->authHandler->generateAuthorizeUrl($shop, $state, $proto);

            $app['session']->set('shop', $shop);
            $app['session']->set('state', $state);

            // redirect to authorize url
            return new RedirectResponse($url);
        } elseif ($code) {
            // Step 2: do a form POST to get the access token
            /** @var \Symfony\Component\HttpFoundation\Session\Session $session */
            $session = $app['session'];
            $state = $request->query->get('state');
            if (!$state || $state !== $session->get('state')) {
                throw new \InvalidArgumentException('State for this request is incorrect');
            }

            $session->clear();
            $token = $this->authHandler->token($shop, $code, $proto);

            // Now, request the token and store it in your session.
            return new RedirectResponse($proto . '://' . $shop . '/admin/apps/');
        } else {
            return new Response('Invalid request');
        }
    }

    public function unAuthorizeAction(Request $request, Application $app)
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data)) {
            return new Response('Bad request', 404);
        }

        $event = new UninstalledSuccess($data);
        $this->dispatcher->dispatch('app.uninstalled', $event);
        $app['monolog']->addDebug('removing app');

        return new Response('Success');
    }

    public function pingAction(Request $request, Application $app) {
        $app['db']->fetchAll('SELECT COUNT(*) FROM sessions');

        return new Response('OK');
	}
}

<?php

namespace GoldenPlanet\Silex\Obb\App\Controller;

use GoldenPlanet\Silex\Obb\App\AuthorizeHandler;
use Silex\Application;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeController
{

    /**
     * @var AuthorizeHandler
     */
    private $authHandler;

    public function __construct(AuthorizeHandler $authHandler)
    {
        $this->authHandler = $authHandler;
    }

    public function authorizeAction(Request $request, Application $app)
    {
        $shop = $request->query->get('shop');
        $code = $request->query->get('code');
        $proto = $request->query->get('proto', 'http');

        if ($proto != 'http' && $proto != 'https') {
            throw new \InvalidArgumentException('Invalid proto value');
        }

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
            $token = $this->authHandler->token($shop, $code);

            // Now, request the token and store it in your session.
            $session->set('token', $token);

            return new RedirectResponse('http://' . $shop . '/admin/apps/');
        } else {
            return new Response('Invalid request');
        }
    }
}

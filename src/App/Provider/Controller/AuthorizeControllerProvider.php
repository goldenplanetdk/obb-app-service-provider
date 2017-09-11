<?php

namespace GoldenPlanet\Silex\Obb\App\Provider\Controller;

use Silex\Api\ControllerProviderInterface;
use Silex\Application;

class AuthorizeControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/auth/obb', 'authorize.controller:authorizeAction');
        $controllers->post('/auth/obb/unauthorize', 'authorize.controller:unAuthorizeAction');
        $controllers->get('/ping', 'authorize.controller:pingAction');
        return $controllers;
    }
}

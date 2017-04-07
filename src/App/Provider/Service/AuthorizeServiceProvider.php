<?php

namespace GoldenPlanet\Silex\Obb\App\Provider\Service;

use GoldenPlanet\Silex\Obb\App\AuthorizeHandler;
use GoldenPlanet\Silex\Obb\App\Controller\AuthorizeController;
use GoldenPlanet\Silex\Obb\App\CurlHttpClient;
use GoldenPlanet\Silex\Obb\App\Provider\Controller\AuthorizeControllerProvider;
use GoldenPlanet\Silex\Obb\App\Validator\HmacValidator;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\BootableProviderInterface;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class AuthorizeServiceProvider implements ServiceProviderInterface, BootableProviderInterface
{

    /**
     * @inheritdoc
     */
    public function register(Container $app)
    {
        $app['authorize.controller'] = function () use ($app) {
            return new AuthorizeController($app['authorize.handler']);
        };

        $app['authorize.handler'] = function ($app) {
            return new AuthorizeHandler(
                $app['dispatcher'],
                $app['http.client'],
                $app['api.app_key'],
                $app['api.app_secret'],
                $app['api.app_scope'],
                $app['app.redirect_url']
            );
        };

        $app['http.client'] = function () {
            return new CurlHttpClient();
        };

        $app['validator.hmac'] = function ($app) {
            return new HmacValidator($app['api.app_secret']);
        };

        // init defaults from ENV
        $app['api.app_key'] = $_SERVER['API_KEY'] ?? '';
        $app['api.app_secret'] = $_SERVER['API_SECRET'] ?? '';
        $app['app.redirect_url'] = $_SERVER['APP_REDIRECT_URL'] ?? '';
        $app['api.app_scope'] = 'read_products';

        $app->before(function (Request $request, Application $app) {
            $queryString = $request->server->get('QUERY_STRING');
            $validator = $app['validator.hmac'];
            $validator->validate($queryString);
        });
    }

    /**
     * @inheritdoc
     */
    public function boot(Application $app)
    {
        $app->mount('/', new AuthorizeControllerProvider());
    }
}

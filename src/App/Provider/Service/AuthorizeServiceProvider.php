<?php

namespace GoldenPlanet\Silex\Obb\App\Provider\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use GoldenPlanet\Silex\Obb\App\Api\StoreApiFactory;
use GoldenPlanet\Silex\Obb\App\AuthorizeHandler;
use GoldenPlanet\Silex\Obb\App\Controller\AuthorizeController;
use GoldenPlanet\Silex\Obb\App\CurlHttpClient;
use GoldenPlanet\Silex\Obb\App\Provider\Controller\AuthorizeControllerProvider;
use GoldenPlanet\Silex\Obb\App\Validator\HmacValidator;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\BootableProviderInterface;
use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use GoldenPlanet\Silex\Obb\App\Install\InstallSuccessListener;

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

        $app->register(new DoctrineServiceProvider(), array(
            'db.options' => array(
                'url' => $_SERVER['DATABASE_URL'] ?? 'postgres://ebrjlpiwhlyvyf:4252c2170228af3a9ff3293e7b3a647a86b0eef00cfd9e29376b2a7e329ecf62@ec2-54-247-99-159.eu-west-1.compute.amazonaws.com:5432/d9fra4qrmk1n9u',
            ),
        ));

        // init defaults from ENV
        $app['api.app_key'] = $_SERVER['API_KEY'] ?? '';
        $app['api.app_secret'] = $_SERVER['API_SECRET'] ?? '';
        $app['app.redirect_url'] = $_SERVER['APP_REDIRECT_URL'] ?? '';

        $app->before(function (Request $request, Application $app) {
            $queryString = $request->server->get('QUERY_STRING');
            $validator = $app['validator.hmac'];
            $validator->validate($queryString);
        });

        // db init
        /** @var AbstractSchemaManager $sm */
        $sm = $app['db']->getSchemaManager();
        $tables = $sm->listTables();
        $isSchemaPresent = false;
        foreach ($tables as $table) {
            if ($table->getName() == 'installations') {
                $isSchemaPresent = true;
            }
        }

        $connection = $app['db'];
        if (!$isSchemaPresent) {
            $schema = new \Doctrine\DBAL\Schema\Schema();
            $myTable = $schema->createTable("installations");
            $myTable->addColumn("id", "integer", array("unsigned" => true, 'autoincrement' => true));
            $myTable->addColumn("shop", "string", array("length" => 256));
            $myTable->addColumn("token", "string", ['notnull' => true]);
            $myTable->addColumn("is_secure_protocol", "boolean");
            $myTable->addColumn("created_at", "datetime", ['default' => 'CURRENT_TIMESTAMP']);
            $myTable->setPrimaryKey(array("id"));
            $queries = $schema->toSql($app['db']->getDatabasePlatform());
            /** @var Connection $connection */
            $connection->exec($queries[0]);
        }

        $app['store.api.factory'] = function ($app) {
            return new StoreApiFactory($app['db'], $app['http.client']);
        };
    }

    /**
     * @inheritdoc
     */
    public function boot(Application $app)
    {
        $app->mount('/', new AuthorizeControllerProvider());

        $dispatcher = $app['dispatcher'];

        $params = parse_url($app['app.redirect_url']);
        $backUrl = $params['scheme'] . "://" . $params['host'] . (isset($params['port']) ? ':' . $params['port'] : '') . '/auth/obb/unauthorize';

        $connection = $app['db'];
        $listener = new InstallSuccessListener($connection, $app['store.api.factory'], $backUrl);
        $dispatcher->addListener('app.installation.success', [$listener, 'onSuccess'], -100);
    }
}

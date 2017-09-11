<?php

namespace GoldenPlanet\Silex\Obb\App\Provider\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use GoldenPlanet\Gpp\App\Installer\Api\StoreApiFactory;
use GoldenPlanet\Gpp\App\Installer\AuthorizeHandler;
use GoldenPlanet\Gpp\App\Installer\CurlHttpClient;
use GoldenPlanet\Gpp\App\Installer\Install\InstallSuccessListener;
use GoldenPlanet\Gpp\App\Installer\Uninstall\UninstallSuccessListener;
use GoldenPlanet\Gpp\App\Installer\Validator\HmacValidator;
use GoldenPlanet\Gpp\App\Installer\Validator\WebhookValidator;
use GoldenPlanet\Silex\Obb\App\Controller\AuthorizeController;
use GoldenPlanet\Silex\Obb\App\Provider\Controller\AuthorizeControllerProvider;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\BootableProviderInterface;
use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

class AuthorizeServiceProvider implements ServiceProviderInterface, BootableProviderInterface
{

    /**
     * @inheritdoc
     */
    public function register(Container $app)
    {
        $app['authorize.controller'] = function () use ($app) {
            return new AuthorizeController($app['authorize.handler'], $app['dispatcher']);
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

        $app['validator.webhook'] = function ($app) {
            return new WebhookValidator($app['api.app_secret']);
        };




        // init defaults from ENV
        $app['api.app_key'] = $_SERVER['API_KEY'] ?? '';
        $app['api.app_secret'] = $_SERVER['API_SECRET'] ?? '';
        $app['api.app_scope'] = $_SERVER['API_SCOPE'] ?? '';
        $app['app.redirect_url'] = $_SERVER['APP_REDIRECT_URL'] ?? '';

        $app->before(function (Request $request, Application $app) {
            if ($request->getRequestUri() == '/ping') { // no validation for ping
                return;
            }
            if ($request->headers->get('X-OBB-Signature')) {
                // webhook validation
                $validator = $app['validator.webhook'];
                $payload = $request->getContent();
                $validator->validate($payload, $request->headers->get('X-OBB-SIGNATURE'));
            } else {
                // hmac validation
                $queryString = $request->server->get('QUERY_STRING');
                $validator = $app['validator.hmac'];
                $validator->validate($queryString);

            }
        });

        $app->register(new SessionServiceProvider());

        $app['session.storage.handler'] = function () use ($app) {
            return new PdoSessionHandler(
                $app['db']->getWrappedConnection()
            );
        };

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
        $url = $app['db.url'] ?? ($_SERVER['DATABASE_URL'] ?? '');
        $app->register(new DoctrineServiceProvider(), array(
            'db.options' => array(
                'url' => $url,
            ),
        ));

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
            $myTable->addColumn("created_at", "datetime");
            $myTable->setPrimaryKey(array("id"));
            $queries = $schema->toSql($app['db']->getDatabasePlatform());
            /** @var Connection $connection */
            $connection->exec($queries[0]);
        }

        $dispatcher = $app['dispatcher'];

        $params = parse_url($app['app.redirect_url']);
        $backUrl = $params['scheme'] . "://" . $params['host'] . (isset($params['port']) ? ':' . $params['port'] : '') . '/auth/obb/unauthorize';

        $connection = $app['db'];
        $listener = new InstallSuccessListener($connection, $app['store.api.factory'], $backUrl);
        $dispatcher->addListener('app.installation.success', [$listener, 'onSuccess'], 100);

        $listener = new UninstallSuccessListener($connection);
        $dispatcher->addListener('app.uninstalled', [$listener, 'onSuccess'], 100);
    }
}

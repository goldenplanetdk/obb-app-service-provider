<?php

namespace GoldenPlanet\Silex\Obb\App\Install;

use Doctrine\DBAL\Connection;
use GoldenPlanet\Silex\Obb\App\Api\StoreApiFactory;
use GoldenPlanet\Silex\Obb\App\InstallationSuccess;

class InstallSuccessListener
{
    /**
     * @var Connection
     */
    private $connection;
    /**
     * @var StoreApiFactory
     */
    private $api;
    private $backUrl;

    /**
     * InstallSuccessListener constructor.
     * @param Connection $connection
     * @param StoreApiFactory $api
     * @param $backUrl
     */
    public function __construct(Connection $connection, StoreApiFactory $api, $backUrl)
    {
        $this->connection = $connection;
        $this->api = $api;
        $this->backUrl = $backUrl;
    }

    public function onSuccess(InstallationSuccess $event) {
        $data = [
            'shop' => $event->shop(),
            'token' => $event->token(),
            'is_secure_protocol' => (int) ($event->protocol() == 'https'),
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];
        $this->connection->insert('installations', $data);

        $client = $this->api->createClient($event->shop());

        $data = [
            'url' => $this->backUrl,
            'event_name' => 'app.uninstalled',
        ];
        $client->call('POST', '/api/v1/webhooks', $data);
    }
}

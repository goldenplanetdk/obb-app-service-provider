<?php

namespace GoldenPlanet\Silex\Obb\App\Uninstall;

use Doctrine\DBAL\Connection;
use GoldenPlanet\Silex\Obb\App\UninstalledSuccess;

class UninstallSuccessListener
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * InstallSuccessListener constructor.
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function onSuccess(UninstalledSuccess $event) {
        $this->connection->delete('installations', ['shop' => $event->domain()]);
    }
}

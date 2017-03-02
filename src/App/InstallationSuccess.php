<?php


namespace GoldenPlanet\Silex\Obb\App;


class InstallationSuccess
{

    private $shop;
    private $token;

    public function __construct($shop, $token)
    {
        $this->shop = $shop;
        $this->token = $token;
    }

    /**
     * @return mixed
     */
    public function shop()
    {
        return $this->shop;
    }

    /**
     * @return mixed
     */
    public function token()
    {
        return $this->token;
    }
}

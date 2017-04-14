<?php

namespace GoldenPlanet\Silex\Obb\App;

interface Client
{
    public function request($method, $url, $options);
}

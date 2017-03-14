<?php

namespace GoldenPlanet\Silex\Obb\App\Validator;

class HmacValidator
{

    private $secret;

    public function __construct($secret)
    {
        $this->secret = $secret;
    }

    public function validate($hmac, $queryString)
    {
        // validate hmac
        if (!$hmac) {
            throw new \InvalidArgumentException('Invalid hmac value');
        }


        parse_str($queryString, $data);
        unset($data['hmac']);

        if (hash_hmac('sha256', http_build_query($data), $this->secret) !== $hmac) {
            throw new \InvalidArgumentException('Hmac verification failed');
        }
    }
}

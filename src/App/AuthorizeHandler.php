<?php

namespace GoldenPlanet\Silex\Obb\App;

use Symfony\Component\EventDispatcher\EventDispatcher;

class AuthorizeHandler
{
    const ACCESS_TOKEN_URL = 'oauth/v2/token';
    const AUTHORIZE_URL = 'admin/oauth/v2/authorize';

    private $apiKey;
    private $secret;
    private $scope;
    private $redirectUrl;
    private $client;

    /**
     * @var EventDispatcher
     */
    private $dispatcher;

    public function __construct(EventDispatcher $dispatcher, Client $client, $apiKey, $secret, $scope, $redirectUrl)
    {
        $this->apiKey = $apiKey;
        $this->secret = $secret;
        $this->scope = $scope;
        $this->redirectUrl = $redirectUrl;
        $this->client = $client;
        $this->dispatcher = $dispatcher;
    }

    // Get the URL required to request authorization
    public function generateAuthorizeUrl($domain, $state, $protocol = 'http')
    {
        $queryString = [
            'response_type' => 'code',
            'client_id' => $this->apiKey,
            'scope' => $this->scope,
            'state' => $state,
        ];

        if ($this->redirectUrl != '') {
            $queryString['redirect_uri'] = $this->redirectUrl;
        }

        return $protocol . '://' . $domain . '/' . self::AUTHORIZE_URL . '?' . http_build_query($queryString);
    }

    public function token($domain, $code, $protocol = 'http')
    {
        $url = $protocol . '://' . $domain . '/' . self::ACCESS_TOKEN_URL;

        $payload = [
            'client_id' => $this->apiKey,
            'client_secret' => $this->secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUrl,
        ];
        $response = $this->client->request('POST', $url, ['form_params' => $payload]);
        $token = json_decode($response, true);

        if (isset($token['access_token'])) {
            $event = new InstallationSuccess($domain, $token['access_token'], $protocol);
            $this->dispatcher->dispatch('app.installation.success', $event);
            return $token['access_token'];
        } else {
            throw new \InvalidArgumentException(sprintf('Token structure is wrong %s', $response));
        }
    }
}

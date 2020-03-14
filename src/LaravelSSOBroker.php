<?php

namespace Zefy\LaravelSSO;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Zefy\LaravelSSO\Exceptions\MissingConfigurationException;
use Zefy\SimpleSSO\SSOBroker;
use GuzzleHttp;

/**
 * Class SSOBroker. This class is only a skeleton.
 * First of all, you need to implement abstract functions in your own class.
 * Secondly, you should create a page which will be your SSO server.
 *
 * @package Zefy\SimpleSSO
 */
class LaravelSSOBroker extends SSOBroker
{
    /**
     * Generate request url.
     *
     * @param string $command
     * @param array $parameters
     *
     * @return string
     */
    protected function generateCommandUrl(string $command, array $parameters = [])
    {
        $query = '';
        if (!empty($parameters)) {
            $query = '?' . http_build_query($parameters);
        }

        return $this->ssoServerUrl . '/api/sso/' . $command . $query;
    }

    /**
     * Set base class options (sso server url, broker name and secret, etc).
     *
     * @return void
     *
     * @throws MissingConfigurationException
     */
    protected function setOptions()
    {
        $this->ssoServerUrl = config('laravel-sso.serverUrl', null);
        $this->brokerName = config('laravel-sso.brokerName', null);
        $this->brokerSecret = config('laravel-sso.brokerSecret', null);

        if (!$this->ssoServerUrl || !$this->brokerName || !$this->brokerSecret) {
            throw new MissingConfigurationException('Missing configuration values.');
        }
    }

    /**
     * Save unique client token to cookie.
     *
     * @return void
     */
    protected function saveToken()
    {
        if (isset($this->token) && $this->token) {
            return;
        }
    
        if ($this->token = Cookie::get($this->getCookieName(), null)) {
            return;
        }

        // If cookie token doesn't exist, we need to create it with unique token...
        $this->token = Str::random(40);
        Cookie::queue(Cookie::make($this->getCookieName(), $this->token, 60));

        // ... and attach it to broker session in SSO server.
        $this->attach();
    }

    /**
     * Delete saved unique client token.
     *
     * @return void
     */
    protected function deleteToken()
    {
        $this->token = null;
        Cookie::forget($this->getCookieName());
    }

    /**
     * Make request to SSO server.
     *
     * @param string $method Request method 'post' or 'get'.
     * @param string $command Request command name.
     * @param array $parameters Parameters for URL query string if GET request and form parameters if it's POST request.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @return array
     */
    protected function makeRequest(string $method, string $command, array $parameters = [])
    {
        $commandUrl = $this->generateCommandUrl($command);

        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '. $this->getSessionId(),
        ];

        switch ($method) {
            case 'POST':
                $body = ['form_params' => $parameters];
                break;
            case 'GET':
                $body = ['query' => $parameters];
                break;
            default:
                $body = [];
                break;
        }

        $client = new GuzzleHttp\Client;
        $response = $client->request($method, $commandUrl, $body + ['headers' => $headers]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Redirect client to specified url.
     *
     * @param string $url URL to be redirected.
     * @param array $parameters HTTP query string.
     * @param int $httpResponseCode HTTP response code for redirection.
     *
     * @return void
     */
    protected function redirect(string $url, array $parameters = [], int $httpResponseCode = 307)
    {
        $query = '';
        // Making URL query string if parameters given.
        if (!empty($parameters)) {
            $query = '?';

            if (parse_url($url, PHP_URL_QUERY)) {
                $query = '&';
            }

            $query .= http_build_query($parameters);
        }

        app()->abort($httpResponseCode, '', ['Location' => $url . $query]);
    }

    /**
     * Getting current url which can be used as return to url.
     *
     * @return string
     */
    protected function getCurrentUrl()
    {
        return url()->full();
    }

    /**
     * Cookie name in which we save unique client token.
     *
     * @return string
     */
    protected function getCookieName()
    {
        // Cookie name based on broker's name because there can be some brokers on same domain
        // and we need to prevent duplications.
        return 'sso_token_' . preg_replace('/[_\W]+/', '_', strtolower($this->brokerName));
    }


    /**
     * @param $returnUrl
     * @return mixed
     */
    public function redirectToSsoServer( $returnUrl)
    {

        $sessionId = base64_encode($this->getSessionId());

        $url = $this->generateCommandUrl('brokers/login/'. $sessionId, [ 'return_url' => $returnUrl ]);

        $headers = [
            'Authorization' => 'Bearer '. $sessionId,
        ];

        return redirect()->away($url, 307, $headers);

    }

    /**
     * Login client to SSO server with user credentials.
     *
     * @param string $username
     * @param string $password
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @return bool
     */
    public function login(string $username, string $password)
    {
        $this->userInfo = $this->makeRequest('POST', 'login', compact('username', 'password'));

        Log::debug('user info'. json_encode($this->userInfo), [
            'credentials' => compact('username', 'password')
        ]);

        if (!isset($this->userInfo['error']) && isset($this->userInfo['data']['id'])) {

            $userModel = config('laravel-sso.usersModel');
            $username = config('laravel-sso.username');
            $remoteUserName = config('laravel-sso.remoteUserName');

            $user = config('laravel-sso.usersModel')::where($username, $this->userInfo['data'][$remoteUserName])->first();

            if (!$user) {
                $user = new $userModel;
                $user->$username = $this->userInfo['data'][$remoteUserName];
                $user->save();
            }

            if (auth()->guest()) {
                auth()->loginUsingId($user->id, true);
            }

            return true;
        }

        return false;
    }
}

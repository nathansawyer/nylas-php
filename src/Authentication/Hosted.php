<?php

declare(strict_types = 1);

namespace Nylas\Authentication;

use function trim;
use function http_build_query;

use Nylas\Utilities\API;
use Nylas\Utilities\Options;
use Nylas\Utilities\Validator as V;
use GuzzleHttp\Exception\GuzzleException;

/**
 * ----------------------------------------------------------------------------------
 * Nylas Hosted Authentication
 * ----------------------------------------------------------------------------------
 *
 * @author lanlin
 * @change 2023/07/21
 */
class Hosted
{
    // ------------------------------------------------------------------------------

    /**
     * @var Options
     */
    private Options $options;

    // ------------------------------------------------------------------------------

    /**
     * Hosted constructor.
     *
     * @param Options $options
     */
    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    // ------------------------------------------------------------------------------

    /**
     * Authenticate your user using Hosted Authentication
     *
     * @see https://developer.nylas.com/docs/api/v2/#get-/oauth/authorize
     * @see https://developer.nylas.com/docs/developer-guide/authentication/authentication-scopes/#nylas-scopes
     *
     * @param array $params
     *
     * @return string
     */
    public function authenticateUser(array $params): string
    {
        $params['api_token'] = $this->options->getApiToken();

        V::doValidate(V::keySet(
            V::key('scopes', V::stringType()::notEmpty()),
            V::key('api_token', V::stringType()::notEmpty()),
            V::key('redirect_uri', V::url()),
            V::key('response_type', V::in(['code', 'token'])),
            V::keyOptional('state', V::stringType()::length(1, 255)),
            V::keyOptional('provicer', V::in(['iCloud', 'gmail', 'office365', 'exchange', 'IMAP'])),
            V::keyOptional('login_hint', V::email()),
            V::keyOptional('redirect_on_error', V::boolType()),         // default false
            V::keyOptional('disable_provider_selection', V::boolType()) // default false
        ), $params);

        $query  = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $apiUrl = trim($this->options->getServer(), '/').API::LIST['oAuthAuthorize'];

        return trim($apiUrl, '/').'?'.$query;
    }

    // ------------------------------------------------------------------------------

    /**
     * Send authorization code. An access token will return as part of the response.
     *
     * @see https://developer.nylas.com/docs/api/v2/#post-/oauth/token
     *
     * @param string $code
     *
     * @return array
     * @throws GuzzleException
     */
    public function sendAuthorizationCode(string $code): array
    {
        V::doValidate(V::stringType()::notEmpty(), $code);

        $params = [
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'api_token'     => $this->options->getApiToken(),
        ];

        return $this->options
            ->getSync()
            ->setFormParams($params)
            ->post(API::LIST['oAuthToken']);
    }

    // ------------------------------------------------------------------------------

    /**
     * Revoke access tokens.
     *
     * @see https://developer.nylas.com/docs/api/v2/#post-/oauth/revoke
     *
     * @return array
     * @throws GuzzleException
     */
    public function revokeAccessTokens(): array
    {
        return $this->options
            ->getSync()
            ->setHeaderParams($this->options->getAuthorizationHeader())
            ->post(API::LIST['oAuthRevoke']);
    }

    // ------------------------------------------------------------------------------
}

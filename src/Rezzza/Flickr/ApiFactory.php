<?php

namespace Rezzza\Flickr;

use Rezzza\Flickr\Http\AdapterInterface;

/**
 * ApiFactory
 *
 * @author Stephane PY <py.stephane1@gmail.com>
 */
class ApiFactory
{
    /**
     * @var Metadata
     */
    protected $metadata;

    /**
     * @var AdapterInterface
     */
    protected $http;

    /**
     * @var string
     *
     * List of keys which must not be part of signatre.
     */
    protected static $unsignedKeys = array('photo');

    /**
     * @param Metadata         $metadata metadata
     * @param AdapterInterface $http     http
     */
    public function __construct(Metadata $metadata, AdapterInterface $http)
    {
        $this->metadata = $metadata;
        $this->http     = $http;
    }

    /**
     * @return Metadata
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @param string $service    service
     * @param array  $parameters parameters
     * @param string $endpoint   endpoint
     *
     * @return \SimpleXMLElement
     */
    public function call($service = null, array $parameters = array(), $endpoint = null)
    {
        $parameters = $this->buildParams($service, $parameters, $endpoint);

        return $this->http->post($endpoint, $parameters);
    }

    /**
     * @param array $calls
     * An array of Calls
     * Each call is an array with keys: service, parameters and endpoint
     *
     * @return \SimpleXMLElement[]
     */
    public function multiCall(array $calls)
    {
        $requests = array();
        foreach ($calls as $call) {
            $parameters = $this->buildParams($call['service'], $call['parameters'], $call['endpoint']);
            $requests[] = array('url' => $call['endpoint'], 'data' => $parameters, 'headers' => array());
        }

        return $this->http->multiPost($requests);
    }

    /**
     * @param string       $file        file
     * @param string       $title       title
     * @param string       $description description
     * @param string|array $tags        tags
     * @param boolean      $isPublic    isPublic
     * @param boolean      $isFriend    isFriend
     * @param boolean      $isFamily    isFamily
     *
     * @return \SimpleXMLElement
     */
    public function upload($file, $title = null, $description = null, $tags = null, $isPublic = null, $isFriend = null, $isFamily = null)
    {
        $path = realpath($file);

        if (false === $path) {
            throw new \LogicException(sprintf('File "%s" does not exists.', $file));
        }

        if (is_array($tags)) {
            foreach ($tags as $k => $v) {
                $tags[$k] = sprintf('"%s"', $v);
            }

            $tags = implode(' ', $tags);
        }

        $parameters = array(
            'photo'       => $path,
            'title'       => $title,
            'description' => $description,
            'tags'        => $tags,
            'is_public'   => (int) $isPublic,
            'is_friend'   => (int) $isFriend,
            'is_family'   => (int) $isFamily,
        );

        return $this->call(null, $parameters, $this->metadata->getUploadEndpoint());
    }

    /**
     * Replace an exitant photo by a new one
     *
     * http://www.flickr.com/services/api/replace.api.html
     *
     * @param string  $photoId photoId
     * @param string  $file    file
     * @param boolean $async   async
     *
     * @return \SimpleXMLElement
     */
    public function replace($photoId, $file, $async = false)
    {
        $path = realpath($file);

        if (false === $path) {
            throw new \LogicException(sprintf('File "%s" does not exists.', $file));
        }

        $parameters = array(
            'photo'    => '@'.$path,
            'photo_id' => $photoId,
            'async'    => $async,
        );

        return $this->call(null, $parameters, $this->metadata->getReplaceEndpoint());
    }

    /**
     * @param string $endpoint   endpoint
     * @param array  $parameters parameters
     */
    protected function addOAuthParameters($endpoint, array &$parameters)
    {
        $accessToken = $this->metadata->getAccessToken();
        $accessTokenSecret = $this->metadata->getAccessTokenSecret();

        if (empty($accessToken) || empty($accessTokenSecret)) {
            throw new \LogicException('Cannot access this resource without oauth authentication.');
        }

        $parameters['oauth_consumer_key']     = $this->metadata->getApiKey();
        $parameters['oauth_timestamp']        = time();
        $parameters['oauth_nonce']            = md5(uniqid(rand(), true));
        $parameters['oauth_signature_method'] = "HMAC-SHA1";
        $parameters['oauth_version']          = "1.0";
        $parameters['oauth_token']            = $accessToken;
        $parameters['oauth_signature']        = $this->buildOAuthSignature($endpoint, $parameters);
    }

    /**
     * @param string $endpoint   endpoint
     * @param array  $parameters parameters
     *
     * @return string
     */
    protected function buildOAuthSignature($endpoint, array $parameters)
    {
        ksort($parameters);

        $uri = 'POST&'.rawurlencode($endpoint).'&';
        $params = '';
        foreach ($parameters as $key => $value) {
            if (!in_array($key, self::$unsignedKeys)) {
                $params .= $key.'='.rawurlencode($value).'&';
            }
        }

        $uri .= rawurlencode(substr($params, 0, -1));

        return base64_encode(hash_hmac('sha1', $uri, $this->metadata->getSecret().'&'.$this->metadata->getAccessTokenSecret(), true));
    }

    /**
     * @param array $parameters parameters
     *
     * @return string
     */
    protected function buildSignature(array $parameters)
    {
        ksort($parameters);

        $sigUnhashed = $this->metadata->getSecret();
        foreach ($parameters as $key => $value) {
            if (!in_array($key, self::$unsignedKeys)) {
                $sigUnhashed .= $key.$value;
            }
        }

        return md5($sigUnhashed);
    }

    /**
     * @param string $service
     * @param array  $parameters
     * @param string $endpoint
     *
     * @return array
     */
    private function buildParams($service, array $parameters, &$endpoint)
    {
        if (null === $endpoint) {
            $endpoint = $this->metadata->getEndpoint();
        }

        $default = array(
            'api_key' => $this->metadata->getApiKey(),
            'format'  => 'rest',
        );

        if ($service) {
            $default['method'] = $service;
        }

        $parameters = array_merge($default, $parameters);
        $parameters = array_filter($parameters, function ($value) {
            return null !== $value;
        });

        $parameters['api_sig'] = $this->buildSignature($parameters);

        $this->addOAuthParameters($endpoint, $parameters);

        return $parameters;
    }
}

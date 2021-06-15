<?php

namespace Rezzza\Flickr\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Event\CompleteEvent;

/**
 * GuzzleAdapter
 *
 * @uses AdapterInterface
 * @author Stephane PY <py.stephane1@gmail.com>
 */
class GuzzleAdapter implements AdapterInterface
{

    private $client;

    public function __construct()
    {
        if (!class_exists('\GuzzleHttp\Client')) {
            throw new \LogicException('Please, install guzzle/http before using this adapter.');
        }
        $this->client = new Client();
    }

    /**
     * {@inheritdoc}
     */
    public function post($url, array $parameters = array(), array $headers = array())
    {

        $multipart = [];
        foreach($parameters as $key => $parameter) {
            $data = [];

            $data['name'] = $key;
            $data['contents'] = $parameter;

            if ($key == 'photo') {
                $data['contents'] = fopen($parameter, 'r');
                $data['filename'] = basename($parameter);
            }

            $multipart[] = $data;
        }

        // guzzle6 need multipart
        $response = $this->client->post( $url, [
            'headers' => $headers,
            'allow_redirects' => true,
            'multipart' => $multipart,
        ]);


        return new \SimpleXMLElement($response->getBody()->getContents());
    }

    /**
     * todo 動かない
     */
    public function multiPost(array $posts)
    {
        $requests = [];
        foreach ($posts as $post) {
            $options = ['allow_redirects' => true];
            array_push($options, $post);
            $requests[] = $this->client->createRequest('POST', $post['url'], $options);
        }

        $responses = [];
        // Add a single event listener using a callable.
        Pool::send($this->client, $requests, [
            'complete' => function (CompleteEvent $event) {
                $responses[] = $event->getResponse()->xml();
            }
        ]);

        return $responses;
    }

    /**
     * @return $client
     */
    public function getClient()
    {
        return $this->client;
    }

}

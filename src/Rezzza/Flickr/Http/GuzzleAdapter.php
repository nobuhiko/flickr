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
    public function post($url, array $data = array(), array $headers = array())
    {
        // flickr does not supports this header and return a 417 http code during upload
        //$request->removeHeader('Expect');

        try {
            $response = $this->client->post($url, [
                'headers'         => $headers,
                'form_params'     => $data,
                'allow_redirects' => true,
            ]);

        } catch (RequestException $e) {

            /*echo $e->getRequest() . "\n";
            if ($e->hasResponse()) {
                echo $e->getResponse() . "\n";
            }*/
        }

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

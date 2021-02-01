<?php

declare(strict_types=1);

namespace ClientEventBundle\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use http\Exception\RuntimeException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class ElasticsearchService
 *
 * @package ClientEventBundle\Services
 */
class ElasticsearchService
{
    /** @var string $host */
    private $host;
    
    /** @var Client $client */
    private $client;

    /**
     * ElasticsearchService constructor.
     *
     * @param string | null $host
     */
    public function __construct(?string $host)
    {
        $this->host = $host;
        $this->client = new Client();
    }

    /**
     * @return string | null
     */
    public function getHost(): ?string
    {
        return $this->host;
    }

    /**
     * @param string $host
     *
     * @return \App\Service\ElasticsearchService
     */
    public function setHost(string $host): ElasticsearchService
    {
        $this->host = $host;

        return $this;
    }

    /**
     * @param string $id
     *
     * @return array | null
     *
     * @throws GuzzleException
     */
    public function getDocument(string $id)
    {
        if ('%' !== substr($id, -1)) {
            $id = "$id%";
        }

        $options['body'] = json_encode(['query' => ['match' => ['_id' => $id]]]);

        $document = $this->sendRequest("_all/_search", $options);

        if (!isset($document['hits']['hits']) || count($document['hits']['hits']) === 0) {
            return null;
        }

        return $document['hits']['hits'];
    }

    /**
     * @param array $condition
     *
     * @return array
     *
     * @throws GuzzleException
     */
    public function findBy(array $condition)
    {
        $query = null;

        if (count($condition) > 1) {
            $query = ['bool' => ['must' => []]];

            foreach ($condition as $key => $value) {
                $query['bool']['must'][] = ['match' => [$key => $value]];
            }
        } else {
            $query = ['match' => $condition];
        }

        $options['body'] = json_encode(['query' => $query]);

        $document = $this->sendRequest("_all/_search", $options);

        if (!isset($document['hits']['hits']) || count($document['hits']['hits']) === 0) {
            return null;
        }

        return $document['hits']['hits'];
    }

    /**
     * @param string $index
     * @param string $id
     * @param array $data
     *
     * @return array
     *
     * @throws GuzzleException
     */
    public function addDocument(string $index, string $id, array $data)
    {
        $options['body'] = json_encode($data);

        return $this->sendRequest("$index/_doc/$id", $options, 'PUT');
    }

    /**
     * @param string $index
     * @param string $id
     * @param array $data
     *
     * @return array
     *
     * @throws GuzzleException
     */
    public function updateDocument(string $index, string $id, array $data)
    {
        $options['body'] = json_encode([
            'doc' => $data,
        ]);

        return $this->sendRequest("$index/_update/$id", $options);
    }

    /**
     * @param string $index
     * @param string $id
     *
     * @return array
     *
     * @throws GuzzleException
     */
    public function deleteDocument(string $index, string $id)
    {
        return $this->sendRequest("$index/_doc/$id", [], 'DELETE');
    }

    /**
     * @return array
     *
     * @throws GuzzleException
     */
    public function getIndexes()
    {
        return $this->sendRequest('_aliases', [], 'GET');
    }

    /**
     * @param string $name
     * @param string $indexMapping
     *
     * @return array
     *
     * @throws GuzzleException
     */
    public function createIndex(string $name, string $indexMapping)
    {
        $mappings = $this->getMappingByDocument($indexMapping);

        $options['body'] = json_encode([
            'mappings' => $mappings[$indexMapping]['mappings']
        ]);

        return $this->sendRequest($name, $options, 'PUT');
    }

    /**
     * @param array $from
     * @param string $to
     *
     * @return array
     *
     * @throws GuzzleException
     */
    public function mergeIndexes(array $from, string $to)
    {
        $options['body'] = json_encode([
            'source' => [
                'index' => array_values($from)
            ],
            'dest' => [
                'index' => $to,
            ]
        ]);

        return $this->sendRequest('_reindex', $options);
    }

    /**
     * @param array $indexes
     *
     * @throws GuzzleException
     */
    public function removeIndexes(array $indexes)
    {
        foreach ($indexes as $index) {
            $this->removeIndex($index);
        }
    }

    /**
     * @param string $name
     *
     * @return mixed
     *
     * @throws GuzzleException
     */
    public function removeIndex(string $name)
    {
        return $this->sendRequest($name, [], 'DELETE');
    }

    /**
     * @param string | null $index
     *
     * @return array
     *
     * @throws GuzzleException
     */
    public function getMappingByDocument(string $index = null)
    {
        $action = '_mapping';

        if (!is_null($index)) {
            $action = "$index/$action";
        }

        return $this->sendRequest($action, [], 'GET');
    }

    /**
     * @param string $uri
     * @param array $options
     * @param string $method
     *
     * @return array
     *
     * @throws GuzzleException
     */
    protected function sendRequest(string $uri, array $options, string $method = 'POST')
    {
        if (empty($this->getHost())) {
            throw new RuntimeException('The "elastic_host" parameter in the "client_event bundle" is not filled');
        }

        $options['headers'] = ['Content-Type' => 'application/json'];

        try {
            $data = $this->client->request($method, "{$this->getHost()}/$uri", $options);
        } catch (ClientException $exception) {
            $content = $exception->getResponse()->getBody()->getContents();
            throw new BadRequestHttpException($content);
        } catch (ServerException $exception) {
            $content = $exception->getResponse()->getBody()->getContents();
            throw $exception;
        }

        $data = $data->getBody()->getContents();

        return json_decode($data, true);
    }
}

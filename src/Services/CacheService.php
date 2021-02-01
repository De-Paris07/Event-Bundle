<?php

namespace ClientEventBundle\Services;

use Predis\Client;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CacheService
 *
 * @package ClientEventBundle\Services
 */
class CacheService
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /** @var RedisAdapter $cache */
    private $cache;

    /**
     * CacheService constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->cache = new RedisAdapter(
            new Client(['scheme' => 'tcp',
                'host' => $this->container->getParameter('client_event.event_server_address'),
                'port' => 6379,
                'read_write_timeout' => 0
            ]),
            'eventServer',
            $defaultLifetime = 604800
        );
    }

    /**
     * @param string $requestId
     *
     * @return bool | mixed
     */
    public function getQueryResponse(string $requestId)
    {
        if (!$this->keyExist("query.$requestId")) {
            return null;
        }
        
        $data = $this->getKey("query.$requestId");

        return empty($data) ? [] : $data;
    }

    /**
     * @param string $requestId
     * @param array $data
     * @param null $expiresAfter
     *
     * @return bool
     */
    public function setQueryResponse(string $requestId, array $data, $expiresAfter = null): bool
    {
        return $this->setKey("query.$requestId", $data, $expiresAfter);
    }

    /**
     * @param string $route
     * 
     * @return bool
     */
    public function checkRoute(string $route): bool
    {
        return $this->keyExist("query.$route");
    }

    /**
     * @param string $route
     *
     * @return mixed
     */
    public function getRouteValidateSchema(string $route)
    {
        $data = $this->getKey("query.$route");
        
        return $data['validateSchema'];
    }

    /**
     * @param $key
     *
     * @return mixed|null
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getKey($key)
    {
        $cached = $this->cache->getItem($key);

        return $cached->get();
    }

    /**
     * @param $key
     * @param $value
     * @param null $expiresAfter
     *
     * @return bool
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function setKey($key, $value, $expiresAfter = null): bool
    {
        $cached = $this->cache->getItem($key);

        if (!is_null($expiresAfter)) {
            $cached->expiresAfter($expiresAfter);
        }

        $cached->set($value);

        return $this->cache->save($cached);
    }

    /**
     * @param $key
     *
     * @return bool
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function keyExist($key): bool
    {
        $cached = $this->cache->getItem($key);

        if (!$cached->isHit()) {
            return false;
        }

        return true;
    }

    /**
     * @param $key
     *
     * @return mixed
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function deleteKey($key)
    {
        return $this->cache->deleteItem($key);
    }
}

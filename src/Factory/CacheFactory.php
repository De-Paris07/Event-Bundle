<?php

namespace ClientEventBundle\Factory;

use ClientEventBundle\Cache\CacheInterface;
use ClientEventBundle\Cache\CacheServiceInterface;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * Class CacheFactory
 * 
 * @package ClientEventBundle\Factory
 */
class CacheFactory
{
    /** @var CacheInterface $cache */
    static private $cache;

    /** @var CacheServiceInterface $cacheService */
    static private $cacheService;

    /** @var string $type */
    static private $type;

    /**
     * CacheFactory constructor.
     *
     * @param CacheServiceInterface $cacheService
     */
    public function __construct(CacheServiceInterface $cacheService)
    {
        self::$cacheService = $cacheService;
    }

    /**
     * @param CacheServiceInterface $cacheService
     */
    public function init(CacheServiceInterface $cacheService)
    {
        self::$cacheService = $cacheService;
    }

    /**
     * @param string $className
     */
    public static function setTypeCache(string $className)
    {
        self::$type = $className;
    }

    /**
     * @param string | null $key
     * @param string | null $type
     *
     * @return CacheInterface
     * 
     * @throws InvalidArgumentException
     */
    public static function create(string $key, string $type): CacheInterface
    {
        if (self::$cacheService->keyExist($key)) {
            return new $type(self::$cacheService->getKey($key));
        }

        if (!self::$cacheService->keyExist($key)) {
            /** @var CacheInterface $cache */
            $cache = new $type();
            $cache->setKey($key);

            return $cache;
        }
    }

    /**
     * @param string | null $key
     *
     * @return CacheInterface
     *
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public static function get(string $key = null): CacheInterface
    {
        if (!is_null($key) && self::$cacheService->keyExist($key)) {
            return self::loadRaw(self::$cacheService->getKey($key))->setKey($key);
        }

        if (!is_null($key) && !self::$cacheService->keyExist($key)) {
            return self::get()->setKey($key);
        }

        if (is_null(self::$cache) || get_class(self::$cache) !== self::$type) {
            self::$cache = new self::$type();
        }

        return self::$cache;
    }

    /**
     * @param $data
     *
     * @return CacheInterface
     *
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public static function load($data): CacheInterface
    {
        if (!is_array($data) && !($data instanceof CacheInterface)) {
            throw new \RuntimeException('The cache value can be an array or an instance of the ' . CacheInterface::class . ' class');
        }

        self::get()->load(is_array($data) ? $data : $data->toArray());

        return self::get();
    }

    /**
     * @param $data
     *
     * @return CacheInterface
     */
    public static function loadRaw($data): CacheInterface
    {
        if (!is_array($data) && !($data instanceof CacheInterface)) {
            throw new \RuntimeException('The cache value can be an array or an instance of the ' . CacheInterface::class . ' class');
        }

        self::clear();
        self::$cache = new self::$type(is_array($data) ? $data : $data->toArray());

        return self::$cache;
    }

    /**
     * @param CacheInterface | null $cache
     */
    public static function save(CacheInterface $cache = null)
    {
        if (is_null($cache)) {
            $cache = &self::$cache;
        }

        if (!is_null($cache) && $cache->isDirty() && !is_null($cache->getKey())) {
            self::$cacheService->setKey($cache->getKey(), $cache->toArray());
            $cache->load(['rawData' => $cache->toArray()]);
        }
    }

    public static function clear()
    {
        if (!is_null(self::$cache)) {
            self::$cache = null;
        }
    }

    /**
     * @param CacheInterface | null $cache
     *
     * @return bool
     */
    public static function remove(CacheInterface $cache = null): bool
    {
        if (is_null($cache)) {
            $cache = &self::$cache;
        }

        if (is_null($cache->getKey())) {
            return false;
        }

        $result = self::$cacheService->deleteKey($cache->getKey());
        self::clear();

        return $result;
    }
}

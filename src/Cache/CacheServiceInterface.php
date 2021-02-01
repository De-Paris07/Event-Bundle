<?php

namespace ClientEventBundle\Cache;

/**
 * Interface CacheServiceInterface
 * 
 * @package ClientEventBundle\Cache
 */
interface CacheServiceInterface
{
    /**
     * @param $key
     *
     * @return mixed | null
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getKey($key);

    /**
     * @param $key
     * @param $value
     *
     * @return bool
     */
    public function setKey($key, $value): bool;

    /**
     * @param $key
     *
     * @return bool
     */
    public function keyExist($key): bool;

    /**
     * @param $key
     *
     * @return bool
     */
    public function deleteKey($key): bool;
}

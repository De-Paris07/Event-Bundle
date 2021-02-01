<?php

namespace ClientEventBundle\Cache;

/**
 * Interface CacheInterface
 * 
 * @package ClientEventBundle\Cache
 */
interface CacheInterface
{
    /**
     * @return bool
     */
    public function isDirty(): bool;

    /**
     * @param null $object
     *
     * @return array
     */
    public function toArray($object = null);

    /**
     * @param array | null $data
     */
    public function load(array $data = null);

    /**
     * @return string | null
     */
    public function getKey(): ?string;

    /**
     * @param string $key
     *
     * @return $this
     */
    public function setKey($key = null): CacheInterface;
}

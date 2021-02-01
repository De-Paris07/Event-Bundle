<?php

namespace ClientEventBundle\Cache;

use ReflectionClass;
use ReflectionException;

/**
 * Class AbstractCache
 * 
 * @package ClientEventBundle\Cache
 */
abstract class AbstractCache implements CacheInterface
{
    /** @var array | null $rawData */
    protected $rawData = [];

    /** @var string $key */
    protected $key;

    /**
     * CallCache constructor.
     *
     * @param array|null $rawData
     *
     * @throws ReflectionException
     */
    public function __construct(array $rawData = null)
    {
        $this->rawData = $rawData ?? $this->toArray();
        $this->load();
    }

    /**
     * @return bool
     *
     * @throws ReflectionException
     */
    public function isDirty(): bool
    {
        return json_encode($this->rawData) !== json_encode($this->toArray());
    }

    /**
     * @param null $object
     *
     * @return array
     *
     * @throws ReflectionException
     */
    public function toArray($object = null)
    {
        if (is_null($object)) {
            $object = $this;
        }

        $reflectionClass = new ReflectionClass(get_class($object));
        $array = array();

        foreach ($reflectionClass->getProperties() as $property) {
            if ('rawData' === $property->getName()) {
                continue;
            }

            $property->setAccessible(true);
            $array[$property->getName()] = $property->getValue($object);
            $property->setAccessible(false);
        }

        return $array;
    }

    /**
     * @param array | null $data
     */
    public function load(array $data = null)
    {
        $data = $data ?? $this->rawData;

        foreach ($data as $key => $property) {
            if (!method_exists($this, $method = 'set' . ucfirst($key))) {
                continue;
            }

            $this->$method(is_object($property) ? clone $property : $property);
        }
    }

    /**
     * @return string | null
     */
    public function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * @param string $key
     *
     * @return $this
     */
    public function setKey($key = null): CacheInterface
    {
        $this->key = $key;

        return $this;
    }
}

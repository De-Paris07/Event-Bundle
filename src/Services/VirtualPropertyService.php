<?php

namespace ClientEventBundle\Services;

/**
 * Class VirtualPropertyService
 *
 * @package ClientEventBundle\Services
 */
class VirtualPropertyService
{
    /** @var array $properties */
    private $properties = [];

    /**
     * @param string $name
     * @param $value
     */
    public function addProperty(string $name, $value)
    {
        $this->properties[$name] = $value;
    }

    /**
     * @return array
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function clearProperties()
    {
        $this->properties = [];
    }
}

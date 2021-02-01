<?php

namespace ClientEventBundle;

/**
 * Class IncompleteClassAccessor
 *
 * @package ClientEventBundle
 */
class IncompleteClassAccessor
{
    /** @var \stdClass $object */
    private $object;

    /** @var string $originalClassName */
    private $originalClassName;

    /** @var array $properties */
    private $properties;

    /**
     * @param \__PHP_Incomplete_Class $object
     */
    public function __construct(\__PHP_Incomplete_Class $object)
    {
        $this->originalClassName = $this->loadOriginalClassName($object);
        $this->object = $this->convertToStdClass($object);
        $this->properties = [];
        $this->loadProperties();
    }

    /**
     * @param \__PHP_Incomplete_Class $object
     *
     * @return string
     */
    private function loadOriginalClassName(\__PHP_Incomplete_Class $object)
    {
        $originalClassName = '';

        foreach ($object as $property => $value) {
            if ($property == '__PHP_Incomplete_Class_Name') {
                $originalClassName = $value;
                break;
            }
        }
        
        return $originalClassName;
    }

    /**
     * @param \__PHP_Incomplete_Class $object
     *
     * @return \stdClass
     */
    private function convertToStdClass(\__PHP_Incomplete_Class $object)
    {
        $fixKey = function ($matches) {
            return ":" . strlen($matches[1]) . ":\"" . $matches[1] . "\"";
        };

        $dump = serialize($object);
        $dump = preg_replace('/^O:\d+:"[^"]++"/', 'O:8:"stdClass"', $dump);
        $dump = preg_replace_callback('/:\d+:"\0.*?\0([^"]+)"/', $fixKey, $dump);
        
        return unserialize($dump);
    }
    
    private function loadProperties()
    {
        foreach ($this->object as $property => $value) {
            if (($value instanceof \__PHP_Incomplete_Class)) {
                $this->properties[$property] = new self($value);
            } else {
                $this->properties[$property] = $value;
            }
        }
    }

    /**
     * @return \stdClass
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @return string
     */
    public function getOriginalClassName()
    {
        return $this->originalClassName;
    }

    /**
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @param string $propertyName
     *
     * @return IncompleteClassAccessor|mixed|null
     */
    public function getProperty($propertyName)
    {
        return isset($this->properties[$propertyName]) ? $this->properties[$propertyName] : null;
    }
}

<?php

namespace ClientEventBundle\Annotation;

/**
 * Class ExtractField
 *
 * @Annotation
 * @Target({"PROPERTY"})
 */
class ExtractField
{
    /** @var string $name */
    public $name;

    /** @var boolean $extractIsNotEmpty */
    public $extractIsNotEmpty = false;
    
    /** @var array $mapping */
    public $mapping = [];

    /**
     * ExtractField constructor.
     *
     * @param array $options
     */
    public function __construct($options = [])
    {
        foreach ($options as $key => $value) {
            if (!property_exists($this, $key)) {
                throw new \InvalidArgumentException(sprintf('Property "%s" does not exist on the "ExtractField" annotation.', $key));
            }

            $this->$key = $value;
        }
    }
}

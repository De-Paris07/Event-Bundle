<?php

namespace ClientEventBundle\Annotation;

/**
 * Class PropertyPathValue
 *
 * @Annotation
 * @Target({"PROPERTY"})
 */
class PropertyPathValue
{
    /**
     * @var string | array
     * @Required
     */
    public $path;

    /**
     * ExtractField constructor.
     *
     * @param array $options
     */
    public function __construct($options = [])
    {
        foreach ($options as $key => $value) {
            if (!property_exists($this, $key)) {
                throw new \InvalidArgumentException(sprintf('Property "%s" does not exist on the "PropertyPathValue" annotation.', $key));
            }

            $this->$key = $value;
        }
    }
}

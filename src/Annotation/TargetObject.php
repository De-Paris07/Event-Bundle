<?php

namespace ClientEventBundle\Annotation;

/**
 * Class TargetObject
 *
 * @Annotation
 * @Target({"PROPERTY"})
 */
class TargetObject
{
    /** @var string $class */
    public $class;
    
    /**
     * TargetObject constructor.
     *
     * @param array $options
     */
    public function __construct($options = [])
    {
        foreach ($options as $key => $value) {
            if (!property_exists($this, $key)) {
                throw new \InvalidArgumentException(sprintf('Property "%s" does not exist on the "TargetObject" annotation.', $key));
            }

            $this->$key = $value;
        }
    }
}

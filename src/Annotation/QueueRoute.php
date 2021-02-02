<?php

namespace ClientEventBundle\Annotation;

use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Doctrine\Common\Annotations\Annotation\Enum;

/**
 * Class QueueRoute
 *
 * @Annotation
 * @Target({"METHOD"})
 * @Attributes({
 *   @Attribute("name", type = "string", required=true),
 *   @Attribute("description",  type = "string", required=true),
 * })
 */
class QueueRoute
{
    /** @var string $name */
    public $name;

    /** @var string $description */
    public $description;

//    /**
//     * @var string
//     *
//     * @Enum({"all", "less_loaded"})
//     */
//    public $strategy;

    /**
     * QueueRoute constructor.
     */
    public function __construct($options = [])
    {
        foreach ($options as $key => $value) {
            if (!property_exists($this, $key)) {
                throw new \InvalidArgumentException(sprintf('Property "%s" does not exist on the "QueueRoute" annotation.', $key));
            }

            $this->$key = $value;
        }
    }
}

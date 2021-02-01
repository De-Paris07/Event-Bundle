<?php

namespace ClientEventBundle\Services;

use ClientEventBundle\Event;
use ClientEventBundle\Exception\ExtractFieldException;

/**
 * Class FieldsService
 *
 * @package ClientEventBundle\Services
 */
class FieldsService
{
    /** @var array | null $extractFields */
    private $extractFields;

    /** @var array | null $virtualProperties */
    private $virtualProperties;
    
    /** @var array | null $propertyPathFields */
    private $propertyPathFields;
    
    /** @var array | null $targetObjectFields */
    private $targetObjectFields;

    /**
     * ExtractFieldsService constructor.
     *
     * @param array | null $extractFields
     * @param array $propertyPathFields
     * @param array $targetObjectFields
     * @param array | null $virtualProperties
     */
    public function __construct(
        array $extractFields = [],
        array $propertyPathFields = [],
        array $targetObjectFields = [],
        array $virtualProperties = null
    ) {
        $this->extractFields = $extractFields;
        $this->virtualProperties = $virtualProperties;
        $this->propertyPathFields = $propertyPathFields;
        $this->targetObjectFields = $targetObjectFields;
    }

    /**
     * @param Event $event
     *
     * @return array
     */
    public function getFields(Event $event): array
    {
        $fields = [];
        $key = get_class($event);

        if (!array_key_exists($key, $this->extractFields)) {
            return $fields;
        }

        foreach ($this->extractFields[$key] as $field) {
            $value = $this->getValue($field, $event);
            $name = $field['alias'] ? $field['alias'] : $field['name'];

            if (!$field['extractIsNotEmpty'] ||
                ($field['extractIsNotEmpty'] &&
                    !is_null($value) &&
                    ( (is_string($value) && '' !== $value) || (is_array($value) && count($value)) || is_float($value) || is_int($value) ))
            ) {
                $fields[$name] = $value;
            }

            $mappings = $field['mapping'];

            if (count($mappings) && isset($mappings[$value])) {
                $fields[$name] = $mappings[$value];
            }
        }

        return $fields;
    }

    /**
     * @param Event $event
     *
     * @return array
     */
    public function getPropertyPathFields(Event $event): array
    {
        $fields = [];
        $key = get_class($event);

        if (!array_key_exists($key, $this->propertyPathFields)) {
            return $fields;
        }
        
        return  $this->propertyPathFields[$key];
    }

    /**
     * @param Event $event
     *
     * @return array
     */
    public function getTargetObjectFields(Event $event): array
    {
        $fields = [];
        $key = get_class($event);

        if (!array_key_exists($key, $this->targetObjectFields)) {
            return $fields;
        }

        return  $this->targetObjectFields[$key];
    }

    /**
     * @param array $field
     * @param Event $class
     *
     * @return mixed
     */
    private function getValue(array $field, Event $class)
    {
        if (!is_null($field['parent'])) {
            $parents = explode('.', $field['parent']);

            foreach ($parents as $paren) {
                $parentMethod = $this->getMethod($paren, $class);
                $class = $class->$parentMethod();
            }
        }

        $method = $this->getMethod($field['name'], $class);
        return $class->$method();
    }

    /**
     * @param string $fieldName
     * @param $class
     *
     * @return string
     */
    private function getMethod(string $fieldName, $class): string
    {
        $method = null;
        $className = get_class($class);
        $getMethod = 'get'.ucfirst($fieldName);
        $isMethod = 'is'.ucfirst($fieldName);
        $hasMethod = 'has'.ucfirst($fieldName);

        if (method_exists($class, $getMethod)) {
            $method = $getMethod;
        } elseif (method_exists($class, $isMethod)) {
            $method = $isMethod;
        } elseif (method_exists($class, $hasMethod)) {
            $method = $hasMethod;
        } else {
            throw new ExtractFieldException(sprintf('Neither of these methods exist in class %s: %s, %s, %s', $className, $getMethod, $isMethod, $hasMethod));
        }

        return $method;
    }
}

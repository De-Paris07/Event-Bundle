<?php

namespace ClientEventBundle\DependencyInjection\Compiler;

use Doctrine\Common\Annotations\Reader;
use ClientEventBundle\Annotation\ExtractField;
use ClientEventBundle\Annotation\PropertyPathValue;
use ClientEventBundle\Annotation\TargetObject;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use function Symfony\Component\Debug\Tests\testHeader;

/**
 * Class FieldsPass
 *
 * @package ClientEventBundle\DependencyInjection\Compiler
 */
class FieldsPass implements CompilerPassInterface
{
    /** @var array $targetObjectFields */
    private $targetObjectFields = [];
    
    /**
     * @param ContainerBuilder $container
     *
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function process(ContainerBuilder $container)
    {
        $extractFields = [];
        $propertyPathFields = [];
        /** @var Reader $reader */
        $reader = $container->get('annotation_reader');
        $fieldsDefinition = $container->getDefinition('client_event.fields.service');

        foreach ($this->getReflectionClassesEvents($container) as $className => $reflectionClass) {
            list($extract, $propertyPath) = $this->readFieldsAnnotations($reflectionClass, $reader);
            
            $extractFields = $extractFields + $extract;
            $propertyPathFields = $propertyPathFields + $propertyPath;
        }

        $fieldsDefinition->setArgument('$extractFields', $extractFields);
        $fieldsDefinition->setArgument('$propertyPathFields', $propertyPathFields);
        $fieldsDefinition->setArgument('$targetObjectFields', $this->targetObjectFieldsFromArray($this->targetObjectFields));
    }

    /**
     * @param array $properties
     *
     * @return array
     */
    private function targetObjectFieldsFromArray(array $properties)
    {
        $fields = [];
        
        foreach ($properties as $key => $object) {
            if (is_array($object)) {
                $data = $this->targetObjectFieldsFromArray($object);
                $fields[$key] = $data;
                continue;
            }

            $fields[$key] = ['name' => $object->getName(), 'class' => $object->class];
        }
        
        return $fields;
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return array
     *
     * @throws \ReflectionException
     */
    private function getReflectionClassesEvents(ContainerBuilder $container)
    {
        $classes = [];
        $eventSent = $container->getParameter('client_event.events_sent');
        $eventSubscribe = $container->getParameter('client_event.events_subscribe');

        foreach ($eventSent as $event) {
            if (key_exists($object = $event['target_object'], $classes)) {
                continue;
            }

            $classes[$object] = new \ReflectionClass($object);
        }

        foreach ($eventSubscribe as $subscribe) {
            if (!isset($subscribe['target_object']) || key_exists($subscribe['target_object'], $classes)) {
                continue;
            }

            $classes[$subscribe['target_object']] = new \ReflectionClass($subscribe['target_object']);
        }

        return $classes;
    }

    /**
     * @param array $miscAnnotations
     *
     * @return \Iterator
     */
    private function getExtractFieldsAnnotations(array $miscAnnotations): \Iterator
    {
        foreach ($miscAnnotations as $miscAnnotation) {
            if (ExtractField::class === \get_class($miscAnnotation)) {
                yield $miscAnnotation;
            }
        }
    }

    /**
     * @param array $miscAnnotations
     *
     * @return \Iterator
     */
    private function getTargetObjectAnnotations(array $miscAnnotations): \Iterator
    {
        foreach ($miscAnnotations as $miscAnnotation) {
            if (TargetObject::class === \get_class($miscAnnotation)) {
                yield $miscAnnotation;
            }
        }
    }

    /**
     * @param array $miscAnnotations
     *
     * @return \Iterator
     */
    private function getPropertyPathValueAnnotations(array $miscAnnotations): \Iterator
    {
        foreach ($miscAnnotations as $miscAnnotation) {
            if (PropertyPathValue::class === \get_class($miscAnnotation)) {
                yield $miscAnnotation;
            }
        }
    }

    /**
     * @param \ReflectionClass $reflectionClass
     * @param Reader $reader
     *
     * @return array
     *
     * @throws \ReflectionException
     */
    private function readFieldsAnnotations(\ReflectionClass $reflectionClass, Reader $reader)
    {
        $compositeProperties = $this->getCompositeProperties($reflectionClass, $reader);
        $properties = array_merge($reflectionClass->getProperties(), $compositeProperties);
        
        if (count($compositeProperties)) {
            $this->targetObjectFields[$reflectionClass->getName()] = $compositeProperties;
        }

        $extractFields = $this->getExtractFields($properties, $compositeProperties, $reflectionClass, $reader);
        $propertyPathFields = $this->getPropertyPathFields($properties, $compositeProperties, $reflectionClass, $reader);

        return [$extractFields, $propertyPathFields];
    }

    /**
     * @param \ReflectionClass $reflectionClass
     * @param Reader $reader
     *
     * @return array | \ReflectionProperty[]
     *
     * @throws \ReflectionException
     */
    private function getCompositeProperties(\ReflectionClass $reflectionClass, Reader $reader)
    {
        $properties = [];

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            foreach ($this->getTargetObjectAnnotations($reader->getPropertyAnnotations($reflectionProperty)) as $annotation) {
                $childProperties = $this->getCompositeProperties(new \ReflectionClass($annotation->class), $reader);
                $targetClass = new \ReflectionClass($annotation->class);
                $parentProperty = $reflectionProperty->getName();
                $properties[$parentProperty] = $targetClass->getProperties() + $childProperties;
            }
        }

        return $properties;
    }

    /**
     * @param array $properties
     * @param array $compositeProperties
     * @param \ReflectionClass $reflectionClass
     * @param Reader $reader
     *
     * @return array
     */
    private function getExtractFields(array $properties, array $compositeProperties, \ReflectionClass $reflectionClass, Reader $reader)
    {
        $fields = [];

        foreach ($properties as $parentKey => $reflectionProperty) {
            if (is_array($reflectionProperty)) {
                foreach ($reflectionProperty as $childKey => $childProperty) {
                    if (is_array($childProperty)) {
                        $childFields = $this->getExtractFields([$childKey =>$childProperty], $compositeProperties, $reflectionClass, $reader);

                        if (!count($childFields)) {
                            continue;
                        }

                        if (is_string($parentKey) && '' !== $parentKey) {
                            foreach ($childFields[$reflectionClass->getName()] as $key => $item) {
                                $childFields[$reflectionClass->getName()][$key]['parent'] = $parentKey . '.' . $item['parent'];
                            }
                        }

                        $fields[$reflectionClass->getName()] = array_merge($fields[$reflectionClass->getName()], $childFields[$reflectionClass->getName()]);
                        continue;
                    }

                    foreach ($this->getExtractFieldsAnnotations($reader->getPropertyAnnotations($childProperty)) as $annotation) {
                        $alias = $annotation->name;
                        $propertyName = $childProperty->getName();

                        if (isset($compositeProperties[$parentKey]) ? key_exists($propertyName, $compositeProperties[$parentKey]) : key_exists($propertyName, $properties[$parentKey])) {
                            throw new \RuntimeException(sprintf('The "%s::$%s" field refers to the "%s" object. Use "%s" to retrieve this field.', $childProperty->class, $propertyName, current($reflectionProperty[$propertyName])->class, 'ClientEventBundle\Services\VirtualPropertyService'));
                        }

                        $fields[$reflectionClass->getName()][] = [
                            'name' => $propertyName,
                            'alias' => $alias,
                            'extractIsNotEmpty' => $annotation->extractIsNotEmpty,
                            'mapping' => $annotation->mapping,
                            'parent' => $parentKey,
                        ];
                    }
                }
                continue;
            }

            foreach ($this->getExtractFieldsAnnotations($reader->getPropertyAnnotations($reflectionProperty)) as $annotation) {
                $alias = $annotation->name;
                $propertyName = $reflectionProperty->getName();

                if (key_exists($propertyName, $compositeProperties)) {
                    throw new \RuntimeException(sprintf('The "%s::$%s" field refers to the "%s" object. Use "%s" to retrieve this field.', $reflectionClass->getName(), $propertyName, current($compositeProperties[$propertyName])->class, 'ClientEventBundle\Services\VirtualPropertyService'));
                }

                $fields[$reflectionClass->getName()][] = [
                    'name' => $propertyName,
                    'alias' => $alias,
                    'extractIsNotEmpty' => $annotation->extractIsNotEmpty,
                    'mapping' => $annotation->mapping,
                    'parent' => null,
                ];
            }
        }

        return $fields;
    }

    /**
     * @param array $properties
     * @param array $compositeProperties
     * @param \ReflectionClass $reflectionClass
     * @param Reader $reader
     *
     * @return array
     */
    private function getPropertyPathFields(array $properties, array $compositeProperties, \ReflectionClass $reflectionClass, Reader $reader)
    {
        $fields = [];

        foreach ($properties as $parentKey => $reflectionProperty) {
            if (is_array($reflectionProperty)) {
                foreach ($reflectionProperty as $childKey => $childProperty) {
                    if (is_array($childProperty)) {
                        $childFields = $this->getPropertyPathFields([$childKey =>$childProperty], $compositeProperties, $reflectionClass, $reader);

                        if (!count($childFields)) {
                            continue;
                        }

                        if (is_string($parentKey) && '' !== $parentKey) {
                            foreach ($childFields[$reflectionClass->getName()] as $key => $item) {
                                $childFields[$reflectionClass->getName()][$key]['parent'] = $parentKey . '.' . $item['parent'];
                            }
                        }

                        $fields[$reflectionClass->getName()] = array_merge($fields[$reflectionClass->getName()], $childFields[$reflectionClass->getName()]);
                        continue;
                    }

                    foreach ($this->getPropertyPathValueAnnotations($reader->getPropertyAnnotations($childProperty)) as $annotation) {
                        $path = $annotation->path;
                        $propertyName = $childProperty->getName();
                        $isTargetProperty = count(iterator_to_array($this->getTargetObjectAnnotations($reader->getPropertyAnnotations($childProperty)), false));

                        $fields[$reflectionClass->getName()][] = [
                            'name' => $propertyName, 
                            'path' => $this->getPathProperty($path, $propertyName),
                            'parent' => $parentKey,
                            'class' => $isTargetProperty ? $childProperty->class : null
                        ];
                    }
                }
                
                continue;
            }

            foreach ($this->getPropertyPathValueAnnotations($reader->getPropertyAnnotations($reflectionProperty)) as $annotation) {
                $path = $annotation->path;
                $propertyName = $reflectionProperty->getName();

                $fields[$reflectionClass->getName()][] = [
                    'name' => $propertyName,
                    'path' => $this->getPathProperty($path, $propertyName),
                    'parent' => null
                ];
            }
        }
        
        return $fields;
    }

    /**
     * @param $path
     * @param $propertyName
     *
     * @return array
     */
    private function getPathProperty($path, $propertyName)
    {
        $fields = [];
        
        if (is_array($path)) {
            foreach ($path as $key => $property) {
                $child = [];
                
                if (is_array($property)) {
                    $child = $this->getPathProperty($property, $key);
                    $fields[] = ['name' =>$key, 'path' => $child];
                    continue;
                }
                
                $fields[] = ['name' => $key, 'path' => $property, 'parent' => null];
            }
            
            return $fields;
        }
        
        return $path;
    }
}

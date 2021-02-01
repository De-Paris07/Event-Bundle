<?php

namespace ClientEventBundle\DependencyInjection\Compiler;

use Doctrine\Common\Annotations\Reader;
use ClientEventBundle\Annotation\QueueRoute;
use ClientEventBundle\Util\ReflectionClassRecursiveIterator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class RoutePass
 *
 * @package ClientEventBundle\DependencyInjection\Compiler
 */
class RoutePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        /** @var Reader $reader */
        $reader = $container->get('annotation_reader');
        $resourceClassDirectories = [$container->getParameter('kernel.project_dir').'/src'];
        $routes = [];

        
        foreach (ReflectionClassRecursiveIterator::getReflectionClassesFromDirectories($resourceClassDirectories) as $className => $reflectionClass) {
            /** @var \ReflectionClass $reflectionClass */
            foreach ($reflectionClass->getMethods() as $reflectionMethod) {
                foreach ($this->getRouteAnnotations($reader->getMethodAnnotations($reflectionMethod)) as $annotation) {
                    $routes[$annotation->name] = [
                        'name' => $annotation->name,
                        'description' => $annotation->description,
                        'class' => $reflectionClass->getName(),
                        'method' => $reflectionMethod->getName(),
                    ];

                    if (!$container->hasDefinition($reflectionClass->getName())) {
                        continue;
                    }

                    $container->findDefinition($reflectionClass->getName())
                        ->setPublic(true);
                }
            }
        }
        
       if (!empty($routes)) {
           $container->setParameter('client_event.routes', $routes);
       }
    }

    /**
     * @param array $miscAnnotations
     *
     * @return \Iterator
     */
    private function getRouteAnnotations(array $miscAnnotations): \Iterator
    {
        foreach ($miscAnnotations as $miscAnnotation) {
            if (QueueRoute::class === \get_class($miscAnnotation)) {
                yield $miscAnnotation;
            }
        }
    }
}

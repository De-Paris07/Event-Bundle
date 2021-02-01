<?php

namespace ClientEventBundle;

use ClientEventBundle\DependencyInjection\Compiler\FieldsPass;
use ClientEventBundle\DependencyInjection\Compiler\RoutePass;
use ClientEventBundle\DependencyInjection\QueueEventsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ClientEventBundle extends Bundle
{
    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new FieldsPass());
        $container->addCompilerPass(new RoutePass());
    }
}

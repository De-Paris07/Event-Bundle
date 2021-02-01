<?php

namespace ClientEventBundle\VirtualProperty;

abstract class AbstractVirtualProperty implements VirtualProperty
{
    abstract public function getName(): string;

    abstract public function getValue();
}

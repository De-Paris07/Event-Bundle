<?php

namespace ClientEventBundle\VirtualProperty;

interface VirtualProperty
{
    public function getName(): string;

    public function getValue();
}

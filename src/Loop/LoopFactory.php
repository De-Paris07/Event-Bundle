<?php

declare(strict_types=1);

namespace ClientEventBundle\Loop;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;

/**
 * Class LoopFactory
 *
 * @package ClientEventBundle\Loop
 */
final class LoopFactory
{
    /** @var LoopInterface $lopp */
    private static $loop;

    /**
     * @return LoopInterface
     */
    public static function getLoop(): LoopInterface
    {
        if (!is_null(self::$loop)) {
            return self::$loop;
        }
        
        self::$loop = Factory::create();

        return self::$loop;
    }
}

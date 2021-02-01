<?php

namespace ClientEventBundle\Util;

/**
 * Class ReflectionClassRecursiveIterator
 *
 * @package ClientEventBundle\Util
 */
class ReflectionClassRecursiveIterator
{
    /**
     * @param array $directories
     * @return \Iterator
     * @throws \ReflectionException
     */
    public static function getReflectionClassesFromDirectories(array $directories): \Iterator
    {
        foreach ($directories as $path) {
            $iterator = new \RegexIterator(
                new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                ),
                '/^.+\.php$/i',
                \RecursiveRegexIterator::GET_MATCH
            );

            foreach ($iterator as $file) {
                $sourceFile = $file[0];

                if (!preg_match('(^phar:)i', $sourceFile)) {
                    $sourceFile = realpath($sourceFile);
                }

                require_once $sourceFile;

                $includedFiles[$sourceFile] = true;
            }
        }

        $declared = get_declared_classes();

        foreach ($declared as $className) {
            $reflectionClass = new \ReflectionClass($className);
            $sourceFile = $reflectionClass->getFileName();
            if (isset($includedFiles[$sourceFile])) {
                yield $className => $reflectionClass;
            }
        }
    }
}

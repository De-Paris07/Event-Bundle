<?php

namespace ClientEventBundle\Query;

/**
 * Interface QueryClientInterface
 *
 * @package ClientEventBundle\Query
 */
interface QueryClientInterface
{
    /**
     * @param string $route
     * @param $data
     *
     * @return QueryResponse
     */
    public function query(string $route, $data);

    /**
     * @return int
     */
    public function getTimeout(): int;

    /**
     * @param int $timeout
     *
     * @return $this
     */
    public function setTimeout(int $timeout): QueryClientInterface;

    /**
     * @return bool
     */
    public function isBlokking(): bool;

    /**
     * @param bool $blokking
     *
     * @return QueryClient
     */
    public function setBlokking(bool $blokking): QueryClientInterface;
}

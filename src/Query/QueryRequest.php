<?php

namespace ClientEventBundle\Query;

/**
 * Class QueryRequest
 *
 * @package ClientEventBundle\Query
 */
class QueryRequest
{
    /** @var array | null $data */
    private $data;

    /**
     * QueryRequest constructor.
     *
     * @param array | null $data
     */
    public function __construct(array $data = null)
    {
        $this->data = $data;
    }

    /**
     * @return false|string
     */
    public function __toString()
    {
        if (is_null($this->data)) {
            return '';
        }
        
        return json_encode($this->data);
    }

    /**
     * @param $key
     * @param null $default
     *
     * @return mixed | null
     */
    public function get($key, $default = null)
    {
        if (!isset($this->data[$key])) {
            return $default;
        }
        
        return $this->data[$key];
    }

    /**
     * @return array | null
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * @param array | null $data
     */
    public function setData(?array $data): void
    {
        $this->data = $data;
    }
}

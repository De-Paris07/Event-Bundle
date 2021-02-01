<?php

namespace ClientEventBundle\Query;

/**
 * Class QueryResponse
 *
 * @package ClientEventBundle\Query
 */
class QueryResponse
{
    public const STATUS_OK = 'success';
    public const STATUS_ERROR = 'error';

    /** @var any $rawData */
    private $data;

    /** @var string $status */
    private $status;
    
    /** @var integer $code */
    private $code;

    /** @var string | null $error */
    private $error;
    
    /** @var array | null $errors */
    private $errors;

    /**
     * QueryResponse constructor.
     *
     * @param $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        if (empty($this->data)) {
            return '';
        }
        
        return @json_encode($this->data, 128 | 256) ?? '';
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
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @param string $status
     *
     * @return QueryResponse
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @param int $code
     * 
     * @return QueryResponse
     */
    public function setCode(int $code): self
    {
        $this->code = $code;
        
        return $this;
    }

    /**
     * @return bool
     */
    public function isError(): bool
    {
        return self::STATUS_ERROR === $this->getStatus();
    }

    /**
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @param string|null $error
     *
     * @return QueryResponse
     */
    public function setError(?string $error): QueryResponse
    {
        $this->error = $error;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getErrors(): ?array
    {
        return $this->errors;
    }

    /**
     * @param array|null $errors
     * 
     * @return QueryResponse
     */
    public function setErrors(?array $errors): QueryResponse
    {
        $this->errors = $errors;

        return $this;
    }
}

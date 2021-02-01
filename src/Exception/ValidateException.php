<?php

namespace ClientEventBundle\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class ValidateException
 *
 * @package ClientEventBundle\Exception
 */
class ValidateException extends HttpException
{
    /** @var array $errors */
    public $errors;

    /**
     * ValidateException constructor.
     * @param array $errors
     * @param string|null $message
     */
    public function __construct(array $errors, string $message = null)
    {
        $this->errors = $errors;
        parent::__construct(Response::HTTP_BAD_REQUEST, $message);
    }
}

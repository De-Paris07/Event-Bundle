<?php

namespace ClientEventBundle\Services;

use ClientEventBundle\Event;
use ClientEventBundle\Exception\ValidateException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class ValidateService
 *
 * @package ClientEventBundle\Services
 */
class ValidateService
{
    /** @var ValidatorInterface $validator */
    private $validator;

    /**
     * ValidateService constructor.
     * 
     * @param ValidatorInterface $validator
     */
    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @param Event $event
     */
    public function validate(Event $event)
    {
        $errors = [];

        foreach ($this->validator->validate($event) as $violation) {
            $field = preg_replace(['/\]\[/', '/\[|\]/'], ['.', ''], $violation->getPropertyPath());
            $errors[$field] = $violation->getMessage();
        }

        if (!empty($errors)) {
            throw new ValidateException($errors, 'The structure of the event does not match the stated.');
        }
    }

    /**
     * @param Event $event
     *
     * @return bool
     */
    public function canDispatchByFilter(Event $event): bool
    {
        $errors = [];

        foreach ($this->validator->validate($event) as $violation) {
            $field = preg_replace(['/\]\[/', '/\[|\]/'], ['.', ''], $violation->getPropertyPath());
            $errors[$field] = $violation->getMessage();
        }

        return empty($errors);
    }
}

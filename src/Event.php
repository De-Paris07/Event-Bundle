<?php

namespace ClientEventBundle;

use ClientEventBundle\Annotation\ExtractField;
use Pheanstalk\PheanstalkInterface;
use Symfony\Component\Validator\Constraints as Assert;

if (class_exists(\Symfony\Component\EventDispatcher\Event::class)) {
    class Event extends \Symfony\Component\EventDispatcher\Event
    {
        /**
         * @var string
         *
         * @Assert\NotBlank()
         * @Assert\Length(min=3)
         */
        protected $senderServiceName;

        /**
         * @var string
         *
         * @Assert\NotBlank()
         * @Assert\Length(min=3)
         */
        protected $eventId;

        /** @var array $dataArray */
        protected $dataArray = [];

        /** @var string $error */
        protected $error;

        /**
         * @var string
         *
         * @Assert\NotBlank()
         * @Assert\Length(min=3)
         */
        protected $eventName;

        /**
         * @var boolean $history
         *
         * @ExtractField()
         */
        protected $history = false;

        /**
         * @var int
         */
        protected $delay = PheanstalkInterface::DEFAULT_DELAY;

        /**
         * @var int
         */
        protected $priority = PheanstalkInterface::DEFAULT_PRIORITY;


        /**
         * @var string
         *
         * @Assert\NotBlank()
         */
        protected $created;

        /**
         * @return string
         */
        public function getSenderServiceName(): ?string
        {
            return $this->senderServiceName;
        }

        /**
         * @param string $senderServiceName
         */
        public function setSenderServiceName(?string $senderServiceName): void
        {
            $this->senderServiceName = $senderServiceName;
        }

        /**
         * @return string
         */
        public function getEventId(): ?string
        {
            return $this->eventId;
        }

        /**
         * @param string $eventId
         */
        public function setEventId(?string $eventId): void
        {
            $this->eventId = $eventId;
        }

        /**
         * @return array | null
         */
        public function getDataArray(): ?array
        {
            return $this->dataArray;
        }

        /**
         * @param array $dataArray
         */
        public function setDataArray(?array $dataArray): void
        {
            $this->dataArray = $dataArray;
        }

        /**
         * @return string
         */
        public function getEventName(): string
        {
            return $this->eventName;
        }

        /**
         * @param string $eventName
         */
        public function setEventName(string $eventName): void
        {
            $this->eventName = $eventName;
        }

        /**
         * @return string
         */
        public function getError(): ?string
        {
            return $this->error;
        }

        /**
         * @param string $error
         */
        public function setError(?string $error): void
        {
            $this->error = $error;
        }

        /**
         * @return bool
         */
        public function isHistory(): bool
        {
            return $this->history;
        }

        /**
         * @param bool $history
         */
        public function setHistory(bool $history): void
        {
            $this->history = $history;
        }

        /**
         * @return int
         */
        public function getDelay(): int
        {
            return $this->delay;
        }

        /**
         * @param int $delay
         */
        public function setDelay(int $delay): void
        {
            $this->delay = $delay;
        }

        /**
         * @return int
         */
        public function getPriority(): int
        {
            return $this->priority;
        }

        /**
         * @param int $priority
         */
        public function setPriority(int $priority): void
        {
            $this->priority = $priority;
        }


        /**
         * @return int | null
         */
        public function getCreated()
        {
            return $this->created;
        }

        /**
         * @param mixed $created
         */
        public function setCreated($created): void
        {
            $this->created = $created;
        }
    }
} else {
    class Event extends \Symfony\Contracts\EventDispatcher\Event
    {
        /**
         * @var string
         *
         * @Assert\NotBlank()
         * @Assert\Length(min=3)
         */
        protected $senderServiceName;

        /**
         * @var string
         *
         * @Assert\NotBlank()
         * @Assert\Length(min=3)
         */
        protected $eventId;

        /** @var array $dataArray */
        protected $dataArray = [];

        /** @var string $error */
        protected $error;

        /**
         * @var string
         *
         * @Assert\NotBlank()
         * @Assert\Length(min=3)
         */
        protected $eventName;

        /**
         * @var boolean $history
         *
         * @ExtractField()
         */
        protected $history = false;

        /**
         * @var int
         */
        protected $delay = PheanstalkInterface::DEFAULT_DELAY;

        /**
         * @var int
         */
        protected $priority = PheanstalkInterface::DEFAULT_PRIORITY;


        /**
         * @var string
         *
         * @Assert\NotBlank()
         */
        protected $created;

        /**
         * @return string
         */
        public function getSenderServiceName(): ?string
        {
            return $this->senderServiceName;
        }

        /**
         * @param string $senderServiceName
         */
        public function setSenderServiceName(?string $senderServiceName): void
        {
            $this->senderServiceName = $senderServiceName;
        }

        /**
         * @return string
         */
        public function getEventId(): ?string
        {
            return $this->eventId;
        }

        /**
         * @param string $eventId
         */
        public function setEventId(?string $eventId): void
        {
            $this->eventId = $eventId;
        }

        /**
         * @return array | null
         */
        public function getDataArray(): ?array
        {
            return $this->dataArray;
        }

        /**
         * @param array $dataArray
         */
        public function setDataArray(?array $dataArray): void
        {
            $this->dataArray = $dataArray;
        }

        /**
         * @return string
         */
        public function getEventName(): string
        {
            return $this->eventName;
        }

        /**
         * @param string $eventName
         */
        public function setEventName(string $eventName): void
        {
            $this->eventName = $eventName;
        }

        /**
         * @return string
         */
        public function getError(): ?string
        {
            return $this->error;
        }

        /**
         * @param string $error
         */
        public function setError(?string $error): void
        {
            $this->error = $error;
        }

        /**
         * @return bool
         */
        public function isHistory(): bool
        {
            return $this->history;
        }

        /**
         * @param bool $history
         */
        public function setHistory(bool $history): void
        {
            $this->history = $history;
        }

        /**
         * @return int
         */
        public function getDelay(): int
        {
            return $this->delay;
        }

        /**
         * @param int $delay
         */
        public function setDelay(int $delay): void
        {
            $this->delay = $delay;
        }

        /**
         * @return int
         */
        public function getPriority(): int
        {
            return $this->priority;
        }

        /**
         * @param int $priority
         */
        public function setPriority(int $priority): void
        {
            $this->priority = $priority;
        }


        /**
         * @return int | null
         */
        public function getCreated()
        {
            return $this->created;
        }

        /**
         * @param mixed $created
         */
        public function setCreated($created): void
        {
            $this->created = $created;
        }
    }
}

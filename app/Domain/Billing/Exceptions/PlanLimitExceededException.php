<?php

namespace App\Domain\Billing\Exceptions;

use Exception;
use Illuminate\Support\Str;

class PlanLimitExceededException extends Exception
{
    public function __construct(
        public string $key,
        public int $limit,
        string $message = '',
    ) {
        parent::__construct(
            $message !== ''
                ? $message
                : sprintf('Your plan allows %d %s. Upgrade to add more.', $limit, Str::plural($this->key, $limit))
        );
    }
}

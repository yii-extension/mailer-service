<?php

declare(strict_types=1);

namespace Yii\Extension\Service\Event;

final class MessageNotSent
{
    private string $errorMessage;

    public function __construct(string $errorMessage)
    {
        $this->errorMessage = $errorMessage;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }
}

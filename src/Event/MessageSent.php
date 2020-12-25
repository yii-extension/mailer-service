<?php

declare(strict_types=1);

namespace Yii\Extension\Service\Event;

final class MessageSent
{
    private string $type;
    private string $header;
    private string $body;
    private bool $addFlash;

    public function __construct(string $type, string $header, string $body, bool $addFlash)
    {
        $this->type = $type;
        $this->header = $header;
        $this->body = $body;
        $this->addFlash = $addFlash;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getHeader(): string
    {
        return $this->header;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getAddFlash(): bool
    {
        return $this->addFlash;
    }
}

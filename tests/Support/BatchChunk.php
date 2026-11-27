<?php

declare(strict_types=1);

namespace EasyUmsPay\Tests\Support;

use Symfony\Contracts\HttpClient\ChunkInterface;

class BatchChunk implements ChunkInterface
{
    public function __construct(private readonly bool $isLast = true, private readonly ?string $error = null)
    {
    }

    public function isTimeout(): bool
    {
        return false;
    }

    public function isFirst(): bool
    {
        return false;
    }

    public function isLast(): bool
    {
        return $this->isLast;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getInformationalStatus(): ?array
    {
        return null;
    }

    public function getContent(): string
    {
        return '';
    }

    public function getOffset(): int
    {
        return 0;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}

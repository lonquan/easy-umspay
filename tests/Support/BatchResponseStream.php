<?php

declare(strict_types=1);

namespace EasyUmsPay\Tests\Support;

use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class BatchResponseStream implements ResponseStreamInterface
{
    /** @var list<array{0: ResponseInterface, 1: ChunkInterface}> */
    private array $pairs;

    private int $position = 0;

    /**
     * @param  list<array{0: ResponseInterface, 1: ChunkInterface}>  $pairs
     */
    public function __construct(array $pairs)
    {
        $this->pairs = $pairs;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function current(): ChunkInterface
    {
        return $this->pairs[$this->position][1];
    }

    public function key(): ResponseInterface
    {
        return $this->pairs[$this->position][0];
    }

    public function next(): void
    {
        $this->position++;
    }

    public function valid(): bool
    {
        return isset($this->pairs[$this->position]);
    }
}

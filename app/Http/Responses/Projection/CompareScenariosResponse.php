<?php

namespace App\Http\Responses\Projection;

class CompareScenariosResponse
{
    public function __construct(private readonly array $comparisons)
    {
    }

    public function toArray(): array
    {
        return ['comparisons' => $this->comparisons];
    }
}

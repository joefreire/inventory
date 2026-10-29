<?php

namespace App\Purchases;

/**
 * Outcome of one purchases import.
 */
final readonly class ImportResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $ordersCreated = 0,
        public int $ordersUpdated = 0,
        public int $itemsCreated = 0,
        public array $errors = [],
    ) {}
}

<?php

namespace App\Contracts;

interface EventImporter
{
    /**
     * Get the stable provider name.
     */
    public function name(): string;

    /**
     * Determine whether the importer has enough configuration to run.
     */
    public function isConfigured(): bool;

    /**
     * Fetch normalized events from the provider.
     *
     * May return an array or a Generator for memory-efficient streaming.
     *
     * @param  array{limit?: int|null}  $options
     * @return iterable<array<string, mixed>>
     */
    public function fetch(array $options = []): iterable;
}

<?php

namespace App\Services\Procurement;

use App\Models\DocumentSequence;

class DocumentNumberService
{
    /**
     * Must be called inside the transaction that creates the document.
     * The unique sequence row serialises concurrent requests per document/year.
     */
    public function next(string $documentType, string $prefix): string
    {
        $year = (int) now()->format('Y');

        // Create the row atomically before taking the explicit row lock.
        // A locking read of a missing row first can acquire competing gap
        // locks in MySQL. Never swallow deadlocks or other query failures:
        // the surrounding document transaction must be allowed to retry.
        $timestamp = now();
        DocumentSequence::query()->toBase()->upsert([
            [
                'document_type' => $documentType,
                'year' => $year,
                'last_number' => 0,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ], ['document_type', 'year'], ['document_type']);

        $sequence = DocumentSequence::query()
            ->where('document_type', $documentType)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();

        $sequence->increment('last_number');

        return sprintf('%s-%d-%05d', $prefix, $year, $sequence->last_number);
    }
}

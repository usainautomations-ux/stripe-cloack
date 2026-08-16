<?php

namespace App\Services\Crypto;

/**
 * Wraps and unwraps data keys with the master key.
 *
 * The interface exists so the local implementation and the KMS implementation
 * are interchangeable without any calling code knowing which is in use. Note
 * that neither method ever exposes the master key itself — with KMS the master
 * key is not merely hidden, it is unavailable to this process by design.
 */
interface KeyProvider
{
    /**
     * Encrypt a data key with the master key.
     */
    public function wrap(string $dataKey): string;

    /**
     * Recover a data key previously produced by wrap().
     */
    public function unwrap(string $wrappedKey): string;
}

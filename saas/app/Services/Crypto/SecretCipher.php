<?php

namespace App\Services\Crypto;

use RuntimeException;
use SensitiveParameter;

/**
 * Envelope encryption for Stripe secret keys and website HMAC secrets.
 *
 * Each secret is sealed under its own randomly generated data key; the data key
 * is then wrapped by the master key. The stored ciphertext contains the wrapped
 * data key alongside the sealed payload, so rotating the master key requires
 * rewrapping only the small data keys, not re-encrypting every secret.
 *
 * The master key comes from a KeyProvider. In production that is KMS, which
 * never discloses the master key to the application at all. Locally it is a
 * key from the environment. Both satisfy the property that matters: a stolen
 * database dump alone is not enough to recover a usable Stripe key.
 *
 * Format (base64 of): VERSION(1) || WRAPPED_KEY_LEN(2) || WRAPPED_KEY || NONCE(24) || SEALED
 */
class SecretCipher
{
    private const VERSION = 1;

    public function __construct(private readonly KeyProvider $keys)
    {
    }

    public function encrypt(#[SensitiveParameter] string $plaintext): string
    {
        $dataKey = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $sealed = sodium_crypto_secretbox($plaintext, $nonce, $dataKey);
        $wrapped = $this->keys->wrap($dataKey);

        sodium_memzero($dataKey);

        $envelope = pack('C', self::VERSION)
            . pack('n', strlen($wrapped))
            . $wrapped
            . $nonce
            . $sealed;

        return base64_encode($envelope);
    }

    public function decrypt(string $ciphertext): string
    {
        $raw = base64_decode($ciphertext, true);

        if ($raw === false || strlen($raw) < 3) {
            throw new RuntimeException('Secret ciphertext is malformed.');
        }

        $version = unpack('C', substr($raw, 0, 1))[1];

        if ($version !== self::VERSION) {
            throw new RuntimeException("Unsupported secret envelope version: {$version}.");
        }

        $wrappedLength = unpack('n', substr($raw, 1, 2))[1];
        $wrapped = substr($raw, 3, $wrappedLength);

        $nonceOffset = 3 + $wrappedLength;
        $nonce = substr($raw, $nonceOffset, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $sealed = substr($raw, $nonceOffset + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        if ($nonce === '' || $sealed === '') {
            throw new RuntimeException('Secret ciphertext is truncated.');
        }

        $dataKey = $this->keys->unwrap($wrapped);
        $plaintext = sodium_crypto_secretbox_open($sealed, $nonce, $dataKey);

        sodium_memzero($dataKey);

        if ($plaintext === false) {
            // Wrong key or tampered payload — indistinguishable, and deliberately so.
            throw new RuntimeException('Secret could not be decrypted.');
        }

        return $plaintext;
    }

    /**
     * Last four characters of a secret, for operator recognition in the UI.
     * Never enough to reconstruct the key.
     */
    public function fingerprint(#[SensitiveParameter] string $plaintext): string
    {
        return substr($plaintext, -4);
    }
}

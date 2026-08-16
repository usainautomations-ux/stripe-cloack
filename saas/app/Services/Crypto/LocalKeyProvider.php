<?php

namespace App\Services\Crypto;

use RuntimeException;

/**
 * Development key provider: the master key lives in the environment.
 *
 * This is the correct choice locally and the wrong choice in production, where
 * KmsKeyProvider should be bound instead — the difference being that KMS never
 * hands the master key to this process, so a compromised app server cannot
 * exfiltrate it.
 */
class LocalKeyProvider implements KeyProvider
{
    private string $masterKey;

    public function __construct(?string $base64Key)
    {
        if (empty($base64Key)) {
            throw new RuntimeException(
                'SECRETS_MASTER_KEY is not set. Generate one with: php artisan secrets:generate-key'
            );
        }

        $key = base64_decode($base64Key, true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException(
                'SECRETS_MASTER_KEY must be a base64-encoded ' . SODIUM_CRYPTO_SECRETBOX_KEYBYTES . '-byte key.'
            );
        }

        $this->masterKey = $key;
    }

    public function wrap(string $dataKey): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return $nonce . sodium_crypto_secretbox($dataKey, $nonce, $this->masterKey);
    }

    public function unwrap(string $wrappedKey): string
    {
        $nonce = substr($wrappedKey, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $sealed = substr($wrappedKey, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $dataKey = sodium_crypto_secretbox_open($sealed, $nonce, $this->masterKey);

        if ($dataKey === false) {
            throw new RuntimeException('Data key could not be unwrapped with the configured master key.');
        }

        return $dataKey;
    }
}

<?php

namespace App\Services\Stripe;

use App\Models\StripeAccount;
use RuntimeException;
use Stripe\StripeClient;

/**
 * Builds a Stripe client for a specific account, decrypting its secret key at
 * the last possible moment.
 *
 * Clients are memoised per request only. They are deliberately NOT held in any
 * cross-request cache: a long-lived client keeps a decrypted secret key
 * resident in memory indefinitely, which turns any later memory-disclosure bug
 * into a live-key disclosure.
 *
 * @var array<string, StripeClient>
 */
class StripeClientFactory
{
    private array $clients = [];

    public function for(StripeAccount $account): StripeClient
    {
        if (! $account->isUsable()) {
            throw new RuntimeException(
                "Stripe account '{$account->label}' is suspended or has no credentials configured."
            );
        }

        return $this->clients[$account->id] ??= new StripeClient([
            'api_key' => $account->secretKey(),
            'stripe_version' => config('services.stripe.api_version'),
        ]);
    }

    /**
     * Verify a secret key actually works before it is saved, so a typo is caught
     * at configuration time rather than at the first customer's checkout.
     *
     * @return array{valid: bool, message: string, account_name: ?string}
     */
    public function validateKey(string $secretKey): array
    {
        try {
            $client = new StripeClient(['api_key' => $secretKey]);
            $account = $client->accounts->retrieve();

            return [
                'valid' => true,
                'message' => 'Key verified.',
                'account_name' => $account->business_profile->name ?? $account->id,
            ];
        } catch (\Stripe\Exception\AuthenticationException) {
            return ['valid' => false, 'message' => 'Stripe rejected this key.', 'account_name' => null];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return ['valid' => false, 'message' => 'Stripe returned an error: ' . $e->getMessage(), 'account_name' => null];
        } catch (\Throwable) {
            return ['valid' => false, 'message' => 'Could not reach Stripe to verify this key.', 'account_name' => null];
        }
    }
}

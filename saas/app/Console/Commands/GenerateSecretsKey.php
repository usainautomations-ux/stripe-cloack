<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateSecretsKey extends Command
{
    protected $signature = 'secrets:generate-key {--show : Print the key instead of writing it to .env}';

    protected $description = 'Generate the master key used to wrap Stripe and plugin secrets';

    public function handle(): int
    {
        $key = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));

        if ($this->option('show')) {
            $this->line($key);

            return self::SUCCESS;
        }

        $path = base_path('.env');

        if (! file_exists($path)) {
            $this->error('No .env file found. Copy .env.example first.');

            return self::FAILURE;
        }

        $contents = file_get_contents($path);

        if (preg_match('/^SECRETS_MASTER_KEY=(.*)$/m', $contents, $matches) && trim($matches[1]) !== '') {
            $this->error('SECRETS_MASTER_KEY is already set.');
            $this->line('Replacing it makes every stored Stripe key and plugin secret undecryptable.');
            $this->line('To rotate deliberately, use secrets:rotate-master-key instead.');

            return self::FAILURE;
        }

        $contents = preg_match('/^SECRETS_MASTER_KEY=/m', $contents)
            ? preg_replace('/^SECRETS_MASTER_KEY=.*$/m', "SECRETS_MASTER_KEY={$key}", $contents)
            : rtrim($contents) . "\nSECRETS_MASTER_KEY={$key}\n";

        file_put_contents($path, $contents);

        $this->info('Master key generated and written to .env');
        $this->line('Back this up now. Without it, stored secrets cannot be recovered.');

        return self::SUCCESS;
    }
}

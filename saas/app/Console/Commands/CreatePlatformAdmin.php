<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Creates the first user able to log in.
 *
 * There is deliberately no public sign-up route — every user on a payments
 * platform should be provisioned by someone accountable for having done it,
 * not by whoever finds the URL. This command exists only to bootstrap the
 * very first account; every user after this one is created by an existing
 * administrator through the dashboard.
 */
class CreatePlatformAdmin extends Command
{
    protected $signature = 'app:create-admin
        {--email= : Login email}
        {--name= : Display name}
        {--tenant= : Tenant slug to create and own. Omit for a platform administrator.}
        {--tenant-name= : Display name for a new tenant. Only asked interactively if omitted.}
        {--password= : Set the password non-interactively. Prefer the interactive prompt — this option leaves the password in shell history.}';

    protected $description = 'Create the first user able to log in — a platform admin, or a tenant owner';

    public function handle(TenantContext $context): int
    {
        // A CLI invocation has no HTTP request, so nothing has ever bound a
        // tenant to this connection. Without elevating first, every insert
        // below would be rejected by the RLS policies' WITH CHECK clause —
        // the isolation layer working correctly, just not what we want here.
        $context->elevate();

        $email = $this->option('email') ?: $this->ask('Email address');
        $name = $this->option('name') ?: $this->ask('Full name');
        $tenantSlug = $this->option('tenant');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name],
            ['email' => ['required', 'email'], 'name' => ['required', 'string', 'max:255']]
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        if (User::withoutGlobalScopes()->where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists.");

            return self::FAILURE;
        }

        if ($password = $this->option('password')) {
            $this->warn('Password supplied via --password. Clear this from your shell history.');
        } else {
            $password = $this->secret('Password (min 12 characters)');

            if ($password !== $this->secret('Confirm password')) {
                $this->error('Passwords did not match.');

                return self::FAILURE;
            }
        }

        if (strlen($password) < 12) {
            $this->error('Password must be at least 12 characters.');

            return self::FAILURE;
        }

        $tenantId = null;
        $roleKey = 'platform_admin';

        if ($tenantSlug) {
            // Only prompt when nothing was supplied on the command line and a
            // password wasn't either — --password is treated as the signal
            // that this run is scripted and must not block on stdin.
            $tenantName = $this->option('tenant-name')
                ?? ($this->option('password') ? ucfirst($tenantSlug) : $this->ask('Tenant display name', ucfirst($tenantSlug)));

            $tenant = \App\Models\Tenant::withoutGlobalScopes()->firstOrCreate(
                ['slug' => $tenantSlug],
                ['name' => $tenantName]
            );
            $tenantId = $tenant->id;
            $roleKey = 'tenant_owner';
        }

        if (! Role::find($roleKey)) {
            $this->error("Role '{$roleKey}' does not exist. Run `php artisan db:seed --class=RolePermissionSeeder` first.");

            return self::FAILURE;
        }

        $user = new User([
            'tenant_id' => $tenantId,
            'role_key' => $roleKey,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        // email_verified_at is not user-fillable — assigning it directly would
        // let an ordinary registration flow mark itself pre-verified. Setting
        // it here bypasses that guard deliberately, because this command IS
        // the trusted operator action that should be allowed to.
        $user->forceFill(['status' => 'active', 'email_verified_at' => now()])->save();

        $this->info("Created {$roleKey} '{$user->name}' <{$user->email}>.");

        if ($tenantId) {
            $this->line("Tenant: {$tenantSlug}");
        }

        return self::SUCCESS;
    }
}

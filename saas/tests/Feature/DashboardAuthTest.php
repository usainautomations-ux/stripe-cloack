<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Guards the auth guard's own session-based re-authentication.
 *
 * This exists because of a real bug: with RLS enabled on `users`, login
 * itself worked (the credential lookup runs elevated), but every request
 * AFTER that silently failed to re-authenticate. Illuminate\Auth\SessionGuard
 * calls the user provider's retrieveById() on every request to reload the
 * session's user, and RLS rejected that SELECT because no tenant was bound
 * yet — binding it is what the 'tenant' middleware does, and it can only run
 * once the user (and therefore their tenant) is known. The guard fell back to
 * treating the request as a guest, and every authenticated route bounced back
 * to /login, even with a perfectly valid session cookie. Fixed by
 * TenantAwareUserProvider, which elevates specifically for that lookup.
 *
 * Two things make this bug easy to write a test that doesn't actually catch
 * it:
 *
 *  - actingAs() only calls Guard::setUser() — it never writes anything to the
 *    session, so a naive test never exercises retrieveById() at all.
 *  - Laravel's HTTP test client reuses the same application container, and
 *    therefore the same TenantContext singleton, across sequential calls
 *    within one test method. A real subsequent request under php-fpm or
 *    artisan serve starts from a fresh, unbound TenantContext; a test that
 *    doesn't force that starting condition can pass while the real bug is
 *    still there.
 *
 * So this test does three things deliberately: performs an actual Auth::login
 * (which writes the session key a real login would), discards the guard's
 * in-memory user so the next resolution must go back through the provider,
 * and clears TenantContext to the unbound state a fresh process would have.
 */
class DashboardAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);

        $this->user = User::create([
            'tenant_id' => $tenant->id,
            'role_key' => 'tenant_owner',
            'name' => 'Owner',
            'email' => 'owner@acme.test',
            'password' => Hash::make('correct-horse-battery-staple'),
            'status' => 'active',
        ]);
    }

    public function test_a_logged_in_user_can_load_the_dashboard_on_a_subsequent_request(): void
    {
        Auth::login($this->user);

        // Force the next request to re-resolve the user from scratch, from a
        // tenant context as unbound as a genuinely fresh process would have —
        // the exact precondition that triggered the bug.
        app(TenantContext::class)->clear();
        $this->app['auth']->forgetGuards();

        $response = $this->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertSee('Overview');
    }

    public function test_the_login_form_itself_can_authenticate_a_tenant_user(): void
    {
        $response = $this->post(route('login.attempt'), [
            'email' => 'owner@acme.test',
            'password' => 'correct-horse-battery-staple',
        ]);

        $response->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_a_platform_admin_with_no_tenant_can_authenticate_and_reload(): void
    {
        $admin = User::create([
            'tenant_id' => null,
            'role_key' => 'platform_admin',
            'name' => 'Platform Admin',
            'email' => 'admin@usman.com',
            'password' => Hash::make('correct-horse-battery-staple'),
            'status' => 'active',
        ]);

        Auth::login($admin);
        app(TenantContext::class)->clear();
        $this->app['auth']->forgetGuards();

        $this->get(route('dashboard.index'))->assertOk();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $response = $this->post(route('login.attempt'), [
            'email' => 'owner@acme.test',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_suspended_user_cannot_log_in(): void
    {
        $this->user->forceFill(['status' => 'suspended'])->save();

        $response = $this->post(route('login.attempt'), [
            'email' => 'owner@acme.test',
            'password' => 'correct-horse-battery-staple',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_unauthenticated_requests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard.index'))->assertRedirect(route('login'));
    }
}

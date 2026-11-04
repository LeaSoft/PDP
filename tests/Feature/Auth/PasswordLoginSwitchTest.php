<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * AUTH_PASSWORD_LOGIN=false: the app is Google-only.
 */
class PasswordLoginSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.password_login' => false]);
    }

    public function test_password_login_is_closed(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertNotFound();

        $this->assertGuest();
    }

    public function test_login_page_offers_only_google(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Login')
                ->where('authFeatures.passwordLogin', false));
    }

    public function test_registration_sends_people_to_the_login_page(): void
    {
        $this->get(route('register'))->assertRedirect(route('login'));

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@leasoft.org',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_reset_is_closed(): void
    {
        $this->get(route('password.request'))->assertNotFound();
        $this->post(route('password.email'), ['email' => 'test@leasoft.org'])->assertNotFound();
        $this->post(route('password.store'), [])->assertNotFound();
    }

    public function test_password_settings_and_account_deletion_are_closed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('password.edit'))->assertNotFound();
        $this->actingAs($user)->put(route('password.update'), [])->assertNotFound();
        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertNotFound();

        $this->assertNotNull($user->fresh());
    }

    public function test_logout_everyone_clears_sessions_and_remember_me_tokens(): void
    {
        $users = User::factory()->count(2)->create();
        DB::table('sessions')->insert([
            'id' => 'some-session', 'user_id' => $users[0]->id, 'ip_address' => '10.0.0.1',
            'user_agent' => 'test', 'payload' => '', 'last_activity' => time(),
        ]);

        $this->artisan('pdp:logout-everyone', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('sessions', 0);
        $this->assertSame(0, User::whereNotNull('remember_token')->count());
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\Pdp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
            'auth.company_email_domains' => ['leasoft.org'],
        ]);
    }

    /**
     * Make the callback receive this Google account. A null value drops the claim.
     */
    private function fakeGoogleAccount(array $claims = []): void
    {
        $raw = array_filter(array_merge([
            'sub' => 'google-123',
            'name' => 'Olena Koval',
            'email' => 'olena@leasoft.org',
            'email_verified' => true,
            'hd' => 'leasoft.org',
        ], $claims), fn ($value) => $value !== null);

        Socialite::fake('google', (new SocialiteUser)->setRaw($raw)->map([
            'id' => $raw['sub'] ?? null,
            'name' => $raw['name'] ?? null,
            'email' => $raw['email'] ?? null,
        ]));
    }

    public function test_redirect_sends_the_person_to_google(): void
    {
        Socialite::fake('google');

        $this->get(route('google.redirect'))
            ->assertRedirect('https://socialite.fake/google/authorize');
    }

    public function test_google_sign_in_is_off_until_the_oauth_client_is_configured(): void
    {
        config(['services.google.client_id' => null]);
        $this->fakeGoogleAccount();

        $this->get(route('google.redirect'))->assertNotFound();
        $this->get(route('google.callback'))->assertNotFound();
        $this->assertGuest();
    }

    public function test_new_employee_gets_an_account_with_no_roles(): void
    {
        $this->fakeGoogleAccount();

        $this->get(route('google.callback'))
            ->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'olena@leasoft.org')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('Olena Koval', $user->name);
        $this->assertFalse($user->is_moderator);
        $this->assertFalse($user->isSuperAdmin());
        $this->assertNotNull($user->email_verified_at);
        // Google sign-in never sets remember-me
        $this->assertNull($user->remember_token);
    }

    public static function rejectedAccounts(): array
    {
        return [
            'personal gmail' => [['email' => 'olena@gmail.com', 'hd' => null]],
            'company address outside the Workspace (no hd)' => [['hd' => null]],
            'another company Workspace' => [['email' => 'olena@other.com', 'hd' => 'other.com']],
            'company hd with a foreign email' => [['email' => 'olena@other.com']],
            'foreign hd with a company email' => [['hd' => 'other.com']],
            'unverified email' => [['email_verified' => false]],
            'email_verified missing' => [['email_verified' => null]],
        ];
    }

    #[DataProvider('rejectedAccounts')]
    public function test_accounts_that_are_not_company_workspace_accounts_are_rejected(array $claims): void
    {
        $this->fakeGoogleAccount($claims);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_account_with_the_same_email_is_linked_and_keeps_its_data(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->unverified()->create([
            'email' => 'olena@leasoft.org',
            'is_moderator' => true,
        ]);
        $pdp = Pdp::create(['user_id' => $user->id, 'title' => 'My PDP', 'priority' => 'Medium', 'status' => 'Planned']);
        $oldRememberToken = $user->remember_token;
        // A session somebody else may have opened with the password
        DB::table('sessions')->insert([
            'id' => 'other-session', 'user_id' => $user->id, 'ip_address' => '10.0.0.1',
            'user_agent' => 'test', 'payload' => '', 'last_activity' => time(),
        ]);

        $this->fakeGoogleAccount();

        $this->get(route('google.callback'))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);

        $user->refresh();
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->is_moderator, 'Linking must not change roles');
        $this->assertSame($user->id, $pdp->fresh()->user_id);
        $this->assertNotSame($oldRememberToken, $user->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
    }

    public function test_linked_account_is_found_by_google_id_after_the_email_changes(): void
    {
        $user = User::factory()->create(['email' => 'olena.old@leasoft.org']);
        $user->forceFill(['google_id' => 'google-123'])->save();

        $this->fakeGoogleAccount(['email' => 'olena@leasoft.org']);

        $this->get(route('google.callback'))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_account_linked_to_another_google_account_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'olena@leasoft.org']);
        $user->forceFill(['google_id' => 'google-999'])->save();

        $this->fakeGoogleAccount();

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertSame('google-999', $user->fresh()->google_id);
    }

    public function test_cancelled_google_sign_in_returns_to_the_login_page(): void
    {
        $this->get(route('google.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_google_linked_account_cannot_change_its_email(): void
    {
        $user = User::factory()->create(['email' => 'olena@leasoft.org']);
        $user->forceFill(['google_id' => 'google-123'])->save();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), ['name' => 'Olena K', 'email' => 'someone.else@leasoft.org'])
            ->assertSessionHasErrors('email');

        $this->assertSame('olena@leasoft.org', $user->fresh()->email);
    }

    public function test_admin_users_list_shows_who_has_linked_google(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['super_admin' => true])->save();
        $linked = User::factory()->create();
        $linked->forceFill(['google_id' => 'google-123'])->save();

        $rows = collect($this->actingAs($admin)->getJson('/admin/users.json')->assertOk()->json('data'))
            ->keyBy('user_id');

        $this->assertTrue($rows[$linked->id]['google_linked']);
        $this->assertFalse($rows[$admin->id]['google_linked']);
    }
}

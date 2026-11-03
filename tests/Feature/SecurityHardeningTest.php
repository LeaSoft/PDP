<?php

namespace Tests\Feature;

use App\Models\Pdp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function pdpWithMentor(): array
    {
        $owner = User::factory()->create();
        $mentor = User::factory()->create();
        $pdp = Pdp::create([
            'user_id' => $owner->id,
            'title' => 'Owner PDP',
            'priority' => 'Medium',
            'status' => 'Planned',
        ]);
        $pdp->curators()->attach($mentor->id);

        return [$owner, $mentor, $pdp];
    }

    public function test_mentor_cannot_delete_share_or_add_mentors_to_owner_pdp(): void
    {
        [, $mentor, $pdp] = $this->pdpWithMentor();
        $outsider = User::factory()->create();

        $this->actingAs($mentor)->deleteJson("/pdps/{$pdp->id}.json")->assertForbidden();
        $this->actingAs($mentor)->postJson("/pdps/{$pdp->id}/transfer.json", ['user_id' => $outsider->id])->assertForbidden();
        $this->actingAs($mentor)->postJson("/pdps/{$pdp->id}/assign-curator.json", ['email' => $outsider->email])->assertForbidden();

        $this->assertDatabaseHas('pdps', ['id' => $pdp->id]);
        $this->assertDatabaseMissing('pdps', ['user_id' => $outsider->id]);
        $this->assertFalse($pdp->curators()->whereKey($outsider->id)->exists());
    }

    public function test_mentor_can_still_edit_pdp_details_and_owner_keeps_full_control(): void
    {
        [$owner, $mentor, $pdp] = $this->pdpWithMentor();
        $other = User::factory()->create();

        $this->actingAs($mentor)->putJson("/pdps/{$pdp->id}.json", ['title' => 'Edited by mentor'])->assertOk();

        $this->actingAs($owner)->postJson("/pdps/{$pdp->id}/assign-curator.json", ['email' => $other->email])->assertOk();
        $this->actingAs($owner)->postJson("/pdps/{$pdp->id}/transfer.json", ['user_id' => $other->id])->assertSuccessful();
        $this->actingAs($owner)->deleteJson("/pdps/{$pdp->id}.json")->assertNoContent();
    }

    public function test_two_factor_secrets_are_not_serialized(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['two_factor_secret' => 'secret', 'two_factor_recovery_codes' => 'codes'])->save();

        $data = $user->fresh()->toArray();

        $this->assertArrayNotHasKey('two_factor_secret', $data);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $data);
    }

    public function test_profile_email_can_only_change_to_company_domain(): void
    {
        $user = User::factory()->create(['email' => 'legacy@example.com']);

        // Keeping a legacy address while editing the name still works
        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'New Name', 'email' => 'legacy@example.com'])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'New Name', 'email' => 'someone@gmail.com'])
            ->assertSessionHasErrors('email');

        $this->assertSame('legacy@example.com', $user->fresh()->email);
    }

    public function test_registration_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('register.store'))->assertStatus(302);
        }

        $this->post(route('register.store'))->assertStatus(429);
    }

    public function test_import_rejects_oversized_payloads(): void
    {
        $user = User::factory()->create();
        $skill = ['skill' => 'S', 'priority' => 'Low', 'status' => 'Planned'];
        $pdp = ['title' => 'Imported', 'priority' => 'Low', 'status' => 'Planned'];

        $this->actingAs($user)->postJson('/pdps/import.json', ['pdp' => $pdp, 'skills' => array_fill(0, 101, $skill)])
            ->assertJsonValidationErrors('skills');

        $this->actingAs($user)->postJson('/pdps/import.json', ['pdp' => $pdp, 'skills' => [$skill + ['description' => str_repeat('a', 20001)]]])
            ->assertJsonValidationErrors('skills.0.description');

        $this->assertDatabaseCount('pdps', 0);
    }
}

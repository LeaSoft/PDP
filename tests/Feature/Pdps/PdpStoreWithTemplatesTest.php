<?php

namespace Tests\Feature\Pdps;

use App\Models\Pdp;
use App\Models\PdpSkill;
use App\Models\PdpTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdpStoreWithTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_pdp_with_selected_skill_templates(): void
    {
        $user = User::factory()->create();

        $templateOne = PdpTemplate::create([
            'user_id' => $user->id,
            'title' => 'Backend Foundation',
            'description' => 'Base backend skills',
            'published' => true,
            'data' => [
                'version' => 1,
                'pdp' => [
                    'title' => 'Backend Foundation',
                    'description' => 'Base backend skills',
                    'priority' => 'Medium',
                    'status' => 'Planned',
                ],
                'skills' => [
                    [
                        'key' => 'php-basics',
                        'skill' => 'PHP Basics',
                        'description' => 'Types, OOP, Composer',
                        'criteria' => json_encode([
                            ['text' => 'Understand PHP syntax'],
                        ], JSON_UNESCAPED_UNICODE),
                        'priority' => 'High',
                        'status' => 'Planned',
                        'order_column' => 0,
                    ],
                    [
                        'key' => 'laravel-basics',
                        'skill' => 'Laravel Basics',
                        'description' => 'Routing and Eloquent',
                        'criteria' => json_encode([
                            ['text' => 'Build CRUD with Eloquent'],
                        ], JSON_UNESCAPED_UNICODE),
                        'priority' => 'Medium',
                        'status' => 'Planned',
                        'order_column' => 1,
                    ],
                ],
            ],
        ]);

        $templateTwo = PdpTemplate::create([
            'user_id' => $user->id,
            'title' => 'Soft Skills',
            'description' => 'Communication skills',
            'published' => true,
            'data' => [
                'version' => 1,
                'pdp' => [
                    'title' => 'Soft Skills',
                    'description' => 'Communication skills',
                    'priority' => 'Medium',
                    'status' => 'Planned',
                ],
                'skills' => [
                    [
                        'key' => 'communication',
                        'skill' => 'Communication',
                        'description' => 'Clear async communication',
                        'criteria' => json_encode([
                            ['text' => 'Write concise status updates'],
                        ], JSON_UNESCAPED_UNICODE),
                        'priority' => 'Medium',
                        'status' => 'Planned',
                        'order_column' => 0,
                    ],
                ],
            ],
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->postJson('/pdps.json', [
                'title' => 'My composed PDP',
                'description' => 'Created from selected templates',
                'priority' => 'High',
                'eta' => 'Q4 2026',
                'status' => 'Planned',
                'template_keys' => [
                    'db-'.$templateOne->id,
                    'db-'.$templateTwo->id,
                ],
            ], [
                'X-CSRF-TOKEN' => 'test-token',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('skills_count', 3);

        $pdpId = (int) $response->json('id');

        $this->assertDatabaseHas('pdps', [
            'id' => $pdpId,
            'user_id' => $user->id,
            'title' => 'My composed PDP',
        ]);

        $this->assertSame(3, PdpSkill::query()->where('pdp_id', $pdpId)->count());
        $this->assertDatabaseHas('pdp_skills', [
            'pdp_id' => $pdpId,
            'skill' => 'PHP Basics',
            'template_skill_key' => 'php-basics',
        ]);
        $this->assertDatabaseHas('pdp_skills', [
            'pdp_id' => $pdpId,
            'skill' => 'Laravel Basics',
            'template_skill_key' => 'laravel-basics',
        ]);
        $this->assertDatabaseHas('pdp_skills', [
            'pdp_id' => $pdpId,
            'skill' => 'Communication',
            'template_skill_key' => 'communication',
        ]);
    }

    public function test_store_returns_validation_error_for_unknown_template_key(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->postJson('/pdps.json', [
                'title' => 'PDP with invalid template',
                'description' => null,
                'priority' => 'Medium',
                'eta' => null,
                'status' => 'Planned',
                'template_keys' => ['db-999999'],
            ], [
                'X-CSRF-TOKEN' => 'test-token',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonPath(
            'message',
            'One or more selected templates are not available.',
        );
        $this->assertSame(0, Pdp::query()->count());
    }
}

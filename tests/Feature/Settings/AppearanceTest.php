<?php

namespace Tests\Feature\Settings;

use App\Enums\Appearance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_user_can_persist_their_own_appearance_preference(): void
    {
        $user = User::factory()->dsp()->create([
            'appearance' => Appearance::System,
        ]);

        $this->actingAs($user)
            ->get(route('appearance.edit'))
            ->assertOk();

        $this->actingAs($user)
            ->patch(route('appearance.update'), [
                'appearance' => Appearance::Dark->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Appearance::Dark, $user->fresh()->appearance);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertSame(Appearance::Dark, $user->fresh()->appearance);
    }

    public function test_appearance_preference_does_not_change_other_users(): void
    {
        $first = User::factory()->dsp()->create([
            'appearance' => Appearance::Light,
        ]);
        $second = User::factory()->admin()->create([
            'appearance' => Appearance::System,
        ]);

        $this->actingAs($first)
            ->patch(route('appearance.update'), [
                'appearance' => Appearance::Dark->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Appearance::Dark, $first->fresh()->appearance);
        $this->assertSame(Appearance::System, $second->fresh()->appearance);
    }

    public function test_guests_cannot_update_appearance(): void
    {
        $this->patch(route('appearance.update'), [
            'appearance' => Appearance::Dark->value,
        ])->assertRedirect(route('login'));
    }
}

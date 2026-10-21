<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Support\MobileNavigation;
use Illuminate\Foundation\Testing\Attributes\UnitTest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MobileNavigationTest extends TestCase
{
    #[UnitTest]
    public function test_role_specific_shortcut_defaults_match_frozen_mdm_13(): void
    {
        $this->assertSame(
            ['scheduled-visits', 'my-availability', 'messages'],
            MobileNavigation::defaultsFor(Role::Dsp),
        );
        $this->assertSame(
            ['scheduled-visits', 'incidents', 'messages'],
            MobileNavigation::defaultsFor(Role::Supervisor),
        );
        $this->assertSame(
            ['operations', 'incidents', 'messages'],
            MobileNavigation::defaultsFor(Role::Admin),
        );
    }

    #[UnitTest]
    public function test_resolve_falls_back_to_defaults_when_stored_shortcuts_missing(): void
    {
        $this->assertSame(
            MobileNavigation::defaultsFor(Role::Admin),
            MobileNavigation::resolveShortcutKeys(Role::Admin, null),
        );
    }

    #[UnitTest]
    public function test_resolve_rejects_unauthorized_and_duplicate_shortcuts(): void
    {
        $resolved = MobileNavigation::resolveShortcutKeys(Role::Dsp, [
            'operations',
            'messages',
            'messages',
        ]);

        $this->assertSame(
            ['scheduled-visits', 'messages', 'my-availability'],
            $resolved,
        );
        $this->assertCount(3, array_unique($resolved));

        foreach ($resolved as $key) {
            $this->assertTrue(MobileNavigation::isAuthorized(Role::Dsp, $key));
            $this->assertNotSame(MobileNavigation::HOME_KEY, $key);
        }
    }

    #[UnitTest]
    public function test_sanitize_rejects_inaccessible_destinations(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MobileNavigation::sanitizeShortcutKeys(Role::Dsp, [
            'operations',
            'incidents',
            'messages',
        ]);
    }

    #[UnitTest]
    public function test_shortcut_catalog_excludes_home_and_stays_role_scoped(): void
    {
        $dsp = MobileNavigation::shortcutKeysFor(Role::Dsp);
        $admin = MobileNavigation::shortcutKeysFor(Role::Admin);

        $this->assertNotContains(MobileNavigation::HOME_KEY, $dsp);
        $this->assertContains('my-availability', $dsp);
        $this->assertNotContains('operations', $dsp);
        $this->assertContains('operations', $admin);
        $this->assertNotContains('my-availability', $admin);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComingSoonController extends Controller
{
    /**
     * @var array<string, array{title: string, description: string, roles: list<Role>}>
     */
    private const MODULES = [
        'messages.index' => [
            'title' => 'Messages',
            'description' => 'Internal messages and announcements will be available in a later phase.',
            'roles' => [Role::Admin, Role::Supervisor, Role::Dsp],
        ],
    ];

    public function __invoke(Request $request): Response
    {
        $routeName = $request->route()?->getName();
        $definition = is_string($routeName) ? (self::MODULES[$routeName] ?? null) : null;
        abort_unless($definition !== null, 404);

        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless(in_array($user->role, $definition['roles'], true), 403);

        $module = str_replace('.index', '', (string) $routeName);

        return Inertia::render('modules/coming-soon', [
            'module' => $module,
            'title' => $definition['title'],
            'description' => $definition['description'],
        ]);
    }
}

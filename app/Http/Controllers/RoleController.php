<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Roles/Index', [
            'roles' => Role::with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::create([
            'name' => $request->name,
            'display_name' => $request->display_name,
            'description' => $request->description,
            'guard_name' => 'web',
        ]);

        if ($request->permissions) {
            $role->syncPermissions($request->permissions);
        }

        $name = $role->display_name ?: $role->name;

        return redirect()->route('roles.index')
            ->with('success', "Role \"{$name}\" created.");
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        if ($role->name === 'superadmin') {
            $request->validate(['name' => 'required|string|max:255|in:superadmin']);
        }

        $role->update([
            'name' => $request->name,
            'display_name' => $request->display_name,
            'description' => $request->description,
        ]);

        if ($request->permissions !== null) {
            $permissions = $request->permissions;

            if ($role->name === 'superadmin' && ! in_array('manage roles', $permissions)) {
                $permissions[] = 'manage roles';
            }

            $role->syncPermissions($permissions);
        }

        $name = $role->display_name ?: $role->name;

        return redirect()->route('roles.index')
            ->with('success', "Role \"{$name}\" updated.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === 'superadmin') {
            return redirect()->route('roles.index')
                ->with('error', 'The superadmin role cannot be deleted.');
        }

        $name = $role->display_name ?: $role->name;

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', "Role \"{$name}\" deleted.");
    }
}

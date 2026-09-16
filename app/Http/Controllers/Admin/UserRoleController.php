<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UserRoleController extends Controller
{
    public function edit(User $user): RedirectResponse
    {
        return redirect()->route('admin.users.edit', $user);
    }

    public function update(): RedirectResponse
    {
        return redirect()->route('admin.users.index');
    }
}

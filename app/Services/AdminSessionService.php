<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminSessionService
{
    public function bindSessionVersion(Request $request, User $user): void
    {
        $request->session()->put('auth_session_version', (int) ($user->session_version ?? 0));
        $request->session()->put('auth_last_activity_at', now()->timestamp);
    }

    public function invalidateAllSessions(User $user): void
    {
        $user->forceFill([
            'session_version' => (int) ($user->session_version ?? 0) + 1,
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }
    }
}

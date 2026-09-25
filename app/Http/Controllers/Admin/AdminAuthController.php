<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Models\User;
use App\Models\UserLoginLog;
use App\Services\AdminDeviceFingerprintService;
use App\Services\AdminLoginAuditService;
use App\Services\AdminSessionService;
use App\Support\MarketerPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AdminAuthController extends Controller
{
    public function __construct(
        private AdminLoginAuditService $loginAudit,
        private AdminSessionService $sessions,
        private AdminDeviceFingerprintService $deviceFingerprints,
    ) {}

    public function showLogin(): View|Response|RedirectResponse
    {
        if (Auth::check()) {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            return redirect()->route(MarketerPortal::homeRouteName($user));
        }

        return response()
            ->view('admin.auth.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function login(AdminLoginRequest $request): RedirectResponse
    {
        if (Auth::check()) {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            return redirect()->route(MarketerPortal::homeRouteName($user));
        }

        $email = trim((string) $request->input('email'));
        $password = (string) $request->input('password');
        $fingerprint = trim((string) $request->input('fingerprint'));
        $remember = false;

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            $this->loginAudit->log(
                $request,
                UserLoginLog::STATUS_FAILED_CREDENTIALS,
                $user,
                $email,
                $fingerprint !== '' ? $fingerprint : null,
                null,
            );

            return back()
                ->withErrors([
                    'email' => 'The email or password you entered is incorrect.',
                ])
                ->withInput($request->except('password'));
        }

        $requiresFingerprint = ! MarketerPortal::isMarketerOnly($user);
        $fingerprintMatched = null;

        if ($requiresFingerprint) {
            $result = $this->deviceFingerprints->assertTrusted($user, $fingerprint);

            if (! $result['ok']) {
                $this->loginAudit->log(
                    $request,
                    UserLoginLog::STATUS_BLOCKED_FINGERPRINT,
                    $user,
                    $email,
                    $fingerprint !== '' ? $fingerprint : null,
                    false,
                );

                return back()
                    ->withErrors([
                        'email' => $result['message'] ?? 'Device unrecognized.',
                    ])
                    ->withInput($request->except('password'));
            }

            $fingerprintMatched = true;
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $this->sessions->bindSessionVersion($request, $user->fresh());

        if ($requiresFingerprint) {
            $request->session()->put('auth_device_fingerprint', $fingerprint);
        }

        $this->loginAudit->log(
            $request,
            UserLoginLog::STATUS_SUCCESS,
            $user,
            $email,
            $requiresFingerprint ? $fingerprint : null,
            $fingerprintMatched,
        );

        return redirect()->route(MarketerPortal::homeRouteName($user));
    }

    public function logout(Request $request): RedirectResponse|SymfonyResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Blade login is not an Inertia page. A normal redirect from an Inertia
        // visit opens it in a modal iframe; location() forces a full-page load.
        return Inertia::location(route('login'));
    }

    public function logoutAllDevices(Request $request): RedirectResponse|SymfonyResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            $this->sessions->invalidateAllSessions($user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location(route('login'));
    }
}

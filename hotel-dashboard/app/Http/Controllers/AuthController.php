<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;

class AuthController extends Controller
{
    /**
     * Read manager credentials from config (not env() directly — env() breaks after config:cache) (#5).
     */
    private function managerEmail(): string
    {
        return config('auth.manager_email', 'manager@dharmahotel.com');
    }

    private function managerPassword(): ?string
    {
        return config('auth.manager_password');
    }

    /**
     * Validate a decrypted remember-cookie payload.
     * Returns true if the payload matches the current manager and is within 30 days.
     */
    private function isValidRememberPayload(mixed $data): bool
    {
        if (!is_array($data) || !isset($data['email'], $data['timestamp'])) {
            return false;
        }
        $daysSince = (now()->timestamp - (int) $data['timestamp']) / 86400;
        return strtolower($data['email']) === strtolower($this->managerEmail())
            && $daysSince < 30;
    }

    public function showLoginForm()
    {
        // Already authenticated via session — go to dashboard
        if (request()->session()->get('manager_authenticated') === true) {
            return redirect('/dashboard');
        }

        // Auto-login via remember cookie (unless user just logged out)
        if (!request()->session()->has('just_logged_out')) {
            $rememberCookie = request()->cookie('manager_remember');
            if ($rememberCookie) {
                try {
                    $data = decrypt($rememberCookie);
                    if ($this->isValidRememberPayload($data)) {
                        request()->session()->put('manager_authenticated', true);
                        request()->session()->put('manager_email', $this->managerEmail());
                        return redirect('/dashboard');
                    }
                } catch (\Exception $e) {
                    Cookie::queue(Cookie::forget('manager_remember'));
                }
            }
        }

        request()->session()->forget('just_logged_out');

        // Pre-fill email from remember cookie so user doesn't have to retype
        $prefilledEmail = '';
        $hasRemember = false;
        $rememberCookie = request()->cookie('manager_remember');
        if ($rememberCookie) {
            try {
                $data = decrypt($rememberCookie);
                if ($this->isValidRememberPayload($data)) {
                    $prefilledEmail = $data['email'];
                    $hasRemember = true;
                }
            } catch (\Exception $e) {
                Cookie::queue(Cookie::forget('manager_remember'));
            }
        }

        return Inertia::render('Auth/Login', [
            'prefilled_email' => $prefilledEmail,
            'has_remember'    => $hasRemember,
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'min:6'],
        ]);

        $remember = $request->boolean('remember');
        $email    = $request->input('email');
        $password = $request->input('password');

        // Manager-only auth using config values (not env() — breaks after config:cache) (#5).
        $managerEmail    = $this->managerEmail();
        $managerPassword = $this->managerPassword();

        // Support both bcrypt hash and plaintext in .env (#13: document to use bcrypt in prod).
        // To hash: php artisan tinker → bcrypt('yourpassword')
        $passwordMatches = $managerPassword && (
            str_starts_with($managerPassword, '$2y$')
                ? Hash::check($password, $managerPassword)
                : ($password === $managerPassword)
        );

        $emailMatches = strtolower($email) === strtolower($managerEmail);

        if (!$emailMatches || !$passwordMatches) {
            return back()->withErrors([
                'email' => 'Email atau password yang Anda masukkan salah.',
            ])->onlyInput('email');
        }

        // Valid — create session
        $request->session()->regenerate();
        $request->session()->put('manager_authenticated', true);
        $request->session()->put('manager_email', $managerEmail);

        if ($remember) {
            // secure=true in production so cookie is HTTPS-only (#6)
            $secure = app()->isProduction();
            $cookie = Cookie::make(
                'manager_remember',
                encrypt(['email' => $managerEmail, 'timestamp' => now()->timestamp]),
                43200, // 30 days in minutes
                '/',
                null,
                $secure, // HTTPS-only in production (#6)
                true,    // httpOnly
                false,
                'lax'
            );
            return redirect()->intended('/dashboard')->cookie($cookie);
        }

        // Clear any existing remember cookie when not checked
        return redirect()->intended('/dashboard')->cookie(Cookie::forget('manager_remember'));
    }

    public function logout(Request $request)
    {
        // Clear authentication
        $request->session()->forget('manager_authenticated');
        $request->session()->forget('manager_email');

        // Mark that user just logged out (prevents auto-login)
        $request->session()->put('just_logged_out', true);

        $request->session()->regenerateToken();

        // Don't clear remember me cookie on logout
        // User will auto-login on next visit if cookie is still valid
        // But not immediately after logout due to 'just_logged_out' flag

        return redirect('/login');
    }
}

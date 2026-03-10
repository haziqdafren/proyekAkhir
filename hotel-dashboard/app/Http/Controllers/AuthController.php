<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        // Don't auto-login if user is already authenticated (prevents redirect loop)
        if (request()->session()->has('manager_authenticated')) {
            return redirect('/dashboard');
        }

        // Check if user has remember me cookie
        $rememberCookie = request()->cookie('manager_remember');

        // Only auto-login if:
        // 1. Remember cookie exists
        // 2. User doesn't have 'just_logged_out' session flag
        if ($rememberCookie && !request()->session()->has('just_logged_out')) {
            try {
                $data = decrypt($rememberCookie);
                $managerEmail = env('MANAGER_EMAIL', 'manager@dharmahotel.com');

                // If cookie is valid and not expired (30 days), auto-login
                if (isset($data['email']) && $data['email'] === $managerEmail) {
                    $timestamp = $data['timestamp'] ?? 0;
                    $daysSinceCreation = (now()->timestamp - $timestamp) / 86400; // 86400 seconds in a day

                    if ($daysSinceCreation < 30) {
                        // Auto-login the user
                        request()->session()->put('manager_authenticated', true);
                        request()->session()->put('manager_email', $managerEmail);
                        return redirect('/dashboard');
                    }
                }
            } catch (\Exception $e) {
                // Invalid cookie, continue to login form
                Cookie::queue(Cookie::forget('manager_remember'));
            }
        }

        // Clear the 'just_logged_out' flag after showing login form
        request()->session()->forget('just_logged_out');

        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'min:6'],
        ]);

        $remember = $request->boolean('remember');

        // Simple manager-only authentication using env credentials
        $managerEmail = env('MANAGER_EMAIL', 'manager@dharmahotel.com');
        $managerPassword = env('MANAGER_PASSWORD');

        // Check if password is hashed or plain text (for backward compatibility)
        $passwordMatches = false;
        if (str_starts_with($managerPassword, '$2y$')) {
            // Hashed password - use Hash::check()
            $passwordMatches = ($credentials['email'] === $managerEmail && Hash::check($credentials['password'], $managerPassword));
        } else {
            // Plain text password (legacy support)
            $passwordMatches = ($credentials['email'] === $managerEmail && $credentials['password'] === $managerPassword);
        }

        if ($passwordMatches) {
            // Create session for manager
            $request->session()->regenerate();
            $request->session()->put('manager_authenticated', true);
            $request->session()->put('manager_email', $managerEmail);

            // Handle "Remember Me" functionality
            if ($remember) {
                // Set cookie for 30 days (43200 minutes)
                cookie()->queue(
                    'manager_remember',
                    encrypt([
                        'email' => $managerEmail,
                        'timestamp' => now()->timestamp
                    ]),
                    43200 // 30 days in minutes (30 * 24 * 60)
                );
            } else {
                // If user unchecks remember me, remove the cookie
                cookie()->queue(cookie()->forget('manager_remember'));
            }

            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'email' => 'Email atau password yang Anda masukkan salah.',
        ])->onlyInput('email');
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

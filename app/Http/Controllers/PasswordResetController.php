<?php

namespace App\Http\Controllers;

use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        Password::sendResetLink($request->only('email'));

        // Always respond the same way regardless of whether the email matched
        // an account, so this endpoint can't be used to enumerate users.
        return response()->json([
            'message' => 'If that email address is registered, a password reset link has been sent.',
        ]);
    }

    public function reset(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->update(['password' => Hash::make($password)]);

                event(new PasswordReset($user));

                Mail::to($user)->queue(new PasswordChangedMail($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $message = match ($status) {
                Password::INVALID_TOKEN => 'This password reset link is invalid or has expired.',
                Password::INVALID_USER => 'We could not find an account for that email address.',
                Password::RESET_THROTTLED => 'Please wait before retrying this password reset.',
                default => 'Unable to reset password.',
            };

            return response()->json(['message' => $message], 422);
        }

        return response()->json(['message' => 'Your password has been reset.']);
    }
}

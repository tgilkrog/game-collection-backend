<?php

namespace App\Http\Controllers;

use App\Models\User;

class EmailVerificationController extends Controller
{
    public function verify(int $id, string $hash)
    {
        $user = User::findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect(config('app.frontend_url').'/?verified=1');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Password, Auth, Cache};
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken; // Add this


class AuthController extends Controller
{
    protected $cacheTtl = 300; // 5 minutes

    /**
     * Student/Instructor registration
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'role' => 'nullable|in:student,instructor',
        ]);

        $user = User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
            'is_verified' => false,
            'is_active' => true,
        ]);

        $user->assignRole($validated['role'] ?? 'student');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Login with rate limiting
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials']]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages(['email' => ['Account is deactivated']]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'user' => new UserResource($user),
            'access_token' => $token,
        ]);
    }

    /**
     * Get user profile with caching
     */
    public function profile(Request $request)
    {
        $cacheKey = "user_profile_{$request->user()->id}";

        $data = Cache::remember($cacheKey, $this->cacheTtl, function () use ($request) {
            return [
                'user' => new UserResource($request->user()),
            ];
        });

        return response()->json(['success' => true, ...$data]);
    }

    /**
     * Update profile with media handling
     */
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|image|max:2048',
        ]);

        $user = $request->user();

        if ($request->hasFile('avatar')) {
            $user->clearMediaCollection('avatar');
            $user->addMedia($request->file('avatar'))->toMediaCollection('avatar');
        }

        $user->update($validated);

        Cache::forget("user_profile_{$user->id}");

        return response()->json([
            'success' => true,
            'user' => new UserResource($user->fresh()),
            'message' => 'Profile updated successfully',
        ]);
    }

    /**
     * Logout and revoke token
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        Cache::forget("user_profile_{$user->id}");

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Password reset request
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['success' => true, 'message' => 'Reset link sent'])
            : response()->json(['success' => false, 'message' => 'Unable to send link'], 400);
    }

    /**
     * Reset password with token
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->remember_token = Str::random(60);
                $user->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['success' => true, 'message' => 'Password reset'])
            : response()->json(['success' => false, 'message' => 'Invalid token'], 400);
    }
}

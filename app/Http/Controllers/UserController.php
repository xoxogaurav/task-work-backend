<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function profile()
    {
        return response()->json(auth()->user());
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name' => 'string|max:255',
            'country' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:18|max:120',
            'phone_number' => 'nullable|string|max:20',
            'bio' => 'nullable|string',
            'timezone' => 'nullable|string',
            'language' => 'nullable|string',
            'email_notifications' => 'boolean',
            'current_password' => 'required_with:new_password',
            'new_password' => 'nullable|min:6|confirmed',
        ]);

        if ($request->has('current_password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json(['message' => 'Current password is incorrect'], 422);
            }
        }

        $updateData = $request->only([
            'name', 'country', 'age', 'phone_number', 'bio',
            'timezone', 'language', 'email_notifications'
        ]);

        if ($request->filled('new_password')) {
            $updateData['password'] = Hash::make($request->new_password);
        }

        $user->update($updateData);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    public function leaderboard()
    {
        $users = User::where('is_admin', false)
            ->orderByDesc('balance')
            ->select('name', 'balance', 'tasks_completed', 'profile_picture')
            ->limit(10)
            ->get();

        return response()->json($users);
    }
}
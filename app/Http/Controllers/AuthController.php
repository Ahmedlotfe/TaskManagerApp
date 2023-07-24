<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $attributes = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|confirmed'
        ]);

        $attributes['password'] = bcrypt($request->get('password'));

        $user = User::create($attributes);

        $token = $user->createToken('myapptoken')->plainTextToken;

        $response = [
            "user" => $user,
            "token" => $token
        ];

        return response($response, 201);
    }

    public function login(Request $request)
    {
        $attributes = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        $user = User::where('email', $attributes['email'])->first();

        if (!$user || !Hash::check($attributes['password'], $user->password)) {
            return response([
                "message" => "Bad Creds"
            ]);
        }

        Auth::logoutOtherDevices($request);

        $token = $user->createToken('myapptoken')->plainTextToken;

        $response = [
            "user" => $user,
            "token" => $token
        ];

        return response($response, 201);
    }


    public function logout(Request $request)
    {
        auth()->user()->tokens()->delete();

        return [
            'message' => 'Logged out'
        ];
    }
}

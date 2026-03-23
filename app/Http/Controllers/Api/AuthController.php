<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register user
     * 
     * @bodyParam name string required Nama pengguna. Contoh: test
     * @bodyParam email string required Email pengguna. Contoh: test@example.com
     * @bodyParam password string required Password. Contoh: password
     * @bodyParam password_confirmation string required password_confirmation. Contoh: password
     * 
     * @response 201 {
      *  "user": {
       *     "name": "test",
       *     "email": "test@example.com",
       *     "updated_at": "2025-05-19T19:15:03.000000Z",
       *     "created_at": "2025-05-19T19:15:03.000000Z",
       *     "id": 1
       * },
       *  "token": "jwt-token"
    *    }
     */
    public function register(Request $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $token = JWTAuth::fromUser($user);
        return response()->json(compact('user', 'token'), 201);
    }

    /**
     * Login user
     * 
     * @bodyParam email string required Email pengguna. Contoh: test@example.com
     * @bodyParam password string required Password pengguna. Contoh: password
     *
     * @response 200 {
     *   "token": "jwt-token"
     * }
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        return response()->json(compact('token'));
    }
}

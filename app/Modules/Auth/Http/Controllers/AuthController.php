<?php

namespace App\Modules\Auth\Http\Controllers;

use Illuminate\Http\Request;

use App\Modules\Auth\Actions\LoginUser;
use App\Modules\Auth\Actions\RegisterUser;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\RegisterRequest;

class AuthController
{
    public function register(RegisterRequest $request, RegisterUser $registerUser)
    {
        $result = $registerUser->handle($request->validated());

        return response()->json($result, 201);
    }

    public function login(LoginRequest $request, LoginUser $loginUser)
    {
        $result = $loginUser->handle($request->validated());

        return response()->json($result);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}

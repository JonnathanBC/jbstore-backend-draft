<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Actions\LoginAction;
use App\Modules\Auth\Actions\RegisterAction;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use Illuminate\Http\Request;

class AuthController
{
    public function register(RegisterRequest $request, RegisterAction $action)
    {
        $result = $action->execute($request->validated());

        return response()->json($result, 201);
    }

    public function login(LoginRequest $request, LoginAction $action)
    {
        $result = $action->execute($request->validated());

        return response()->json($result);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}

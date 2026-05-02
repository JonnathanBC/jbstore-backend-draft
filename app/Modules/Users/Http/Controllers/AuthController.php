<?php

namespace App\Modules\Users\Http\Controllers;

use App\Modules\Users\Actions\LoginAction;
use App\Modules\Users\Actions\RegisterAction;
use App\Modules\Users\Http\Requests\LoginRequest;
use App\Modules\Users\Http\Requests\RegisterRequest;

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

    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}

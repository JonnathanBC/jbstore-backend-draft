<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Modules\User\Actions\LoginAction;
use App\Modules\User\Actions\RegisterAction;
use App\Modules\User\Http\Requests\RegisterRequest;

use Illuminate\Http\Request;

class AuthController extends Controller
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

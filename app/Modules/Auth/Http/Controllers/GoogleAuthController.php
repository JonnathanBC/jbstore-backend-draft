<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Actions\ExchangeGoogleLoginCode;
use App\Modules\Auth\Actions\FindOrCreateGoogleUser;
use App\Modules\Auth\Actions\IssueGoogleLoginCode;
use App\Modules\Auth\Http\Requests\GoogleExchangeRequest;
use App\Modules\Auth\Http\Requests\GoogleRedirectRequest;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController
{
    public function redirect(GoogleRedirectRequest $request)
    {
        // stateless: la API no tiene sesión, el state lo verifica el front contra su cookie
        $url = Socialite::driver('google')
            ->stateless()
            ->with(['state' => $request->validated('state')])
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    public function callback(
        Request $request,
        FindOrCreateGoogleUser $findOrCreateUser,
        IssueGoogleLoginCode $issueCode,
    ) {
        $user = $findOrCreateUser->handle(Socialite::driver('google')->stateless()->user());

        if (! $user) {
            return redirect(config('app.frontend_url').'/login?error=google');
        }

        $query = http_build_query([
            'code' => $issueCode->handle($user),
            'state' => (string) $request->query('state', ''),
        ]);

        return redirect(config('app.frontend_url')."/api/auth/google/callback?{$query}");
    }

    public function exchange(GoogleExchangeRequest $request, ExchangeGoogleLoginCode $exchangeCode)
    {
        return response()->json($exchangeCode->handle($request->validated('code')));
    }
}

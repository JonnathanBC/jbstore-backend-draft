<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\CompleteProfile;
use App\Modules\Users\Http\Requests\CompleteProfileRequest;
use App\Modules\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Listado liviano de usuarios para selects/autocomplete del admin.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();

        $users = User::query()
            ->select(['id', 'name', 'last_name', 'email'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($users);
    }

    /**
     * Paginado de usuarios para la pantalla de administración.
     */
    public function listing(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();

        $query = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });

        return $this->paginated(
            $query,
            $request,
            ['created_at'],
        );
    }

    /**
     * Show the form for creating the resource.
     */
    public function create(): never
    {
        abort(404);
    }

    /**
     * Store the newly created resource in storage.
     */
    public function store(Request $request): never
    {
        abort(404);
    }

    /**
     * Display the resource.
     */
    public function show()
    {
        //
    }

    /**
     * Show the form for editing the resource.
     */
    public function edit()
    {
        //
    }

    /**
     * Update the resource in storage.
     */
    public function update(Request $request)
    {
        //
    }

    /**
     * Remove the resource from storage.
     */
    public function destroy(): never
    {
        abort(404);
    }

    public function me(Request $request)
    {
        return $this->profileResponse($request->user());
    }

    public function updateProfile(CompleteProfileRequest $request, CompleteProfile $completeProfile): JsonResponse
    {
        return $this->profileResponse(
            $completeProfile->handle($request->user(), $request->validated()),
        );
    }

    // El front decide si mostrar el form de perfil en el checkout con este flag
    private function profileResponse(User $user): JsonResponse
    {
        return response()->json([
            ...$user->toArray(),
            'profile_complete' => $user->isProfileComplete(),
        ]);
    }
}

<?php

namespace App\Modules\Addresses\Http\Controllers;

use App\Modules\Addresses\Http\Requests\StoreAddressRequest;
use App\Modules\Addresses\Models\Address;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = Address::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($addresses);
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $address = Address::create([
            ...$data,
            'user_id' => $user->id,
            'receiver' => $user->id,
            'receiver_info' => [
                'name' => trim($user->name . ' ' . $user->last_name),
                'phone' => $data['phone'],
            ],
        ]);

        return response()->json($address, 201);
    }
}

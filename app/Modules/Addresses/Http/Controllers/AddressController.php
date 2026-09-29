<?php

namespace App\Modules\Addresses\Http\Controllers;

use App\Modules\Addresses\Http\Requests\StoreAddressRequest;
use App\Modules\Addresses\Models\Address;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(): JsonResponse
    {
        $addresses = Address::query()->latest()->get();

        return response()->json($addresses);
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $address = DB::transaction(function () use ($data, $user) {
            $address = Address::create([
                ...$data,
                'receiver' => 1, // true
                'receiver_info' => [
                    'name' => trim($user->name . ' ' . $user->last_name),
                    'phone' => $data['phone'],
                ],
            ]);

            $isFirst = ! Address::whereKeyNot($address->getKey())->exists();

            if ($address->is_default || $isFirst) {
                $address->markAsDefault();
            }

            return $address->fresh();
        });

        return response()->json($address, 201);
    }

    public function setDefault(Address $address): JsonResponse
    {
        $address->markAsDefault();

        return response()->json($address->fresh());
    }
}

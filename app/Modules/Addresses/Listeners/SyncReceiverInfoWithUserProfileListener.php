<?php

namespace App\Modules\Addresses\Listeners;

use App\Concerns\Scopes\OwnedByUserScope;
use App\Modules\Addresses\Models\Address;
use App\Modules\Users\Events\UserProfileUpdated;

class SyncReceiverInfoWithUserProfileListener
{
    public function handle(UserProfileUpdated $event): void
    {
        $user = $event->user;

        // Filtro explícito por user_id: no dependemos de quién esté autenticado
        $addresses = Address::withoutGlobalScope(OwnedByUserScope::class)
            ->where('user_id', $user->id)
            ->get();

        foreach ($addresses as $address) {
            // Solo el documento: el nombre/teléfono del receptor pudo editarse a mano por dirección
            $address->update([
                'receiver_info' => [
                    ...($address->receiver_info ?? []),
                    'document_number' => $user->document_number,
                ],
            ]);
        }
    }
}

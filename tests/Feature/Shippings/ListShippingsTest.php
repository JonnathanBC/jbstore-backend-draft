<?php

namespace Tests\Feature\Shippings;

use App\Modules\Drivers\Models\Driver;
use App\Modules\Orders\Models\Order;
use App\Modules\Shippings\Enums\ShippingStatusEnum;
use App\Modules\Shippings\Models\Shipping;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListShippingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeShipping(
        string $plate,
        string $name,
        string $lastName,
        ShippingStatusEnum $status = ShippingStatusEnum::Pending,
    ): Shipping {
        // Sin eventos: el observer genera el PDF del ticket y no es parte de este test
        $order = Order::withoutEvents(fn () => Order::create([
            'user_id' => User::factory()->create()->id,
            'payment_id' => 'pay_' . $plate,
            'content' => [],
            'address' => [],
            'total' => 100,
        ]));

        $driver = Driver::create([
            'user_id' => User::factory()->create(['name' => $name, 'last_name' => $lastName])->id,
            'type' => 'car',
            'license_plate' => $plate,
        ]);

        $shipping = Shipping::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
        ]);
        $shipping->status = $status;
        $shipping->save();

        return $shipping;
    }

    private function seedShippings(): array
    {
        return [
            'juan' => $this->makeShipping('ABC-123', 'Juan', 'Perez'),
            'maria' => $this->makeShipping('XYZ-999', 'Maria', 'Gomez', ShippingStatusEnum::Failed),
            'pedro' => $this->makeShipping('QWE-555', 'Pedro', 'Gomez', ShippingStatusEnum::Completed),
        ];
    }

    public function test_without_filters_returns_all_shippings(): void
    {
        $this->seedShippings();
        Sanctum::actingAs($this->adminUser('manage.shippings'));

        $this->getJson('/api/admin/shippings')
            ->assertOk()
            ->assertJsonPath('total', 3);
    }

    public function test_search_by_license_plate(): void
    {
        $shippings = $this->seedShippings();
        Sanctum::actingAs($this->adminUser('manage.shippings'));

        $this->getJson('/api/admin/shippings?search=xyz')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $shippings['maria']->id);
    }

    public function test_search_by_driver_name_is_case_insensitive(): void
    {
        $shippings = $this->seedShippings();
        Sanctum::actingAs($this->adminUser('manage.shippings'));

        $this->getJson('/api/admin/shippings?search=jUAN')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $shippings['juan']->id);

        $this->getJson('/api/admin/shippings?search=gomez')
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    public function test_search_by_driver_full_name(): void
    {
        $shippings = $this->seedShippings();
        Sanctum::actingAs($this->adminUser('manage.shippings'));

        $this->getJson('/api/admin/shippings?search=' . urlencode('pedro gomez'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $shippings['pedro']->id);
    }

    public function test_filter_by_status(): void
    {
        $shippings = $this->seedShippings();
        Sanctum::actingAs($this->adminUser('manage.shippings'));

        $this->getJson('/api/admin/shippings?status=failed')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $shippings['maria']->id);
    }

    public function test_empty_status_is_ignored(): void
    {
        $this->seedShippings();
        Sanctum::actingAs($this->adminUser('manage.shippings'));

        $this->getJson('/api/admin/shippings?status=')
            ->assertOk()
            ->assertJsonPath('total', 3);
    }

    public function test_search_and_status_combined(): void
    {
        $shippings = $this->seedShippings();
        Sanctum::actingAs($this->adminUser('manage.shippings'));

        $this->getJson('/api/admin/shippings?search=gomez&status=completed')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $shippings['pedro']->id);

        $this->getJson('/api/admin/shippings?search=juan&status=failed')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_invalid_status_is_rejected(): void
    {
        Sanctum::actingAs($this->adminUser('manage.shippings'));

        $this->getJson('/api/admin/shippings?status=nope')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_non_admin_cannot_list_shippings(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/shippings')->assertForbidden();
    }
}

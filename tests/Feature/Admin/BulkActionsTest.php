<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function order(array $attrs = []): Order
    {
        return Order::create($attrs + [
            'payment_method' => 'delivery',
            'customer_name' => 'Ada Obi',
            'customer_phone' => '0801 234 5678',
            'city' => 'Ikeja',
            'shipping_country' => 'NG',
            'subtotal' => 10000,
            'total' => 10000,
            'currency_code' => 'NGN',
        ]);
    }

    public function test_bulk_actions_require_login(): void
    {
        $this->post('/admin/products/bulk', ['action' => 'delete', 'ids' => [1]])->assertRedirect('/admin/login');
    }

    public function test_products_bulk_publish_feature_move_and_delete(): void
    {
        $this->actingAsAdmin();
        $shoes = Category::factory()->create();
        [$a, $b, $c] = Product::factory()->count(3)->create(['status' => 'draft', 'is_featured' => false]);

        $this->post('/admin/products/bulk', ['action' => 'publish', 'ids' => [$a->id, $b->id]])->assertSessionHasNoErrors();
        $this->assertSame(['published', 'published', 'draft'], [$a->fresh()->status, $b->fresh()->status, $c->fresh()->status]);

        $this->post('/admin/products/bulk', ['action' => 'feature', 'ids' => [$c->id]]);
        $this->assertTrue($c->fresh()->is_featured);

        $this->post('/admin/products/bulk', ['action' => 'category', 'value' => (string) $shoes->id, 'ids' => [$a->id, $c->id]]);
        $this->assertSame($shoes->id, $a->fresh()->category_id);

        $this->post('/admin/products/bulk', ['action' => 'availability', 'value' => 'out_of_stock', 'ids' => [$b->id]]);
        $this->assertSame('out_of_stock', $b->fresh()->availability);

        $this->post('/admin/products/bulk', ['action' => 'delete', 'ids' => [$a->id, $b->id]])->assertSessionHas('success', '2 products deleted.');
        $this->assertSame([$c->id], Product::pluck('id')->all());
    }

    public function test_bulk_rejects_unknown_actions_values_and_oversized_selections(): void
    {
        $this->actingAsAdmin();
        $p = Product::factory()->create();

        $this->post('/admin/products/bulk', ['action' => 'drop_table', 'ids' => [$p->id]])->assertSessionHasErrors('action');
        $this->post('/admin/products/bulk', ['action' => 'availability', 'value' => 'bogus', 'ids' => [$p->id]])->assertSessionHasErrors('value');
        $this->post('/admin/products/bulk', ['action' => 'category', 'value' => '999', 'ids' => [$p->id]])->assertSessionHasErrors('value');
        $this->post('/admin/products/bulk', ['action' => 'delete', 'ids' => []])->assertSessionHasErrors('ids');
        $this->post('/admin/products/bulk', ['action' => 'delete', 'ids' => ['1 OR 1=1']])->assertSessionHasErrors('ids.0');
        $this->post('/admin/products/bulk', ['action' => 'delete', 'ids' => range(1, 101)])->assertSessionHasErrors('ids');
        $this->assertSame(1, Product::count());
    }

    public function test_categories_bulk_delete_moves_or_uncategorises_products(): void
    {
        $this->actingAsAdmin();
        [$a, $b, $keep] = Category::factory()->count(3)->create();
        $p = Product::factory()->create(['category_id' => $a->id]);

        // Products inside: a destination is required, and it can't be one being deleted.
        $this->post('/admin/categories/bulk', ['action' => 'delete', 'ids' => [$a->id, $b->id]])->assertSessionHasErrors('value');
        $this->post('/admin/categories/bulk', ['action' => 'delete', 'value' => (string) $b->id, 'ids' => [$a->id, $b->id]])->assertSessionHasErrors('value');

        $this->post('/admin/categories/bulk', ['action' => 'delete', 'value' => (string) $keep->id, 'ids' => [$a->id, $b->id]])->assertSessionHasNoErrors();
        $this->assertSame([$keep->id], Category::pluck('id')->all());
        $this->assertSame($keep->id, $p->fresh()->category_id);

        $this->post('/admin/categories/bulk', ['action' => 'hide', 'ids' => [$keep->id]]);
        $this->assertFalse($keep->fresh()->is_published);
    }

    public function test_coupons_bulk_switch_off_and_delete(): void
    {
        $this->actingAsAdmin();
        $a = Coupon::create(['code' => 'A10', 'discount_type' => 'percent', 'amount' => 10, 'is_active' => true]);
        $b = Coupon::create(['code' => 'B10', 'discount_type' => 'percent', 'amount' => 10, 'is_active' => true]);

        $this->post('/admin/coupons/bulk', ['action' => 'deactivate', 'ids' => [$a->id, $b->id]]);
        $this->assertFalse($a->fresh()->is_active);
        $this->post('/admin/coupons/bulk', ['action' => 'delete', 'ids' => [$a->id]]);
        $this->assertSame(['B10'], Coupon::pluck('code')->all());
    }

    public function test_orders_bulk_status_and_delete_keeps_paid_orders(): void
    {
        $this->actingAsAdmin();
        $unpaid = $this->order();
        $paid = $this->order(['payment_status' => 'paid']);

        $this->post('/admin/orders/bulk', ['action' => 'status', 'value' => 'confirmed', 'ids' => [$unpaid->id, $paid->id]])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $paid->fresh()->status);
        $this->post('/admin/orders/bulk', ['action' => 'status', 'value' => 'teleported', 'ids' => [$paid->id]])->assertSessionHasErrors('value');

        $this->post('/admin/orders/bulk', ['action' => 'delete', 'ids' => [$unpaid->id, $paid->id]]);
        $this->assertSame([$paid->id], Order::pluck('id')->all());
    }

    public function test_export_selected_rows_only(): void
    {
        $this->actingAsAdmin();
        $a = Product::factory()->create(['name' => 'Alpha Lamp']);
        Product::factory()->create(['name' => 'Beta Chair']);

        $csv = $this->get("/admin/products/export?ids={$a->id}")->streamedContent();
        $this->assertStringContainsString('Alpha Lamp', $csv);
        $this->assertStringNotContainsString('Beta Chair', $csv);
    }
}

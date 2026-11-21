<?php

namespace Tests\Feature\Admin;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Support\CsvExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ListsAndExportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        BusinessSetting::current()->update(['shop_enabled' => true, 'business_name' => 'Kettle Co']);
        BusinessSetting::forgetResolved();
        $this->actingAsAdmin();
    }

    protected function order(array $attrs = []): Order
    {
        $order = Order::create($attrs + [
            'payment_method' => 'delivery',
            'customer_name' => 'Ada Obi',
            'customer_phone' => '0801 234 5678',
            'customer_email' => null,
            'city' => 'Ikeja',
            'shipping_country' => 'NG',
            'shipping_state' => 'LA',
            'subtotal' => 10000,
            'total' => 10000,
            'currency_code' => 'NGN',
        ]);
        if (isset($attrs['created_at'])) {
            $order->forceFill(['created_at' => $attrs['created_at']])->saveQuietly();
        }

        return $order;
    }

    protected function csv($response): array
    {
        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $lines = array_values(array_filter(preg_split('/\r?\n/', substr($body, 3))));

        return array_map('str_getcsv', $lines);
    }

    public function test_customer_key_matches_phone_formats_and_falls_back_to_email(): void
    {
        $this->assertSame('p8012345678', Order::customerKey('0801 234 5678', null));
        $this->assertSame('p8012345678', Order::customerKey('+234 (801) 234-5678', 'x@y.z'));
        $this->assertSame(Order::customerKey('', 'Ada@Example.com'), Order::customerKey(null, ' ada@example.com'));
        $this->assertNull(Order::customerKey('', ''));
    }

    public function test_customers_are_grouped_by_phone_with_totals(): void
    {
        $this->order(['customer_name' => 'Ada', 'total' => 5000, 'created_at' => now()->subDays(10)]);
        $this->order(['customer_name' => 'Ada Obi', 'customer_phone' => '+2348012345678', 'customer_email' => 'ada@example.com', 'total' => 15000]);
        $this->order(['customer_name' => 'Ada Obi', 'total' => 99999, 'status' => 'cancelled']);
        $this->order(['customer_name' => 'Bola', 'customer_phone' => '0802 000 0000', 'total' => 2000, 'shipping_state' => 'FC']);

        $this->get('/admin/customers')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Admin/Customers/Index')
            ->has('customers.data', 2)
            ->where('customers.data.0.name', 'Bola')
            ->where('customers.data.1.name', 'Ada Obi')
            ->where('customers.data.1.orders_count', 3)
            ->where('customers.data.1.email', 'ada@example.com')
            ->where('counts', ['' => 2, 'new' => 1, 'returning' => 1, 'inactive' => 0]));

        // Filters, search and sorting.
        $this->get('/admin/customers?segment=returning')->assertInertia(fn (Assert $p) => $p->has('customers.data', 1)->where('customers.data.0.name', 'Ada Obi'));
        $this->get('/admin/customers?search=bola')->assertInertia(fn (Assert $p) => $p->has('customers.data', 1)->where('customers.data.0.name', 'Bola'));
        $this->get('/admin/customers?state=FC')->assertInertia(fn (Assert $p) => $p->has('customers.data', 1)->where('customers.data.0.name', 'Bola'));
        $this->get('/admin/customers?contact=email')->assertInertia(fn (Assert $p) => $p->has('customers.data', 1));
        $this->get('/admin/customers?min_spent=10000')->assertInertia(fn (Assert $p) => $p->has('customers.data', 1)->where('customers.data.0.name', 'Ada Obi'));
        $this->get('/admin/customers?sort=spent')->assertInertia(fn (Assert $p) => $p->where('customers.data.0.name', 'Ada Obi'));
        $this->get('/admin/customers?per_page=10&sort=bogus')->assertInertia(fn (Assert $p) => $p->where('filters.per_page', 10)->where('filters.sort', 'recent'));
        $this->get('/admin/customers?from='.now()->subDays(12)->toDateString().'&to='.now()->subDays(8)->toDateString())
            ->assertInertia(fn (Assert $p) => $p->has('customers.data', 1)->where('customers.data.0.name', 'Ada Obi'));

        // Detail page: cancelled orders don't count towards spend.
        $this->get('/admin/customers/p8012345678')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Admin/Customers/Show')
            ->where('customer.orders_count', 3)
            ->where('customer.cancelled_count', 1)
            ->has('orders', 3));
        $this->get('/admin/customers/p0000000001')->assertNotFound();

        // Orders can be filtered to one customer.
        $this->get('/admin/orders?customer=p8012345678')->assertInertia(fn (Assert $p) => $p->has('orders.data', 3)->where('customerName', 'Ada Obi'));
    }

    public function test_customers_export_csv_with_filters(): void
    {
        $this->order(['customer_name' => 'Ada Obi', 'total' => 5000]);
        $this->order(['customer_name' => '=HYPERLINK("x")', 'customer_phone' => '0802 000 0000', 'total' => 2000]);

        $response = $this->get('/admin/customers/export?sort=spent');
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('kettle-co-customers-', $response->headers->get('content-disposition'));

        $rows = $this->csv($response);
        $this->assertSame(['Name', 'Phone', 'WhatsApp', 'Email', 'City', 'State', 'Country', 'Orders', 'Total spent (NGN)', 'Average order (NGN)', 'First order', 'Last order'], $rows[0]);
        $this->assertCount(3, $rows);
        $this->assertSame('Ada Obi', $rows[1][0]);
        $this->assertSame('Lagos', $rows[1][5]);
        $this->assertSame('5000', $rows[1][8]);
        // Formula injection is neutralised.
        $this->assertSame("'=HYPERLINK(\"x\")", $rows[2][0]);

        $this->assertCount(2, $this->csv($this->get('/admin/customers/export?search=ada')));
    }

    public function test_orders_filters_sorting_and_export(): void
    {
        $this->order(['total' => 3000, 'payment_status' => 'paid']);
        $this->order(['total' => 9000, 'fulfilment' => 'pickup', 'payment_method' => 'bank_transfer', 'coupon_code' => 'SAVE10']);

        $this->get('/admin/orders?payment_status=paid')->assertInertia(fn (Assert $p) => $p->has('orders.data', 1));
        $this->get('/admin/orders?fulfilment=pickup&payment_method=bank_transfer')->assertInertia(fn (Assert $p) => $p->has('orders.data', 1));
        $this->get('/admin/orders?sort=total_asc')->assertInertia(fn (Assert $p) => $p->where('orders.data.0.total', '₦3,000'));
        $this->get('/admin/orders?search=save10')->assertInertia(fn (Assert $p) => $p->has('orders.data', 1));
        $this->get('/admin/orders?from=2000-01-01&to=2000-01-02')->assertInertia(fn (Assert $p) => $p->has('orders.data', 0));

        $rows = $this->csv($this->get('/admin/orders/export?payment_status=paid'));
        $this->assertCount(2, $rows);
        $this->assertSame('Order', $rows[0][0]);
        $this->assertSame('Paid', $rows[1][4]);
        $this->assertSame('3000', $rows[1][21]);
    }

    public function test_products_categories_and_coupons_lists_filter_paginate_and_export(): void
    {
        $shoes = Category::factory()->create(['name' => 'Shoes', 'is_published' => false]);
        Category::factory()->create(['name' => 'Bags']);
        Product::factory()->count(12)->create(['category_id' => $shoes->id, 'availability' => 'in_stock']);
        Product::factory()->create(['category_id' => null, 'name' => 'Rare Boot', 'availability' => 'out_of_stock', 'is_featured' => true]);

        $this->get('/admin/products?per_page=10')->assertInertia(fn (Assert $p) => $p->has('products.data', 10)->where('products.total', 13));
        $this->get('/admin/products?availability=out_of_stock')->assertInertia(fn (Assert $p) => $p->has('products.data', 1));
        $this->get('/admin/products?featured=1')->assertInertia(fn (Assert $p) => $p->where('filters.tag', 'featured'));
        $this->get('/admin/products?category=none')->assertInertia(fn (Assert $p) => $p->has('products.data', 1));
        $this->assertCount(13, $this->csv($this->get('/admin/products/export?category='.$shoes->id)));

        $this->get('/admin/categories?visibility=hidden')->assertInertia(fn (Assert $p) => $p->has('categories.data', 1)->where('categories.data.0.name', 'Shoes')->has('allCategories', 2));
        $this->get('/admin/categories?search=bag')->assertInertia(fn (Assert $p) => $p->has('categories.data', 1));
        $this->assertSame('Shoes', $this->csv($this->get('/admin/categories/export?sort=products'))[1][1]);

        Coupon::create(['code' => 'WELCOME10', 'discount_type' => 'percent', 'amount' => 10, 'is_active' => true]);
        Coupon::create(['code' => 'OFF', 'discount_type' => 'fixed_cart', 'amount' => 500, 'is_active' => false]);
        $this->get('/admin/coupons?status=disabled')->assertInertia(fn (Assert $p) => $p->has('coupons.data', 1)->where('coupons.total', 1)->where('counts.active', 1));
        $this->get('/admin/coupons?type=percent')->assertInertia(fn (Assert $p) => $p->has('coupons.data', 1));
        $this->get('/admin/coupons?per_page=10&page=2')->assertInertia(fn (Assert $p) => $p->has('coupons.data', 0));
        $this->assertCount(3, $this->csv($this->get('/admin/coupons/export')));
    }

    public function test_visitors_export_and_csv_cells(): void
    {
        $this->get('/admin/analytics/visitors/export?period=30d')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertSame("'-x", CsvExport::cell('-x'));
        $this->assertSame('-5', CsvExport::cell('-5'));
        $this->assertSame('Yes', CsvExport::cell(true));
    }

    public function test_exports_need_login(): void
    {
        auth()->logout();
        $this->get('/admin/customers/export')->assertRedirect('/admin/login');
        $this->get('/admin/orders/export')->assertRedirect('/admin/login');
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RotateOrderLink extends Command
{
    protected $signature = 'security:rotate-order-link {number : Customer-facing order number}';

    protected $description = 'Revoke one private order link and make a replacement available in Admin Orders';

    public function handle(): int
    {
        $order = Order::query()->where('number', $this->argument('number'))->first();
        if (! $order) {
            $this->error('Order not found.');

            return self::FAILURE;
        }
        $order->forceFill(['token' => Str::random(40)])->saveOrFail();
        $this->info("Link replaced for order #{$order->number}. Obtain the new customer link from Admin Orders.");

        return self::SUCCESS;
    }
}

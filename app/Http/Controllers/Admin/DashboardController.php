<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\BusinessSetting;
use App\Support\Media;
use App\Support\Money;
use App\Support\WhatsApp;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $row = fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'price' => Money::format($p->price),
            'status' => $p->status,
            'category' => $p->category?->name,
            'thumb' => Media::url($p->primaryImage?->path, 'sm'),
            'created_at' => $p->created_at?->diffForHumans(),
            'updated_at' => $p->updated_at?->diffForHumans(),
        ];

        $counts = Product::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as published', [Product::STATUS_PUBLISHED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as drafts', [Product::STATUS_DRAFT])
            ->selectRaw('SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured')
            ->first();

        $settings = BusinessSetting::current();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'products' => (int) $counts->total,
                'published' => (int) $counts->published,
                'drafts' => (int) $counts->drafts,
                'featured' => (int) $counts->featured,
                'categories' => Category::query()->count(),
            ],
            'recentlyAdded' => Product::query()->with(['primaryImage', 'category:id,name'])->latest()->latest('id')->limit(5)->get()->map($row),
            'recentlyUpdated' => Product::query()->with(['primaryImage', 'category:id,name'])->latest('updated_at')->latest('id')->limit(5)->get()->map($row),
            // Setup checklist so a new business owner knows what is still missing.
            'setup' => [
                ['label' => 'Upload your logo', 'done' => filled($settings->logo_path), 'href' => '/admin/settings?tab=business'],
                ['label' => 'Add your WhatsApp number', 'done' => WhatsApp::normalize($settings->whatsapp_number) !== null, 'href' => '/admin/settings?tab=whatsapp'],
                ['label' => 'Create a category', 'done' => Category::query()->exists(), 'href' => '/admin/categories/create'],
                ['label' => 'Add your first product', 'done' => Product::query()->exists(), 'href' => '/admin/products/create'],
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceItem;
use App\Support\Gallery;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MarketplaceController extends Controller
{
    public function index(Request $request)
    {
        $category = trim((string) $request->get('category', ''));
        $q = trim((string) $request->get('q', ''));

        $available = MarketplaceItem::where('status', 'available');
        $total = (clone $available)->count();

        $items = $available
            ->when($category !== '', fn ($qr) => $qr->where('category', $category))
            ->when($q !== '', function ($qr) use ($q) {
                $qr->where(function ($qr2) use ($q) {
                    $qr2->where('title', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->with('seller', 'media')
            ->latest()
            ->paginate(16)
            ->withQueryString();

        $me = $request->user();

        return Inertia::render('Marketplace/Index', [
            'items' => $items->through(fn (MarketplaceItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => Str::limit($item->description, 100),
                'cover_url' => $item->coverUrl(),
                'gallery_count' => $item->galleryCount(),
                'category_label' => MarketplaceItem::CATEGORIES[$item->category] ?? $item->category,
                // A listing with no price is an invitation to make an offer.
                'price_label' => $item->price > 0 ? '৳'.number_format($item->price) : 'Negotiable',
                'location' => $item->location ?: 'Campus',
                'seller' => [
                    'full_name' => $item->seller->full_name,
                    'avatar_url' => $item->seller->avatar_url,
                    'avatar_initials' => $item->seller->avatar_initials,
                ],
                'url' => route('marketplace.show', $item),
                'is_mine' => $me !== null && $item->user_id === $me->id,
                'message_url' => route('messages.index', ['with' => $item->seller?->public_id]),
                'sold_url' => route('marketplace.sold', $item),
            ]),
            'categories' => MarketplaceItem::CATEGORIES,
            'filters' => ['q' => $q, 'category' => $category],
            'total' => $total,
        ]);
    }

    public function show(Request $request, MarketplaceItem $item)
    {
        $item->load('seller', 'media');
        $viewer = $request->user();

        $related = MarketplaceItem::where('status', 'available')
            ->where('id', '!=', $item->id)
            ->where('category', $item->category)
            ->with('seller', 'media')
            ->latest()
            ->limit(3)
            ->get();

        return view('marketplace.show', [
            'item' => $item,
            'related' => $related,
            'categories' => MarketplaceItem::CATEGORIES,
            'canEditMedia' => $viewer !== null && ($item->user_id === $viewer->id || $viewer->isAdmin()),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'description' => ['required', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'category' => ['required', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:190'],
            'photo' => ['nullable', 'image', 'max:10240'],
            'photos' => ['nullable', 'array', 'max:'.Gallery::LIMIT],
            'photos.*' => ['image', 'max:10240'],
        ]);

        unset($data['photo'], $data['photos']);

        $item = MarketplaceItem::create([
            ...$data,
            // A blank price arrives as null and the column does not take null.
            'price' => $data['price'] ?? 0,
            'user_id' => $request->user()->id,
        ]);

        foreach (Gallery::filesFrom($request) as $file) {
            Gallery::add($item, $file, $request->user()->id);
        }

        return back()->with('success', 'Listing published.');
    }

    public function markSold(Request $request, MarketplaceItem $item)
    {
        if ($item->user_id !== $request->user()->id) {
            abort(403);
        }

        $item->update(['status' => 'sold']);

        return $this->respond($request, ['status' => 'sold'], 'Listing marked as sold.');
    }
}

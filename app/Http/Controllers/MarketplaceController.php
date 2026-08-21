<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceItem;
use Illuminate\Http\Request;

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
            ->with('seller')
            ->latest()
            ->paginate(16)
            ->withQueryString();

        return view('marketplace.index', [
            'items' => $items,
            'categories' => MarketplaceItem::CATEGORIES,
            'category' => $category,
            'q' => $q,
            'total' => $total,
        ]);
    }

    public function show(Request $request, MarketplaceItem $item)
    {
        $item->load('seller');

        $related = MarketplaceItem::where('status', 'available')
            ->where('id', '!=', $item->id)
            ->where('category', $item->category)
            ->with('seller')
            ->latest()
            ->limit(3)
            ->get();

        return view('marketplace.show', [
            'item' => $item,
            'related' => $related,
            'categories' => MarketplaceItem::CATEGORIES,
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
        ]);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('marketplace', 'public');
        }

        MarketplaceItem::create([...$data, 'user_id' => $request->user()->id]);

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

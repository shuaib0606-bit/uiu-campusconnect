<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceItem;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function index()
    {
        $items = MarketplaceItem::where('status', 'available')->with('seller')->latest()->paginate(16);

        return view('marketplace.index', compact('items'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'description' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'category' => ['required', 'string', 'max:80'],
            'condition' => ['required', 'string', 'max:30'],
            'photo' => ['nullable', 'image', 'max:10240'],
        ]);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('marketplace', 'public');
        }

        MarketplaceItem::create([...$data, 'user_id' => $request->user()->id]);

        return back()->with('success', 'Listing published.');
    }
}

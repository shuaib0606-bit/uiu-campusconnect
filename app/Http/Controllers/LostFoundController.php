<?php

namespace App\Http\Controllers;

use App\Models\LostFoundClaim;
use App\Models\LostFoundItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LostFoundController extends Controller
{
    public function index()
    {
        $items = LostFoundItem::where('status', 'open')->with('user')->latest()->paginate(16);

        return view('lost-found.index', compact('items'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['lost', 'found'])],
            'title' => ['required', 'string', 'max:190'],
            'description' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:190'],
            'photo' => ['nullable', 'image', 'max:10240'],
        ]);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('lost-found', 'public');
        }

        LostFoundItem::create([...$data, 'user_id' => $request->user()->id]);

        return back()->with('success', 'Listing published.');
    }

    public function claim(Request $request, LostFoundItem $item)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:500']]);

        LostFoundClaim::create([...$data, 'item_id' => $item->id, 'user_id' => $request->user()->id]);

        return $this->respond($request, ['active' => true], 'Claim submitted to the poster.');
    }
}

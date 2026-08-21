<?php

namespace App\Http\Controllers;

use App\Models\LostFoundClaim;
use App\Models\LostFoundItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LostFoundController extends Controller
{
    public function index(Request $request)
    {
        $type = trim((string) $request->get('type', ''));
        $q = trim((string) $request->get('q', ''));

        $open = LostFoundItem::where('status', 'open');

        $stats = [
            'lost' => (clone $open)->where('type', 'lost')->count(),
            'found' => (clone $open)->where('type', 'found')->count(),
            'resolved' => LostFoundItem::where('status', 'resolved')->count(),
        ];

        $items = $open
            ->when(in_array($type, ['lost', 'found'], true), fn ($qr) => $qr->where('type', $type))
            ->when($q !== '', function ($qr) use ($q) {
                $qr->where(function ($qr2) use ($q) {
                    $qr2->where('title', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%")->orWhere('location', 'like', "%{$q}%");
                });
            })
            ->with('user')
            ->latest()
            ->paginate(16)
            ->withQueryString();

        return view('lost-found.index', compact('items', 'type', 'q', 'stats'));
    }

    public function show(Request $request, LostFoundItem $item)
    {
        $item->load('user');
        $viewer = $request->user();
        $isOwner = $viewer && $item->user_id === $viewer->id;

        return view('lost-found.show', [
            'item' => $item,
            'isOwner' => $isOwner,
            'claims' => $isOwner ? $item->claims()->with('user')->latest()->get() : collect(),
            'hasClaimed' => $viewer && ! $isOwner
                ? $item->claims()->where('user_id', $viewer->id)->exists()
                : false,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['lost', 'found'])],
            'title' => ['required', 'string', 'max:190'],
            'description' => ['required', 'string'],
            'location' => ['required', 'string', 'max:190'],
            'event_date' => ['nullable', 'date'],
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

    public function resolve(Request $request, LostFoundItem $item)
    {
        if ($item->user_id !== $request->user()->id) {
            abort(403);
        }

        $item->update(['status' => 'resolved']);

        return $this->respond($request, ['status' => 'resolved'], 'Marked as resolved.');
    }
}

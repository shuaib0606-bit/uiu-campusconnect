<?php

namespace App\Http\Controllers;

use App\Models\LostFoundClaim;
use App\Models\LostFoundItem;
use App\Support\Gallery;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LostFoundController extends Controller
{
    /** Lists a viewer may pick between. "mine" and "everything" need an account. */
    private const VIEWS = ['open', 'reunited', 'mine', 'everything'];

    public function index(Request $request)
    {
        $viewer = $request->user();
        $type = trim((string) $request->get('type', ''));
        $q = trim((string) $request->get('q', ''));
        $view = $this->view($request);

        $items = LostFoundItem::query()
            ->when($view === 'open', fn ($qr) => $qr->where('status', 'open'))
            ->when($view === 'reunited', fn ($qr) => $qr->where('status', 'resolved'))
            ->when($view === 'mine', fn ($qr) => $qr->where('user_id', $viewer->id))
            ->when(in_array($type, ['lost', 'found'], true), fn ($qr) => $qr->where('type', $type))
            ->when($q !== '', function ($qr) use ($q) {
                $qr->where(function ($qr2) use ($q) {
                    $qr2->where('title', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%")->orWhere('location', 'like', "%{$q}%");
                });
            })
            ->with('user', 'media', 'resolver')
            // The reunited list reads as a record, so it goes by when it was settled.
            ->when($view === 'reunited', fn ($qr) => $qr->orderByDesc('resolved_at')->orderByDesc('updated_at'))
            ->when($view !== 'reunited', fn ($qr) => $qr->latest())
            ->paginate(16)
            ->withQueryString();

        // One lookup for the page, so a card does not have to ask on its own.
        $claimed = $viewer !== null
            ? LostFoundClaim::where('user_id', $viewer->id)
                ->whereIn('item_id', $items->pluck('id'))
                ->pluck('item_id')
                ->flip()
            : collect();

        return Inertia::render('LostFound/Index', [
            'items' => $items->through(fn (LostFoundItem $item) => [
                'id' => $item->id,
                'type' => $item->type,
                'title' => $item->title,
                'description' => Str::limit($item->description, 130),
                'cover_url' => $item->coverUrl(),
                'gallery_count' => $item->galleryCount(),
                'location' => $item->location,
                'event_date' => $item->event_date?->format('M j, Y'),
                'posted_ago' => $item->created_at->diffForHumans(),
                'poster' => $item->user->full_name,
                'is_open' => $item->isOpen(),
                'resolved_on' => $item->resolved_at?->format('M j, Y'),
                'can_manage' => $item->canBeManagedBy($viewer),
                'has_claimed' => isset($claimed[$item->id]),
                'url' => route('lost-found.show', $item),
                'actions' => [
                    'claim' => route('lost-found.claim', $item),
                    'resolve' => route('lost-found.resolve', $item),
                    'reopen' => route('lost-found.reopen', $item),
                ],
            ]),
            'filters' => ['q' => $q, 'type' => $type, 'view' => $view],
            'counts' => [
                'open' => LostFoundItem::where('status', 'open')->count(),
                'reunited' => LostFoundItem::where('status', 'resolved')->count(),
                'mine' => $viewer ? LostFoundItem::where('user_id', $viewer->id)->count() : 0,
            ],
            'can' => [
                'post' => $viewer !== null,
                'see_everything' => $viewer?->isAdmin() ?? false,
                'see_mine' => $viewer !== null,
            ],
        ]);
    }

    public function show(Request $request, LostFoundItem $item)
    {
        $item->load('user', 'media', 'resolver');
        $viewer = $request->user();
        $isOwner = $viewer !== null && $item->user_id === $viewer->id;
        $canManage = $item->canBeManagedBy($viewer);

        return view('lost-found.show', [
            'item' => $item,
            'isOwner' => $isOwner,
            'canManage' => $canManage,
            'canEditMedia' => $canManage,
            'claims' => $canManage ? $item->claims()->with('user')->latest()->get() : collect(),
            'hasClaimed' => $viewer !== null && ! $isOwner
                ? $item->claims()->where('user_id', $viewer->id)->exists()
                : false,
        ]);
    }

    /** Falls back to the open list when the viewer may not use the one asked for. */
    private function view(Request $request): string
    {
        $view = (string) $request->get('view', 'open');

        if (! in_array($view, self::VIEWS, true)) {
            return 'open';
        }

        if ($view === 'mine' && $request->user() === null) {
            return 'open';
        }

        if ($view === 'everything' && ! $request->user()?->isAdmin()) {
            return 'open';
        }

        return $view;
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
            'photos' => ['nullable', 'array', 'max:'.Gallery::LIMIT],
            'photos.*' => ['image', 'max:10240'],
        ]);

        unset($data['photo'], $data['photos']);

        $item = LostFoundItem::create([...$data, 'user_id' => $request->user()->id]);

        foreach (Gallery::filesFrom($request) as $file) {
            Gallery::add($item, $file, $request->user()->id);
        }

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
        if (! $item->canBeManagedBy($request->user())) {
            abort(403);
        }

        $item->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ]);

        return $this->respond($request, ['status' => 'resolved'], 'Marked as reunited.');
    }

    /**
     * The poster decides on a claim. Accepting does not close the item: the
     * two still have to meet, and the poster marks it reunited afterwards.
     */
    public function reviewClaim(Request $request, LostFoundClaim $claim)
    {
        $claim->load('item');

        if (! $claim->item->canBeManagedBy($request->user())) {
            abort(403);
        }

        $data = $request->validate(['claim_action' => ['required', 'in:accept,reject']]);
        $accepted = $data['claim_action'] === 'accept';

        $claim->update(['status' => $accepted ? 'accepted' : 'rejected']);

        Notifier::send(
            $claim->user_id,
            $request->user(),
            Notifier::SYSTEM,
            'lost_found',
            $claim->item_id,
            $accepted
                ? 'accepted your claim on "'.$claim->item->title.'". Message them to arrange the handover.'
                : 'did not accept your claim on "'.$claim->item->title.'".',
        );

        return back()->with('success', $accepted ? 'Claim accepted.' : 'Claim rejected.');
    }

    /** Undoes a resolution, for the times it was marked by mistake. */
    public function reopen(Request $request, LostFoundItem $item)
    {
        if (! $item->canBeManagedBy($request->user())) {
            abort(403);
        }

        $item->update([
            'status' => 'open',
            'resolved_at' => null,
            'resolved_by' => null,
        ]);

        return $this->respond($request, ['status' => 'open'], 'Listing reopened.');
    }
}

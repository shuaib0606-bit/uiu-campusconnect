<?php

namespace App\Services\Ai\Tools;

use App\Models\LostFoundItem;
use App\Models\MarketplaceItem;
use App\Services\Ai\Links;
use App\Services\Ai\Scopes;

/**
 * The two public boards: things for sale, and things lost or found.
 * Only live listings - sold items and settled cases are history.
 */
class ListingsTool extends Tool
{
    public function name(): string
    {
        return 'listings';
    }

    public function description(): string
    {
        return 'The marketplace (items students are selling) or lost and found (open reports).';
    }

    public function parameters(): array
    {
        return self::object([
            'board' => self::choice(['marketplace', 'lost_found'], 'Which board.'),
            'query' => self::text('Optional words, e.g. "calculator", "ID card", "umbrella".'),
        ], ['board']);
    }

    public function run(array $arguments): array
    {
        $query = $this->arg($arguments, 'query');
        $like = '%'.addcslashes($query, '%_\\').'%';

        return $this->arg($arguments, 'board') === 'lost_found'
            ? $this->lostFound($query, $like)
            : $this->marketplace($query, $like);
    }

    private function marketplace(string $query, string $like): array
    {
        $items = MarketplaceItem::where('status', 'available')
            // A block hides a seller's listings from the other side, the
            // same as it hides their posts.
            ->whereHas('seller', fn ($s) => Scopes::withoutBlocks($s->where('users.status', 'active'), $this->viewer))
            ->when($query !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('category', 'like', $like)))
            ->with('seller:id,full_name')
            ->latest()
            ->limit(self::LIMIT)
            ->get();

        return ['marketplace' => $items->map(fn ($i) => array_filter([
            'title' => $i->title,
            'price' => $i->price,
            'category' => MarketplaceItem::CATEGORIES[$i->category] ?? $i->category,
            'condition' => $i->condition,
            'pickup' => $i->location,
            'seller' => Links::person($i->seller),
            'link' => Links::marketplace($i),
            'about' => $this->excerpt($i->description, 160),
            'listed' => $i->created_at?->diffForHumans(),
        ], fn ($v) => $v !== null && $v !== ''))->values()->all()];
    }

    private function lostFound(string $query, string $like): array
    {
        $items = LostFoundItem::where('status', 'open')
            ->when($query !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('location', 'like', $like)))
            ->with('user:id,full_name')
            // Blocks hide reports the same way they hide listings.
            ->whereHas('user', fn ($u) => Scopes::withoutBlocks($u->where('users.status', 'active'), $this->viewer))
            ->latest()
            ->limit(self::LIMIT)
            ->get();

        return ['lost_and_found' => $items->map(fn ($i) => array_filter([
            'type' => $i->type,
            'title' => $i->title,
            'where' => $i->location,
            'date' => $i->event_date?->toFormattedDateString(),
            'reported_by' => Links::person($i->user),
            'link' => Links::lostFound($i),
            'about' => $this->excerpt($i->description, 160),
        ]))->values()->all()];
    }
}

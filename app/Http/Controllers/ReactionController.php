<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostReaction;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReactionController extends Controller
{
    /** The reaction set, in display order. Keys are stored in post_reactions.reaction. */
    public const REACTIONS = [
        'like' => ['label' => 'Like', 'icon' => 'thumb-up'],
        'love' => ['label' => 'Love', 'icon' => 'heart'],
        'helpful' => ['label' => 'Helpful', 'icon' => 'lightbulb'],
        'question' => ['label' => 'Question', 'icon' => 'question-mark-circle'],
    ];

    public function store(Request $request, Post $post)
    {
        $data = $request->validate([
            'reaction' => ['required', Rule::in(array_keys(self::REACTIONS))],
        ]);

        $userId = $request->user()->id;
        $query = PostReaction::where('post_id', $post->id)->where('user_id', $userId);
        $existing = $query->first();
        $active = true;
        $reaction = $data['reaction'];

        if ($existing && $existing->reaction === $reaction) {
            // Picking the reaction you already have clears it.
            $query->delete();
            $active = false;
            $reaction = null;
        } elseif ($existing) {
            $query->update(['reaction' => $reaction]);
        } else {
            PostReaction::create(['post_id' => $post->id, 'user_id' => $userId, 'reaction' => $reaction]);
        }

        // Notify the author only while a reaction stands.
        if ($active) {
            Notifier::send(
                $post->user_id,
                $request->user(),
                Notifier::POST_REACTION,
                'post',
                $post->id,
                'reacted to your post.',
                PostReaction::where('post_id', $post->id)->where('user_id', '!=', $post->user_id)->count(),
            );
        }

        return $this->respond($request, [
            'active' => $active,
            'reaction' => $reaction,
            'count' => $post->reactions()->count(),
            'breakdown' => $post->reactions()
                ->selectRaw('reaction, count(*) as total')
                ->groupBy('reaction')
                ->pluck('total', 'reaction'),
        ]);
    }
}

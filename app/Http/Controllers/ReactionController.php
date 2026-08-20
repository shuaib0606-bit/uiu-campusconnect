<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostReaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReactionController extends Controller
{
    public function store(Request $request, Post $post)
    {
        $data = $request->validate([
            'reaction' => ['required', Rule::in(['like', 'love', 'celebrate', 'insightful', 'curious'])],
        ]);

        $userId = $request->user()->id;
        $query = PostReaction::where('post_id', $post->id)->where('user_id', $userId);
        $existing = $query->first();
        $active = true;

        if ($existing && $existing->reaction === $data['reaction']) {
            $query->delete();
            $active = false;
        } elseif ($existing) {
            $query->update(['reaction' => $data['reaction']]);
        } else {
            PostReaction::create(['post_id' => $post->id, 'user_id' => $userId, 'reaction' => $data['reaction']]);
        }

        return $this->respond($request, [
            'active' => $active,
            'count' => $post->reactions()->count(),
        ]);
    }
}

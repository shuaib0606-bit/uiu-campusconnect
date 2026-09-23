<?php

namespace App\Services\Ai\Tools;

use App\Models\Post;
use App\Services\Ai\Links;

/**
 * Feed search through Post::visible and Post::forViewer - the scopes the
 * feed itself runs on every request: hides, mutes, blocks both ways, and
 * department- and connections-only audiences.
 */
class PostsTool extends Tool
{
    public function name(): string
    {
        return 'search_posts';
    }

    public function description(): string
    {
        return 'Search campus feed posts the asker may see. Optionally only posts by one person.';
    }

    public function parameters(): array
    {
        return self::object([
            'query' => self::text('Words to look for. May be empty when searching by author.'),
            'author' => self::text('Optional author name.'),
        ]);
    }

    public function run(array $arguments): array
    {
        $query = $this->arg($arguments, 'query');
        $author = $this->arg($arguments, 'author');

        if (mb_strlen($query) < 2 && mb_strlen($author) < 2) {
            return ['error' => 'Give at least two characters to search for, or an author.'];
        }

        $posts = Post::query()
            ->visible()
            ->forViewer($this->viewer)
            ->when(mb_strlen($query) >= 2, fn ($q) => $q->where('posts.body', 'like', '%'.addcslashes($query, '%_\\').'%'))
            ->when(mb_strlen($author) >= 2, fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('full_name', 'like', '%'.addcslashes($author, '%_\\').'%')))
            ->with('user:id,full_name')
            ->withCount(['comments', 'reactions'])
            ->latest()
            ->limit(self::LIMIT)
            ->get();

        return ['posts' => $posts->map(fn (Post $p) => [
            'by' => Links::person($p->user),
            'link' => Links::post($p),
            'text' => $this->excerpt($p->body),
            'posted' => $p->created_at?->diffForHumans(),
            'reactions' => $p->reactions_count,
            'comments' => $p->comments_count,
        ])->values()->all()];
    }
}

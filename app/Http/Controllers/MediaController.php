<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Support\Gallery;
use Illuminate\Http\Request;

/** Pictures on events, listings, clubs and notices, edited by whoever posted them. */
class MediaController extends Controller
{
    public function store(Request $request, string $type, int $id)
    {
        $item = $this->find($type, $id);

        if (! Gallery::canEdit($request->user(), $item)) {
            abort(403);
        }

        $request->validate([
            'images' => ['required', 'array', 'max:'.Gallery::LIMIT],
            'images.*' => ['image', 'max:10240'],
        ]);

        $room = Gallery::LIMIT - $item->media()->count();

        if ($room < 1) {
            return back()->with('error', 'This post already has '.Gallery::LIMIT.' pictures.');
        }

        $files = array_slice($request->file('images'), 0, $room);

        foreach ($files as $file) {
            Gallery::add($item, $file, $request->user()->id);
        }

        $added = count($files);
        $message = $added === 1 ? 'Picture added.' : $added.' pictures added.';

        return back()->with('success', $message);
    }

    public function destroy(Request $request, Media $media)
    {
        if (! Gallery::canEdit($request->user(), $media->mediable)) {
            abort(403);
        }

        Gallery::remove($media);

        return back()->with('success', 'Picture removed.');
    }

    public function cover(Request $request, Media $media)
    {
        if (! Gallery::canEdit($request->user(), $media->mediable)) {
            abort(403);
        }

        Gallery::makeCover($media);

        return back()->with('success', 'Cover updated.');
    }

    /** Resolves the URL segment and id to the model the pictures belong to. */
    private function find(string $type, int $id)
    {
        $class = Gallery::modelFor($type);

        abort_if($class === null, 404);

        return $class::findOrFail($id);
    }
}

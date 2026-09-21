<?php

namespace App\Support;

use App\Models\Club;
use App\Models\Event;
use App\Models\LostFoundItem;
use App\Models\MarketplaceItem;
use App\Models\Media;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Shared rules for the picture galleries on listings, events and notices. */
class Gallery
{
    /** Most pictures one item may carry. */
    public const LIMIT = 10;

    /** URL segment to model, used by the media routes. */
    public const TYPES = [
        'event' => Event::class,
        'marketplace' => MarketplaceItem::class,
        'lost-found' => LostFoundItem::class,
        'club' => Club::class,
        'notice' => Notice::class,
    ];

    /** Storage folder per model, so uploads stay sorted on disk. */
    public const FOLDERS = [
        Event::class => 'events',
        MarketplaceItem::class => 'marketplace',
        LostFoundItem::class => 'lost-found',
        Club::class => 'clubs',
        Notice::class => 'notices',
    ];

    public static function modelFor(string $type): ?string
    {
        return self::TYPES[$type] ?? null;
    }

    public static function canEdit(?User $user, Model $item): bool
    {
        if ($user === null) {
            return false;
        }

        // The model decides, because who may change a club's banner is not
        // the same question as who may change a marketplace photo.
        return $user->isAdmin() || $item->galleryEditableBy($user);
    }

    /**
     * The image files on a create form, whether it sends one or several.
     *
     * @return array<int, UploadedFile>
     */
    public static function filesFrom(Request $request, string $single = 'photo', string $many = 'photos'): array
    {
        $files = [];

        if ($request->hasFile($single)) {
            $files[] = $request->file($single);
        }

        foreach ((array) $request->file($many, []) as $file) {
            $files[] = $file;
        }

        return array_slice($files, 0, self::LIMIT);
    }

    /** Stores the file and appends it to the gallery. */
    public static function add(Model $item, UploadedFile $file, ?int $userId = null): Media
    {
        $folder = self::FOLDERS[$item::class] ?? 'uploads';
        $path = $file->store($folder, 'public');

        // Shrink before recording the size, so the row matches the stored file.
        ImageResizer::shrink($path);

        return $item->media()->create([
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => Storage::disk('public')->size($path),
            'position' => self::nextPosition($item),
            'uploaded_by' => $userId,
        ]);
    }

    /** Deletes the file from disk as well as the row. */
    public static function remove(Media $media): void
    {
        Storage::disk('public')->delete($media->path);
        $media->delete();
    }

    /** Moves one picture to the front, pushing the rest down a place. */
    public static function makeCover(Media $media): void
    {
        $media->mediable->media()
            ->where('id', '!=', $media->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->each(fn (Media $other, int $i) => $other->update(['position' => $i + 1]));

        $media->update(['position' => 0]);
    }

    private static function nextPosition(Model $item): int
    {
        $max = $item->media()->max('position');

        return $max === null ? 0 : (int) $max + 1;
    }
}

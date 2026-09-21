<?php

namespace App\Models\Concerns;

use App\Models\Media;
use App\Models\User;
use App\Support\CoverImage;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Gives a model a cover picture and a gallery of extra pictures. Models that
 * already had a single image column keep it as the cover, so existing rows and
 * upload forms carry on working.
 */
trait HasGallery
{
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('position')->orderBy('id');
    }

    /** Name of the single image column this model had before galleries, if any. */
    public function coverColumn(): ?string
    {
        return null;
    }

    /** Who may edit the pictures, on top of an administrator. */
    public function galleryOwnerId(): ?int
    {
        return null;
    }

    /**
     * Whether this person may change the pictures.
     *
     * The default is "whoever posted it", which is right for a listing, a
     * lost item and an event. A club and a notice are not owned by one person
     * in the same way - a club outlives the panel that runs it, and a notice
     * belongs to the registrar's office rather than to the clerk who typed
     * it - so those two models override this.
     */
    public function galleryEditableBy(User $user): bool
    {
        return $this->galleryOwnerId() === $user->id;
    }

    /** Text the generated cover draws its initials from. */
    public function coverLabel(): string
    {
        return (string) ($this->title ?? $this->name ?? '');
    }

    /** Stored path of the cover, or null when there is no uploaded picture. */
    public function coverPath(): ?string
    {
        $first = $this->relationLoaded('media')
            ? $this->media->first()
            : $this->media()->first();

        if ($first !== null) {
            return $first->path;
        }

        // Nothing in the gallery, so fall back to the column older rows used.
        $column = $this->coverColumn();

        return $column !== null && ! empty($this->{$column}) ? $this->{$column} : null;
    }

    public function hasUploadedCover(): bool
    {
        return $this->coverPath() !== null;
    }

    /** Always returns something drawable: the upload, or a generated cover. */
    public function coverUrl(): string
    {
        $path = $this->coverPath();

        return $path !== null
            ? Storage::url($path)
            : CoverImage::dataUri(static::class.':'.$this->getKey(), $this->coverLabel());
    }

    /**
     * Every picture in display order, cover first.
     *
     * @return Collection<int, array{id: ?int, url: string, caption: ?string}>
     */
    public function gallery(): Collection
    {
        $items = ($this->relationLoaded('media') ? $this->media : $this->media()->get())
            ->map(fn (Media $m) => ['id' => $m->id, 'url' => $m->url, 'caption' => $m->caption])
            ->values();

        if ($items->isNotEmpty()) {
            return $items;
        }

        $column = $this->coverColumn();

        if ($column !== null && ! empty($this->{$column})) {
            // A row from before galleries. It has no media id to delete by.
            return collect([['id' => null, 'url' => Storage::url($this->{$column}), 'caption' => null]]);
        }

        return $items;
    }

    public function galleryCount(): int
    {
        return $this->gallery()->count();
    }
}

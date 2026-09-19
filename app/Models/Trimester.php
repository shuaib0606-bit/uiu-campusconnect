<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trimester extends Model
{
    /**
     * A class stays open for this long after teaching ends, so people can
     * still fetch what they need, and is then archived.
     */
    public const GRACE_DAYS = 20;

    protected $fillable = ['name', 'code', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class);
    }

    /** The trimester teaching is happening in, if there is one. */
    public static function current(): ?self
    {
        return static::whereDate('starts_on', '<=', now())
            ->whereDate('ends_on', '>=', now())
            ->orderByDesc('ends_on')
            ->first();
    }

    /** The one whose groups people should be looking at right now. */
    public static function latest(): ?self
    {
        return static::current() ?? static::orderByDesc('ends_on')->first();
    }

    public function hasEnded(): bool
    {
        return $this->ends_on->isPast();
    }

    public function archivesOn(): \Carbon\CarbonInterface
    {
        return $this->ends_on->copy()->addDays(self::GRACE_DAYS);
    }

    /** Whole days left before the class groups go read-only, never negative. */
    public function daysUntilArchive(): int
    {
        return max(0, now()->startOfDay()->diffInDays($this->archivesOn()->startOfDay(), false));
    }
}

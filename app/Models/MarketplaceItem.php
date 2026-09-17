<?php

namespace App\Models;

use App\Models\Concerns\HasGallery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceItem extends Model
{
    use HasGallery;

    protected $fillable = ['user_id', 'title', 'description', 'price', 'category', 'location', 'condition', 'photo_path', 'status'];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public const CATEGORIES = [
        'used_books' => 'Used books', 'calculator' => 'Calculator', 'laptop' => 'Laptop',
        'bike_accessories' => 'Cycle/bike accessories', 'roommate' => 'Roommate',
        'tuition' => 'Tuition', 'other' => 'Other',
    ];

    public function coverColumn(): ?string
    {
        return 'photo_path';
    }

    public function galleryOwnerId(): ?int
    {
        return $this->user_id;
    }
}

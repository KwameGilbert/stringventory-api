<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AwardImage Model
 * 
 * Represents gallery images for an awards show
 *
 * @property int $id
 * @property int $award_id
 * @property string $image_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AwardImage extends Model
{
    /**
     * The table associated with the model.
     * @var string
     */
    protected $table = 'awards_images';

    /**
     * The primary key for the model.
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     * @var bool
     */
    public $incrementing = true;

    /**
     * Indicates if the model should be timestamped.
     * @var bool
     */
    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'award_id',
        'image_path',
    ];

    /**
     * The attributes that should be cast.
     * @var array
     */
    protected $casts = [
        'award_id' => 'integer',
    ];

    /* -----------------------------------------------------------------
     |  Relationships
     | -----------------------------------------------------------------
     */

    /**
     * Get the award that owns this image.
     */
    public function award()
    {
        return $this->belongsTo(Award::class, 'award_id');
    }
}

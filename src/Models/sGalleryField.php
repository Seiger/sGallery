<?php namespace Seiger\sGallery\Models;

use Illuminate\Database\Eloquent;

/**
 * Class sGalleryField
 *
 * This class represents a gallery field in the application.
 * It extends the Eloquent\Model class, which is the base class for all Eloquent models.
 */
class sGalleryField extends Eloquent\Model
{
    /**
     * The attributes that may be mass assigned when translated gallery fields are saved.
     *
     * @var array<int, string>
     */
    protected $fillable = ['key', 'lang', 'alt', 'title', 'description', 'link_text', 'link'];
}

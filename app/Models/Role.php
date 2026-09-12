<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'display_name',
        'description',
    ];

    /**
     * @return BelongsToMany<Permission>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission')->withTimestamps();
    }

    /**
     * @return BelongsToMany<User>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}

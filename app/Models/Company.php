<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'email', 'phone', 'address', 'is_active', 'meta_app_id', 'meta_app_secret'];

    protected $hidden = ['meta_app_secret'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'meta_app_secret' => 'encrypted',
        ];
    }

    /** True when the company has entered its own Meta app ID and secret. */
    public function hasMetaApp(): bool
    {
        return filled($this->meta_app_id) && filled($this->meta_app_secret);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Limit companies to the viewer's own company (no limit for Super Admins).
     */
    public function scopeVisibleTo($query, User $viewer)
    {
        if ($viewer->canAccessAllCompanies()) {
            return $query;
        }

        return $query->whereKey($viewer->company_id ?? 0);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

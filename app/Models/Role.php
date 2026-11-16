<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A role owned by a company. Roles with no company are global and only
 * managed/assigned by Super Admins.
 */
class Role extends SpatieRole
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Limit roles to the viewer's company (no limit for Super Admins).
     */
    public function scopeVisibleTo($query, User $viewer)
    {
        if ($viewer->canAccessAllCompanies()) {
            return $query;
        }

        return $query->where('company_id', $viewer->company_id ?? 0);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeWebauthnCredential extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['credential_id', 'credential_hash', 'public_key'];

    protected function casts(): array
    {
        return [
            'sign_count' => 'integer', 'backup_eligible' => 'boolean', 'backed_up' => 'boolean',
            'approved_at' => 'datetime', 'revoked_at' => 'datetime', 'last_used_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

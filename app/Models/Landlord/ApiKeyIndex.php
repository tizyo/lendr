<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;

/** Which workspace an open-banking API key belongs to (by its SHA-256 hash). */
class ApiKeyIndex extends Model
{
    protected $table = 'api_key_index';

    protected $fillable = ['key_hash', 'tenant_id', 'api_client_id'];

    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }
}

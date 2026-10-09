<?php

namespace App\Http\Controllers\Api\V1\Institutions;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InstitutionResource;
use App\Models\Institution;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InstitutionController extends Controller
{
    /**
     * Display a listing of active financial institutions and e-wallets.
     */
    public function index(): AnonymousResourceCollection
    {
        $institutions = Institution::where('is_active', true)
            ->orderBy('name')
            ->get();

        return InstitutionResource::collection($institutions);
    }
}

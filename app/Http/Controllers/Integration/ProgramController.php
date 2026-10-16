<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Http\Resources\Integration\FeeRateResource;
use App\Http\Resources\Integration\ProgramResource;
use App\Services\Integration\SyncReader;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProgramController extends Controller
{
    public function __construct(private readonly SyncReader $reader) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ProgramResource::collection($this->reader->programs()->paginate($this->reader->perPage($request)));
    }

    /**
     * Tarif prodi: UKT per golongan (`ukt_group` null = tarif umum) dan BPP.
     */
    public function feeRates(Request $request, int $program): AnonymousResourceCollection
    {
        $program = $this->reader->findProgram($program);

        return FeeRateResource::collection($this->reader->feeRates($program)->paginate($this->reader->perPage($request)));
    }
}

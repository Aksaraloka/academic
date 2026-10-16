<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Http\Resources\Integration\ClassResource;
use App\Http\Resources\Integration\TermResource;
use App\Services\Integration\SyncReader;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TermController extends Controller
{
    public function __construct(private readonly SyncReader $reader) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return TermResource::collection($this->reader->terms()->paginate($this->reader->perPage($request)));
    }

    public function classes(Request $request, int $term): AnonymousResourceCollection
    {
        $term = $this->reader->findTerm($term);

        return ClassResource::collection($this->reader->classes($term)->paginate($this->reader->perPage($request)));
    }
}

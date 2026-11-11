<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\QueryBuilder;

class FileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        /**
         * @todo: fix the filtering and sorting
         * by uploadedBy relation
         * name
         * created_by
         */
        return Inertia::render('Files/Index', [
            'files' => QueryBuilder::for(File::class)
                ->with('uploadedBy')
                ->allowedFields('users.name')
                ->allowedIncludes([
                    'uploadedBy',
                ])
                ->allowedFilters([
                    'name',
                    'type',
                    'users.name',
                ])
                ->allowedSorts([
                    'name',
                    'created_at',
                    'users.name',
                ])
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Files/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        File::store($request->input('key'), $request->user()->id);

        return to_route('files.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(File $file)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(File $file)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, File $file)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(File $file)
    {
        File::destroy($file->id);

        return to_route('files.index');
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ProjectProgramEnum;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $projects = QueryBuilder::for(Project::class)
            ->select([
                'id',
                'name',
                'description',
                'program',
                'is_funded',
                'project_involvement',
                'created_at',
            ])
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::exact('program'),
                AllowedFilter::exact('is_funded'),
            ])
            ->allowedSorts(['name', 'program', 'created_at'])
            ->defaultSort('-created_at')
            ->paginate(10)
            ->appends(request()->query());

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'enums' => [
                'programs' => ProjectProgramEnum::labels(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Projects/Create', [
            'project' => new Project,
            'enums' => [
                'programs' => ProjectProgramEnum::labels(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectRequest $request)
    {
        Project::create([
            'name' => $request->name,
            'program' => $request->program,
            'is_funded' => $request->is_funded,
            'description' => $request->description,
            'project_involvement' => $request->project_involvement,
        ]);

        return to_route('projects.index')
            ->with('success', 'Project created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project)
    {
        return Inertia::render('Projects/Show', [
            'project' => $project,
            'enums' => [
                'programs' => ProjectProgramEnum::labels(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project)
    {
        return Inertia::render('Projects/Edit', [
            'project' => $project,
            'enums' => [
                'programs' => ProjectProgramEnum::labels(),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjectRequest $request, Project $project)
    {

        $program = $request->program;
        if ($request->program === 'other' && $request->other_program) {
            $program = $request->other_program;
        }

        $project->update([
            'name' => $request->name,
            'program' => $program,
            'is_funded' => $request->is_funded ?? $project->is_funded,
            'description' => $request->description,
            'project_involvement' => $request->project_involvement,
        ]);

        return to_route('projects.index')
            ->with('success', 'Project updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project)
    {
        Project::destroy($project->getKey());

        return to_route('projects.index')
            ->with('success', 'Project deleted successfully.');
    }
}

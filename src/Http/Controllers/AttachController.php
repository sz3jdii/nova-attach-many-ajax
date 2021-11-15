<?php

namespace NovaAttachMany\Http\Controllers;

use Laravel\Nova\Resource;
use Illuminate\Routing\Controller;
use Laravel\Nova\Http\Requests\NovaRequest;

class AttachController extends Controller
{
    public function create(NovaRequest $request, $parent, $relationship)
    {
        return [
            'available' => $this->getAvailableResources($request, $relationship),
        ];
    }

    public function edit(NovaRequest $request, $parent, $parentId, $relationship)
    {
        return [
            'selected' => $this->getSelectedResources($request, $relationship),
            'available' => [],
        ];
    }

    public function getAvailableResources(NovaRequest $request, $relationship)
    {
        $resourceClass = $request->newResource();

        $search = strtoupper(trim($request->query('search')));

        $field = $resourceClass
            ->availableFields($request)
            ->where('component', 'nova-attach-many')
            ->where('attribute', $relationship)
            ->first();

        $query = $field->resourceClass::newModel();

        if(!empty($search)){
            $query = $field->resourceClass::relatableQuery($request, $query)
                ->whereRaw("UPPER(name) LIKE '%".$search."%'")
                ->orWhereRaw("UPPER(first_name) LIKE '%".$search."%'")
                ->orWhereRaw("UPPER(last_name) LIKE '%".$search."%'")
                ->orWhereRaw("UPPER(email) LIKE '%".$search."%'")
                ->get()
                ->mapInto($field->resourceClass)
                ->map(function($resource) {
                    return [
                        'display' => $resource->title(),
                        'value' => $resource->getKey(),
                    ];
                })->sortBy('display')->slice(0, 10)->values();
        }else{
            return;
        }
        return $query;
    }

    public function getSelectedResources(NovaRequest $request, $relationship)
    {
        $resourceClass = $request->newResource();


        $field = $resourceClass
            ->availableFields($request)
            ->where('component', 'nova-attach-many')
            ->where('attribute', $relationship)
            ->first();

        $query = $field->resourceClass::newModel();

        return $request->findResourceOrFail()->model()->{$relationship}
            ->mapInto($field->resourceClass)
            ->filter(function ($resource) use ($request, $field) {
                return $request->newResource()->authorizedToAttach($request, $resource->resource);
            })->map(function($resource) {
                return [
                    'display' => $resource->title(),
                    'value' => $resource->getKey(),
                ];
            })->sortBy('display')->slice(0, 10)->values();
    }

}

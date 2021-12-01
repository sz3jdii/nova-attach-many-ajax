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

        $searchInput = mb_convert_case(trim($request->query('search')), MB_CASE_UPPER, 'UTF-8');

        $field = $resourceClass
            ->availableFields($request)
            ->where('component', 'nova-attach-many')
            ->where('attribute', $relationship)
            ->first();

        $query = $field->resourceClass::newModel();

        if(!empty($searchInput)){
            $searchByFields = $field->resourceClass::$search ?? [];

            $query = !empty($field->defaultQuery) ? $field->defaultQuery : $field->resourceClass::relatableQuery($request, $query);
            if(!empty($searchByFields)){
                $query = $query->where(function ($q) use ($searchByFields, $searchInput){
                    foreach($searchByFields as $key => $searchByField){
                        $rawExpression = "UPPER(".$searchByField."::text) LIKE '%".$searchInput."%'";
                        if($key == 0){
                            $q = $q->whereRaw($rawExpression);
                        }else{
                            $q = $q->orWhereRaw($rawExpression);
                        }
                    }
                });

            }

            $query = $query->get()
                ->mapInto($field->resourceClass)
                ->map(function($resource) {
                    return [
                        'display' => $resource->title(),
                        'value' => $resource->getKey(),
                    ];
                })->sortBy('display')->values();
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

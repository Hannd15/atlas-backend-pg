<?php

namespace App\Http\Controllers;

use App\Models\Deliverable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Deliverables",
 *     description="API endpoints for managing deliverables (supports batch creation)"
 * )
 */
class DeliverableController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/deliverables",
     *     summary="Get all deliverables",
     *     tags={"Deliverables"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of deliverables with phase and file names"
     *     )
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $deliverables = Deliverable::with(['phase', 'files'])->orderBy('updated_at', 'desc')->get();

        $deliverables->each(function ($deliverable) {
            $deliverable->phase_names = $deliverable->phase ? $deliverable->phase->name : '';
            $deliverable->file_names = $deliverable->files->pluck('name')->implode(', ');
        });

        return response()->json($deliverables);
    }

    /**
     * @OA\Post(
     *     path="/api/pg/deliverables",
     *     summary="Create deliverable(s) - supports both single object and batch creation",
     *     tags={"Deliverables"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Single deliverable object or array of deliverables",
     *
     *         @OA\JsonContent(
     *             oneOf={
     *
     *                 @OA\Schema(
     *                     type="object",
     *                     required={"phase_id","name","due_date"},
     *
     *                     @OA\Property(property="phase_id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Entrega 1"),
     *                     @OA\Property(property="due_date", type="string", format="date-time", example="2025-03-15 23:59:00"),
     *                     @OA\Property(property="file_ids", type="array", @OA\Items(type="integer"))
     *                 ),
     *
     *                 @OA\Schema(
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="object",
     *                         required={"phase_id","name","due_date"},
     *
     *                         @OA\Property(property="phase_id", type="integer"),
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="due_date", type="string", format="date-time"),
     *                         @OA\Property(property="file_ids", type="array", @OA\Items(type="integer"))
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Deliverable(s) created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->all();

        // Check if it's a batch creation (array) or single object
        $isBatch = isset($data[0]) && is_array($data[0]);

        if ($isBatch) {
            return $this->storeBatch($data);
        } else {
            return $this->storeSingle($data);
        }
    }

    protected function storeSingle(array $data): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($data, [
            'phase_id' => 'required|exists:phases,id',
            'name' => 'required|string|max:255',
            'due_date' => 'required|date',
            'file_ids' => 'nullable|array',
            'file_ids.*' => 'exists:files,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $deliverable = Deliverable::create([
            'phase_id' => $data['phase_id'],
            'name' => $data['name'],
            'due_date' => $data['due_date'],
        ]);

        if (isset($data['file_ids'])) {
            $deliverable->files()->sync($data['file_ids']);
        }

        return response()->json($deliverable, 201);
    }

    protected function storeBatch(array $dataArray): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make(['deliverables' => $dataArray], [
            'deliverables' => 'required|array',
            'deliverables.*.phase_id' => 'required|exists:phases,id',
            'deliverables.*.name' => 'required|string|max:255',
            'deliverables.*.due_date' => 'required|date',
            'deliverables.*.file_ids' => 'nullable|array',
            'deliverables.*.file_ids.*' => 'exists:files,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $deliverables = DB::transaction(function () use ($dataArray) {
            $createdDeliverables = [];

            foreach ($dataArray as $deliverableData) {
                $deliverable = Deliverable::create([
                    'phase_id' => $deliverableData['phase_id'],
                    'name' => $deliverableData['name'],
                    'due_date' => $deliverableData['due_date'],
                ]);

                if (isset($deliverableData['file_ids'])) {
                    $deliverable->files()->sync($deliverableData['file_ids']);
                }

                $createdDeliverables[] = $deliverable;
            }

            return $createdDeliverables;
        });

        return response()->json($deliverables, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/deliverables/{id}",
     *     summary="Get a specific deliverable",
     *     tags={"Deliverables"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable details with relation IDs"
     *     )
     * )
     */
    public function show(Deliverable $deliverable): \Illuminate\Http\JsonResponse
    {
        $deliverable->load('phase', 'files');

        $deliverable->phase_id = $deliverable->phase ? [$deliverable->phase->id] : [];
        $deliverable->file_ids = $deliverable->files->pluck('id');

        return response()->json($deliverable);
    }

    /**
     * @OA\Put(
     *     path="/api/pg/deliverables/{id}",
     *     summary="Update a deliverable",
     *     tags={"Deliverables"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="phase_id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="due_date", type="string", format="date-time"),
     *             @OA\Property(property="file_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable updated successfully"
     *     )
     * )
     */
    public function update(Request $request, Deliverable $deliverable): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'phase_id' => 'sometimes|required|exists:phases,id',
            'name' => 'sometimes|required|string|max:255',
            'due_date' => 'sometimes|required|date',
            'file_ids' => 'nullable|array',
            'file_ids.*' => 'exists:files,id',
        ]);

        $deliverable->update($request->only('phase_id', 'name', 'due_date'));

        if ($request->has('file_ids')) {
            $deliverable->files()->sync($request->input('file_ids'));
        }

        return response()->json($deliverable);
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/deliverables/{id}",
     *     summary="Delete a deliverable",
     *     tags={"Deliverables"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable deleted successfully"
     *     )
     * )
     */
    public function destroy(Deliverable $deliverable): \Illuminate\Http\JsonResponse
    {
        $deliverable->delete();

        return response()->json(['message' => 'Deliverable deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/deliverables/dropdown",
     *     summary="Get deliverables for dropdown",
     *     tags={"Deliverables"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of deliverables formatted for dropdowns"
     *     )
     * )
     */
    public function dropdown(): \Illuminate\Http\JsonResponse
    {
        $deliverables = Deliverable::all()->map(function ($deliverable) {
            return [
                'value' => $deliverable->id,
                'label' => $deliverable->name,
            ];
        });

        return response()->json($deliverables);
    }
}

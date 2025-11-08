<?php

namespace App\Http\Controllers;

use App\Models\DeliverableFile;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Deliverable Files",
 *     description="API endpoints for managing deliverable-file associations"
 * )
 */
class DeliverableFileController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/deliverable-files",
     *     summary="Get all deliverable-file associations",
     *     tags={"Deliverable Files"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of deliverable-file associations with related names"
     *     )
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $deliverableFiles = DeliverableFile::with(['deliverable', 'file'])->orderBy('updated_at', 'desc')->get();

        $deliverableFiles->each(function ($deliverableFile) {
            $deliverableFile->deliverable_names = $deliverableFile->deliverable ? $deliverableFile->deliverable->name : '';
            $deliverableFile->file_names = $deliverableFile->file ? $deliverableFile->file->name : '';
        });

        return response()->json($deliverableFiles);
    }

    /**
     * @OA\Post(
     *     path="/api/pg/deliverable-files",
     *     summary="Create a new deliverable-file association",
     *     tags={"Deliverable Files"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"deliverable_id","file_id"},
     *
     *             @OA\Property(property="deliverable_id", type="integer", example=1),
     *             @OA\Property(property="file_id", type="integer", example=1)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Deliverable-file association created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'deliverable_id' => 'required|exists:deliverables,id',
            'file_id' => 'required|exists:files,id',
        ]);

        $deliverableFile = DeliverableFile::create($request->only('deliverable_id', 'file_id'));

        return response()->json($deliverableFile, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/deliverable-files/{deliverable_id}/{file_id}",
     *     summary="Get a specific deliverable-file association",
     *     tags={"Deliverable Files"},
     *
     *     @OA\Parameter(
     *         name="deliverable_id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="file_id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable-file association details with relation IDs"
     *     )
     * )
     */
    public function show(int $deliverableId, int $fileId): \Illuminate\Http\JsonResponse
    {
        $deliverableFile = DeliverableFile::where('deliverable_id', $deliverableId)
            ->where('file_id', $fileId)
            ->firstOrFail();

        $deliverableFile->load('deliverable', 'file');

        $deliverableFile->deliverable_id = $deliverableFile->deliverable ? [$deliverableFile->deliverable->id] : [];
        $deliverableFile->file_id = $deliverableFile->file ? [$deliverableFile->file->id] : [];

        return response()->json($deliverableFile);
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/deliverable-files/{deliverable_id}/{file_id}",
     *     summary="Delete a deliverable-file association",
     *     tags={"Deliverable Files"},
     *
     *     @OA\Parameter(
     *         name="deliverable_id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="file_id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable-file association deleted successfully"
     *     )
     * )
     */
    public function destroy(int $deliverableId, int $fileId): \Illuminate\Http\JsonResponse
    {
        $deliverableFile = DeliverableFile::where('deliverable_id', $deliverableId)
            ->where('file_id', $fileId)
            ->firstOrFail();

        $deliverableFile->delete();

        return response()->json(['message' => 'Deliverable-file association deleted successfully']);
    }
}

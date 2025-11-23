<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApprovalRequestFileControllerTest extends TestCase
{
    public function test_user_can_list_files_for_approval_request(): void
    {
        $user = User::factory()->create();

        $approvalRequest = ApprovalRequest::factory()->create([
            'requested_by' => $user->id,
            'action_key' => 'noop',
        ]);

        $firstFile = File::create([
            'name' => 'Adjunto A',
            'extension' => 'pdf',
            'url' => 'https://files.test/adunto-a.pdf',
            'disk' => 'public',
            'path' => 'pg/approval-requests/2024/12/01/adjunto-a.pdf',
        ]);
        $secondFile = File::create([
            'name' => 'Adjunto B',
            'extension' => 'docx',
            'url' => 'https://files.test/adunto-b.docx',
            'disk' => 'public',
            'path' => 'pg/approval-requests/2024/12/01/adjunto-b.docx',
        ]);

        $approvalRequest->files()->attach([$firstFile->id, $secondFile->id]);

        $this->setAtlasUser(['id' => $user->id]);

        $response = $this->getJson("/api/pg/approval-requests/{$approvalRequest->id}/files", $this->defaultHeaders)
            ->assertOk()
            ->json();

        $this->assertEqualsCanonicalizing(
            [$firstFile->id, $secondFile->id],
            collect($response)->pluck('id')->all()
        );
    }

    public function test_user_can_upload_file_to_approval_request(): void
    {
        $user = User::factory()->create();

        $approvalRequest = ApprovalRequest::factory()->create([
            'requested_by' => $user->id,
            'action_key' => 'noop',
        ]);

        config(['filesystems.default' => 'public']);
        Storage::fake('public');

        $this->setAtlasUser(['id' => $user->id]);

        $upload = UploadedFile::fake()->create('observaciones.pdf', 120, 'application/pdf');

        $response = $this->post(
            "/api/pg/approval-requests/{$approvalRequest->id}/files",
            [
                'file' => $upload,
                'name' => 'Observaciones Finales',
            ],
            $this->defaultHeaders
        )->assertCreated()
            ->json();

        $this->assertDatabaseHas('files', [
            'id' => $response['id'],
            'name' => 'Observaciones Finales',
            'extension' => 'pdf',
            'disk' => 'public',
        ]);

        $this->assertDatabaseHas('approval_request_files', [
            'approval_request_id' => $approvalRequest->id,
            'file_id' => $response['id'],
        ]);

        $storedFile = File::query()->find($response['id']);
        $this->assertNotNull($storedFile);

        $this->assertTrue(Storage::disk('public')->exists($storedFile->path));
    }

    protected function setAtlasUser(array $payload): void
    {
        if (! array_key_exists('roles', $payload)) {
            $payload['roles'] = ['Director'];
        }

        $this->mockAtlasUser($payload);
    }
}

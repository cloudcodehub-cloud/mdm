<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfilePhotoService
{
    public function storeEmployee(Employee $employee, UploadedFile $file): void
    {
        $this->replacePath($employee, $file, 'profile-photos/employees');
    }

    public function storeClient(Client $client, UploadedFile $file): void
    {
        $this->replacePath($client, $file, 'profile-photos/clients');
    }

    public function storeCredentialDocument(EmployeeCredential $credential, UploadedFile $file): void
    {
        $this->deletePath($credential->document_path);
        $path = $file->store('credential-documents/'.$credential->employee_id, 'local');
        $credential->update(['document_path' => $path]);
    }

    public function removeEmployee(Employee $employee): void
    {
        $this->deletePath($employee->profile_photo_path);
        $employee->update(['profile_photo_path' => null]);
    }

    public function removeClient(Client $client): void
    {
        $this->deletePath($client->profile_photo_path);
        $client->update(['profile_photo_path' => null]);
    }

    public function streamEmployee(Employee $employee): StreamedResponse
    {
        return $this->stream($employee->profile_photo_path);
    }

    public function streamClient(Client $client): StreamedResponse
    {
        return $this->stream($client->profile_photo_path);
    }

    public function employeeUrl(Employee $employee): ?string
    {
        if (! $employee->hasPhoto()) {
            return null;
        }

        return route('employees.photo', [
            'employee' => $employee,
            'v' => $employee->updated_at?->timestamp,
        ]);
    }

    public function clientUrl(Client $client): ?string
    {
        if (! $client->hasPhoto()) {
            return null;
        }

        return route('clients.photo', [
            'client' => $client,
            'v' => $client->updated_at?->timestamp,
        ]);
    }

    private function replacePath(Employee|Client $model, UploadedFile $file, string $directory): void
    {
        $this->deletePath($model->profile_photo_path);
        $path = $file->store($directory, 'local');
        $model->update(['profile_photo_path' => $path]);
    }

    private function deletePath(?string $path): void
    {
        if (filled($path) && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    private function stream(?string $path): StreamedResponse
    {
        abort_unless(filled($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}

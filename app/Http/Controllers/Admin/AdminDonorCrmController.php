<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDonorNoteRequest;
use App\Http\Requests\Admin\StoreDonorTaskRequest;
use App\Http\Requests\Admin\UpdateDonorOwnerRequest;
use App\Http\Requests\Admin\UpdateDonorTaskRequest;
use App\Models\Donor;
use App\Models\DonorNote;
use App\Models\DonorTask;
use Illuminate\Http\RedirectResponse;

class AdminDonorCrmController extends Controller
{
    public function updateOwner(UpdateDonorOwnerRequest $request, Donor $donor): RedirectResponse
    {
        $donor->update([
            'owner_user_id' => $request->validated('owner_user_id'),
        ]);

        $message = $donor->owner_user_id
            ? 'Donor owner updated.'
            : 'Donor owner cleared.';

        toastr()->success($message);

        return redirect()
            ->route('admin.donors.show', $donor)
            ->with('status', $message);
    }

    public function storeNote(StoreDonorNoteRequest $request, Donor $donor): RedirectResponse
    {
        $donor->notes()->create([
            'user_id' => $request->user()?->id,
            'body' => trim((string) $request->validated('body')),
        ]);

        $message = 'Note added.';
        toastr()->success($message);

        return redirect()
            ->route('admin.donors.show', $donor)
            ->with('status', $message);
    }

    public function destroyNote(Donor $donor, DonorNote $note): RedirectResponse
    {
        abort_unless($note->donor_id === $donor->id, 404);

        $note->delete();

        $message = 'Note deleted.';
        toastr()->success($message);

        return redirect()
            ->route('admin.donors.show', $donor)
            ->with('status', $message);
    }

    public function storeTask(StoreDonorTaskRequest $request, Donor $donor): RedirectResponse
    {
        $validated = $request->validated();
        $status = $validated['status'] ?? DonorTask::STATUS_OPEN;

        if (! in_array($status, DonorTask::statuses(), true)) {
            $status = DonorTask::STATUS_OPEN;
        }

        $donor->tasks()->create([
            'title' => trim((string) $validated['title']),
            'body' => filled($validated['body'] ?? null) ? trim((string) $validated['body']) : null,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'created_by' => $request->user()?->id,
            'due_at' => $validated['due_at'] ?? null,
            'status' => $status,
            'completed_at' => $status === DonorTask::STATUS_DONE ? now() : null,
        ]);

        $message = 'Task created.';
        toastr()->success($message);

        return redirect()
            ->route('admin.donors.show', $donor)
            ->with('status', $message);
    }

    public function updateTask(UpdateDonorTaskRequest $request, Donor $donor, DonorTask $task): RedirectResponse
    {
        abort_unless($task->donor_id === $donor->id, 404);

        $validated = $request->validated();
        $payload = [];

        if (array_key_exists('title', $validated)) {
            $payload['title'] = trim((string) $validated['title']);
        }

        if (array_key_exists('body', $validated)) {
            $payload['body'] = filled($validated['body']) ? trim((string) $validated['body']) : null;
        }

        if (array_key_exists('assigned_to', $validated)) {
            $payload['assigned_to'] = $validated['assigned_to'];
        }

        if (array_key_exists('due_at', $validated)) {
            $payload['due_at'] = $validated['due_at'];
        }

        if (array_key_exists('status', $validated)) {
            $status = (string) $validated['status'];
            $payload['status'] = $status;

            if ($status === DonorTask::STATUS_DONE) {
                $payload['completed_at'] = $task->completed_at ?? now();
            } elseif ($status === DonorTask::STATUS_OPEN) {
                $payload['completed_at'] = null;
            } else {
                $payload['completed_at'] = null;
            }
        }

        $task->update($payload);

        $message = 'Task updated.';
        toastr()->success($message);

        return redirect()
            ->route('admin.donors.show', $donor)
            ->with('status', $message);
    }

    public function destroyTask(Donor $donor, DonorTask $task): RedirectResponse
    {
        abort_unless($task->donor_id === $donor->id, 404);

        $task->delete();

        $message = 'Task deleted.';
        toastr()->success($message);

        return redirect()
            ->route('admin.donors.show', $donor)
            ->with('status', $message);
    }
}

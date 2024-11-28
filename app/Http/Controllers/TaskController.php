<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\Transaction;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function index()
    {
        return Task::where('is_active', true)->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'reward' => 'required|numeric|min:0',
            'time_estimate' => 'required|string',
            'category' => 'required|string',
            'difficulty' => 'required|in:Easy,Medium,Hard',
            'time_in_seconds' => 'required|integer|min:0',
            'steps' => 'required|array',
            'approval_type' => 'required|in:automatic,manual',
        ]);

        $task = Task::create($request->all());
        return response()->json($task, 201);
    }

    public function submit(Request $request, Task $task)
    {
        $request->validate([
            'screenshot_url' => 'required|url',
        ]);

        DB::beginTransaction();
        try {
            $submission = TaskSubmission::create([
                'task_id' => $task->id,
                'user_id' => auth()->id(),
                'screenshot_url' => $request->screenshot_url,
                'status' => $task->approval_type === 'automatic' ? 'approved' : 'pending',
            ]);

            $transaction = Transaction::create([
                'user_id' => auth()->id(),
                'task_id' => $task->id,
                'amount' => $task->reward,
                'type' => 'earning',
                'status' => $task->approval_type === 'automatic' ? 'completed' : 'pending',
            ]);

            $user = auth()->user();

            if ($task->approval_type === 'automatic') {
                $user->balance += $task->reward;
                $user->tasks_completed += 1;
                $user->save();

                Notification::create([
                    'user_id' => $user->id,
                    'title' => 'Task Completed',
                    'message' => "Your submission for \"{$task->title}\" has been automatically approved! \${$task->reward} has been added to your balance.",
                    'type' => 'success',
                    'is_read' => false,
                ]);
            } else {
                $user->pending_earnings += $task->reward;
                $user->save();

                Notification::create([
                    'user_id' => $user->id,
                    'title' => 'Task Submitted',
                    'message' => "Your submission for \"{$task->title}\" is pending review. We'll notify you once it's approved.",
                    'type' => 'info',
                    'is_read' => false,
                ]);
            }

            DB::commit();
            return response()->json(['submission' => $submission, 'transaction' => $transaction]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to submit task'], 500);
        }
    }

    public function review(Request $request, Task $task)
    {
        $request->validate([
            'submission_id' => 'required|exists:task_submissions,id',
            'status' => 'required|in:approved,rejected',
        ]);

        DB::beginTransaction();
        try {
            $submission = TaskSubmission::findOrFail($request->submission_id);
            $submission->status = $request->status;
            $submission->save();

            $transaction = Transaction::where('task_id', $task->id)
                ->where('user_id', $submission->user_id)
                ->first();

            $user = $submission->user;

            if ($request->status === 'approved') {
                $user->balance += $task->reward;
                $user->tasks_completed += 1;
                $user->pending_earnings -= $task->reward;
                $user->save();

                if ($transaction) {
                    $transaction->status = 'completed';
                    $transaction->save();
                }

                Notification::create([
                    'user_id' => $user->id,
                    'title' => 'Task Approved',
                    'message' => "Your submission for \"{$task->title}\" has been approved! \${$task->reward} has been added to your balance.",
                    'type' => 'success',
                    'is_read' => false,
                ]);
            } else {
                $user->pending_earnings -= $task->reward;
                $user->save();

                if ($transaction) {
                    $transaction->status = 'failed';
                    $transaction->save();
                }

                Notification::create([
                    'user_id' => $user->id,
                    'title' => 'Task Rejected',
                    'message' => "Your submission for \"{$task->title}\" has been rejected. Please review the requirements and try again.",
                    'type' => 'error',
                    'is_read' => false,
                ]);
            }

            DB::commit();
            return response()->json($submission);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to review submission'], 500);
        }
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
use App\Notifications\TaskReminder;
use App\Models\Category;
use App\Models\CategoryTask;



class TaskController extends Controller
{
    /**
     * Check if a task with a given ID exists in the database.
     *
     * @param int $id The ID of the task to check.
     * @return bool True if the task exists, false otherwise.
     */
    private function taskExists($id)
    {
        return Task::where('id', $id)->exists();
    }

    /**
     * Retrieve all tasks from the database.
     *
     * @return \Illuminate\Http\JsonResponse The list of tasks.
     */
    public function index(Request $request)
    {
        // $tasks = Task::all();
        // return response()->json($tasks);

        $query = Task::query();

        if ($request->has('completed')) {
            $query->where('is_completed', $request->boolean('completed'));
        }

        if ($request->has('due_date')) {
            $query->where('dueDate', $request->get('due_date'));
        }

        $tasks = $query->where('user_id', '=', auth()->user()->id)->paginate(5);

        return response()->json($tasks);
    }

    /**
     * Create a new task in the database.
     *
     * @param \Illuminate\Http\Request $request The request containing the task data.
     * @return \Illuminate\Http\JsonResponse The newly created task.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'taskName' => 'required|string',
            'description' => 'nullable|string',
            'dueDate' => 'required|date',
            'is_completed' => 'boolean',
            'category_ids.*' => 'exists:categories,id',
        ]);
    
        $time = strtotime($validatedData['dueDate']);
    
        $validatedData['dueDate'] = date('Y-m-d',$time);
    
        $user = auth()->user();
        $validatedData['user_id'] = $user->id;
    
        // Generate a unique share token
        $shareToken = md5(uniqid());


        try {
            $task = Task::create(array_merge($validatedData, ['share_token' => $shareToken]));
            $categoryIds = $validatedData['category_ids'] ?? [];

            // Attach the categories to the task
            foreach ($categoryIds as $categoryId) {
                $category = Category::findOrFail($categoryId);

                $category_task = CategoryTask::create([
                    'category_id' =>  $categoryId,
                    'task_id' => $task->id,
                ]);
            }

            $user->notify(new TaskReminder($task));

        } catch (\Exception $e) {
            throw $e;
        }
    
        return response()->json($task, 201);
    }

    /**
     * Retrieve a specific task from the database.
     *
     * @param int $id The ID of the task to retrieve.
     * @return \Illuminate\Http\JsonResponse The retrieved task.
     */
    public function show($id)
    {
        
        if (!$this->taskExists($id)) {
            return response()->json(['status' => "Task Not found"], 404);
        }

        // Retrieve the task from the database
        $task = Task::findorFail($id);

        // Check if the authenticated user is the owner of the task
        $user = auth()->user();
        if ($user->id !== $task->user_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json($task);
    }

    /**
     * Update a specific task in the database.
     *
     * @param int $id The ID of the task to update.
     * @param \Illuminate\Http\Request $request The request containing the updated task data.
     * @return \Illuminate\Http\JsonResponse The updated task.
     */
    public function update($id, Request $request)
    {
        if (!$this->taskExists($id)) {
            return response()->json(['status' => "Task Not found"], 404);
        }

        $validatedData = $request->validate([
            'taskName' => 'string',
            'description' => 'nullable|string',
            'dueDate' => 'date',
            'isCompleted' => 'boolean',
        ]);

        // Retrieve the task from the database
        $task = Task::find($id);

        // Check if the authenticated user is the owner of the task
        $user = auth()->user();
        if ($user->id !== $task->user_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $time = strtotime($validatedData['dueDate']);
        $validatedData['dueDate'] = date('Y-m-d',$time);

        // Update the task with the validated data
        $task->update($validatedData);

        return response()->json($task);
    }


    /**
     * Delete a specific task from the database.
     *
     * @param int $id The ID of the task to delete.
     * @return \Illuminate\Http\JsonResponse A successful response with status code 204.
     */
    public function destroy($id)
    {
        if (!$this->taskExists($id)) {
            return response()->json(['status' => "Task Not found"], 404);
        }

        // Retrieve the task from the database
        $task = Task::find($id);

        // Check if the authenticated user is the owner of the task
        $user = auth()->user();
        if ($user->id !== $task->user_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Delete the task from the database
        $task->delete();

        return response()->json(null, 204);
    }

    public function showByShareToken($shareToken)
    {
        $task = Task::where('share_token', $shareToken)->first();

        if (!$task) {
            return response()->json(['error' => 'Task not found'], 404);
        }

        // Check if the authenticated user is the owner of the task
        $user = auth()->user();
        if ($user && $user->id === $task->user_id) {
            return response()->json($task, 200);
        }

        // Return the task with a 200 OK response
        return response()->json($task, 200);
    }

    public function getTasksByCategory($categoryId)
    {
        $category = Category::findOrFail($categoryId);

        $tasks = $category->tasks;

        return response()->json($tasks, 200);
    }
}

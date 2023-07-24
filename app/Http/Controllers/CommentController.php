<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Comment;

class CommentController extends Controller
{
    public function store(Request $request)
    {
        
        $validatedData = $request->validate([
            'description' => 'string'
        ]);
        $user = auth()->user();
        $validatedData['user_id'] = $user->id;
        $validatedData['task_id'] = $request->get('task_id');

        $comment = Comment::create($validatedData);

        return response()->json($comment, 201);
    }
}

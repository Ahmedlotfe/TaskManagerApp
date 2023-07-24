<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;

class CategoryController extends Controller
{
    public function getCategories()
    {
        $categories = Category::all();

        return response()->json($categories, 200);
    }

    public function createCategory(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|unique:categories',
        ]);

        $category = Category::create($validatedData);

        return response()->json($category, 201);
    }
}

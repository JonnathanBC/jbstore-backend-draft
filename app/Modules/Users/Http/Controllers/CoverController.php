<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Cover;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CoverController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:1024',
            'title' => 'required|string|max:255',
            'start_at' => 'required|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'is_active' => 'required|boolean',
        ]);

        // if ($request->hasFile('image')) {
        //     $path = $request->file('image')->store('covers', 'public');
        //     $request['image_path'] = $path;
        // }

        $data['image_path'] = Storage::put('covers', $data['image']);
        $cover = Cover::create($data);

        return response()->json($cover, 201);
    }
}

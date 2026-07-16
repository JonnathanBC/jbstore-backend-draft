<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Cover;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CoverController extends Controller
{

    public function index(Request $request)
    {
        $allowedSortable = ['updated_at', 'order'];
        $query = Cover::query();

        return $this->paginated(
            $query,
            $request,
            $allowedSortable,
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:1024',
            'title' => 'required|string|max:255',
            'start_at' => 'required|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'is_active' => 'required|boolean',
        ]);

        $data['image_path'] = Storage::put('covers', $data['image']);
        $cover = Cover::create($data);

        return response()->json($cover, 201);
    }

    public function show(Cover $cover)
    {
        return response()->json($cover);
    }

    public function update(Request $request, Cover $cover)
    {
        $data = $request->validate([
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:1024',
            'title' => 'required|string|max:255',
            'start_at' => 'required|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'is_active' => 'required|boolean',
        ]);

        if ($request->hasFile('image')) {
            $oldPath = $cover->image_path;
            $data['image_path'] = Storage::put('covers', $data['image']);

            Storage::delete($oldPath);
        }

        $cover->update($data);

        return response()->json($cover);
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:covers,id',
        ]);

        foreach ($data['ids'] as $index => $id) {
            Cover::where('id', $id)->update(['order' => $index]);
        }

        return response()->json(null, 204);
    }

    public function destroy(Cover $cover)
    {
        Storage::delete($cover->image_path);
        $cover->delete();

        return response()->json(null, 204);
    }
}

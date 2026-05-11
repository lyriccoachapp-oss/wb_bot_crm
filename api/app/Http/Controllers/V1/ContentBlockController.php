<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use Illuminate\Http\Request;

class ContentBlockController extends Controller
{
    /**
     * Получить список всех текстовых блоков (публичный метод)
     */
    public function index()
    {
        $query = ContentBlock::query();
        $user = auth('api')->user();

        if ($user) {
            if (!$user->admin) {
                $query->whereIn('access_level', ['public', 'auth']);
            }
        } else {
            $query->where('access_level', 'public');
        }

        $blocks = $query->get()->keyBy('key');
        return response()->json([
            'success' => true,
            'data' => $blocks
        ]);
    }

    /**
     * Создать новый блок
     */
    public function store(Request $request)
    {
        $request->validate([
            'key' => 'required|string|unique:content_blocks,key',
            'content' => 'required|array',
            'access_level' => 'nullable|in:public,auth,admin'
        ]);

        $data = $request->all();
        if (empty($data['access_level'])) {
            $data['access_level'] = 'public';
        }

        $block = ContentBlock::create($data);

        return response()->json([
            'success' => true,
            'data' => $block
        ]);
    }

    /**
     * Обновить блок
     */
    public function update(Request $request, $id)
    {
        $block = ContentBlock::findOrFail($id);

        $request->validate([
            'key' => 'required|string|unique:content_blocks,key,' . $id,
            'content' => 'required|array',
            'access_level' => 'nullable|in:public,auth,admin'
        ]);

        $data = $request->all();
        if (empty($data['access_level'])) {
            $data['access_level'] = 'public';
        }

        $block->update($data);

        return response()->json([
            'success' => true,
            'data' => $block
        ]);
    }

    /**
     * Удалить блок
     */
    public function destroy($id)
    {
        $block = ContentBlock::findOrFail($id);
        $block->delete();

        return response()->json([
            'success' => true
        ]);
    }
}

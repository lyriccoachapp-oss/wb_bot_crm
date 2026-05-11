<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Контроллер состояния бота
 */
class BotController extends Controller
{
    /**
     * Получить или записать текущую операцию бота
     * GET/POST /api/v1/bot/operation
     */
    public function operation(Request $request): JsonResponse
    {
        $user = $request->auth_user;
        $chatId = $request->input('chat_id', $user->id_telegram);
        
        if ($request->isMethod('post')) {
            $operation = $request->input('operation', 'none');
            DB::table('bot_operations')->updateOrInsert(
                ['id_telegram' => $user->id_telegram, 'id_chat' => $chatId],
                ['operation' => $operation]
            );
            return $this->success(['operation' => $operation]);
        }

        $record = DB::table('bot_operations')
            ->where('id_telegram', $user->id_telegram)
            ->where('id_chat', $chatId)
            ->first();

        return $this->success([
            'operation' => $record ? $record->operation : 'none',
        ]);
    }
}

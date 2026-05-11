<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    /**
     * GET /api/v1/locations
     * Получить геоданные пользователей за указанную дату
     */
    public function index(Request $request): JsonResponse
    {
        $date = $request->input('date', date('Y-m-d'));

        $results = DB::table('bot_wp_tracking as t')
            ->join('bot_wp_worktime as w', 't.id_worktime', '=', 'w.id_worktime')
            ->join('bot_users as u', 'w.id_telegram', '=', 'u.id_telegram')
            ->leftJoin('companies as c', 'u.company_slug', '=', 'c.slug')
            ->where('w.workday', $date)
            ->select(
                'u.id as user_id',
                'u.firstname',
                'u.lastname',
                'u.company_slug',
                'c.name as company_name',
                'w.id_worktime',
                'w.checkin',
                'w.checkout',
                't.latitude',
                't.longitude',
                't.loc_time'
            )
            ->orderBy('t.loc_time', 'asc')
            ->get();
        
        $grouped = [];
        foreach ($results as $row) {
            // Если компания найдена, берём её имя, иначе фоллбэк на слаг или "Без компании"
            $company = $row->company_name ?: ($row->company_slug ?: 'Без компании');
            $uid = $row->user_id;
            $wid = $row->id_worktime;

            if (!isset($grouped[$company])) {
                $grouped[$company] = [];
            }
            if (!isset($grouped[$company][$uid])) {
                $grouped[$company][$uid] = [
                    'user' => [
                        'id' => $row->user_id,
                        'name' => trim($row->firstname . ' ' . $row->lastname) ?: 'Пользователь ' . $row->user_id
                    ],
                    'worktimes' => []
                ];
            }
            if (!isset($grouped[$company][$uid]['worktimes'][$wid])) {
                $grouped[$company][$uid]['worktimes'][$wid] = [
                    'id_worktime' => $wid,
                    'checkin' => $row->checkin,
                    'checkout' => $row->checkout,
                    'points' => []
                ];
            }
            
            $grouped[$company][$uid]['worktimes'][$wid]['points'][] = [
                'lat' => (float)$row->latitude,
                'lng' => (float)$row->longitude,
                'time' => $row->loc_time
            ];
        }

        // Преобразуем ассоциативные массивы в списки для удобства фронтенда
        $data = [];
        foreach ($grouped as $comp => $users) {
            $userList = [];
            foreach ($users as $u) {
                $userList[] = [
                    'user' => $u['user'],
                    'worktimes' => array_values($u['worktimes'])
                ];
            }
            $data[$comp] = $userList;
        }

        return $this->success($data);
    }
}

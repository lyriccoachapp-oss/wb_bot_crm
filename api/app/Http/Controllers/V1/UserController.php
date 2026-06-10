<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Repositories\BotUserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\BotUser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Контроллер пользователей
 *
 * Просмотр пользователей из bot_users + управление CRM-пользователями.
 */
class UserController extends Controller
{
	public function __construct(
		private readonly BotUserRepository $userRepo
	) {
	}

	/**
	 * GET /api/v1/users
	 *
	 * Список пользователей (bot_users) с пагинацией.
	 */
	public function index(Request $request): JsonResponse
	{
		$page  = (int)$request->query('page',  1);
		$limit = (int)$request->query('limit', 20);

		$filters = [];
		if ($request->has('status')) {
			$filters['status'] = $request->query('status');
		}
		if ($request->has('company_slug')) {
			$filters['company_slug'] = $request->query('company_slug');
		}
		if ($request->has('search')) {
			$filters['search'] = $request->query('search');
		}

		$sort = [];
		if ($request->has('sort_by')) {
			$sort['by'] = $request->query('sort_by');
			$sort['dir'] = $request->query('sort_dir', 'asc');
		}

		$paginator = $this->userRepo->paginate($page, $limit, $filters, $sort);

		return $this->paginated($paginator, fn ($u) => [
			'id'          => $u->id,
			'id_telegram' => $u->id_telegram,
			'username'    => $u->username,
			'firstname'   => $u->firstname,
			'lastname'    => $u->lastname,
			'full_name'   => $u->full_name,
			'email'       => $u->email,
			'phone'       => $u->phone,
			'status'      => $u->status,
			'lcode'       => $u->lcode,
			'sin_num'     => $u->sin_num,
			'addr'        => $u->addr,
			'note'        => $u->note,
			'admin'       => (bool)$u->admin,
			'role_id'     => $u->role_id,
			'role_name'   => $u->role ? $u->role->name : null,
			'company_slug'=> $u->company_slug,
			'company'     => $u->company ? $u->company->name : null,
			'registered'  => $u->isRegistered(),
		]);
	}

	/**
	 * GET /api/v1/users/{id}
	 *
	 * Получить пользователя по ID (id поля БД, а не id_telegram).
	 */
	public function show(int $id): JsonResponse
	{
		$botUser = $this->userRepo->findById($id);

		if (!$botUser) {
			return $this->error('Пользователь не найден.', 404);
		}

		return $this->success([
			'id'          => $botUser->id,
			'id_telegram' => $botUser->id_telegram,
			'username'    => $botUser->username,
			'firstname'   => $botUser->firstname,
			'lastname'    => $botUser->lastname,
			'full_name'   => $botUser->full_name,
			'email'       => $botUser->email,
			'phone'       => $botUser->phone,
			'addr'        => $botUser->addr,
			'sin_num'     => $botUser->sin_num,
			'status'      => $botUser->status,
			'admin'       => (bool)$botUser->admin,
			'tester'      => $botUser->tester,
			'role_id'     => $botUser->role_id,
			'role_slug'   => $botUser->role?->slug,
			'company_slug'=> $botUser->company_slug,
			'company'     => $botUser->company ? $botUser->company->name : null,
		]);
	}

	/**
	 * POST /api/v1/users
	 *
	 * Создать пользователя.
	 */
	public function store(Request $request): JsonResponse
	{
		$validator = Validator::make($request->all(), [
			'email'    => 'required|email|unique:bot_users,email',
			'password' => 'required|string|min:6',
			'firstname'=> 'required|string',
			'lastname' => 'required|string',
			'role_id'  => 'nullable|integer|exists:crm_roles,id',
			'company_slug' => 'nullable|string|exists:companies,slug',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		$user = BotUser::create([
			'email'    => $request->input('email'),
			'password' => Hash::make($request->input('password')),
			'firstname'=> $request->input('firstname'),
			'lastname' => $request->input('lastname'),
			'role_id'  => $request->input('role_id', null),
			'company_slug' => $request->input('company_slug', null),
			'status'   => 'new',
			'date_add' => date('Y-m-d H:i:s'),
		]);

		return $this->success([
			'id'    => $user->id,
			'email' => $user->email,
			'role'  => $user->role?->slug,
		], 'Пользователь создан.', 201);
	}

	/**
	 * PUT /api/v1/users/{id}
	 *
	 * Обновить пользователя.
	 */
	public function update(Request $request, int $id): JsonResponse
	{
		$user = $this->userRepo->findById($id);

		if (!$user) {
			return $this->error('Пользователь не найден.', 404);
		}

		$validator = Validator::make($request->all(), [
			'role_id'  => 'nullable|integer|exists:crm_roles,id',
			'status'   => 'nullable|string',
			'password' => 'nullable|string|min:6',
			'firstname'=> 'nullable|string',
			'lastname' => 'nullable|string',
			'email'    => 'nullable|email|unique:bot_users,email,' . $id,
			'phone'    => 'nullable|string',
			'username' => 'nullable|string',
			'lcode'    => 'nullable|in:ru,uk,en',
			'sin_num'  => 'nullable|string',
			'addr'     => 'nullable|string',
			'note'     => 'nullable|string',
			'company_slug' => 'nullable|string|exists:companies,slug',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		$data = array_filter([
			'role_id'  => $request->has('role_id') ? $request->input('role_id') : null,
			'status'   => $request->input('status'),
			'password' => $request->input('password') ? Hash::make($request->input('password')) : null,
			'firstname'=> $request->input('firstname'),
			'lastname' => $request->input('lastname'),
			'email'    => $request->input('email'),
			'phone'    => $request->input('phone'),
			'username' => $request->input('username'),
			'lcode'    => $request->input('lcode'),
			'sin_num'  => $request->input('sin_num'),
			'addr'     => $request->input('addr'),
			'note'     => $request->input('note'),
			'company_slug' => $request->has('company_slug') ? $request->input('company_slug') : null,
		], fn ($v) => $v !== null && $v !== '');
		
		if ($request->has('role_id') && !$request->input('role_id')) {
			$data['role_id'] = null; // Позволяем снять роль
		}
		if ($request->has('company_slug') && !$request->input('company_slug')) {
			$data['company_slug'] = null;
		}

		$updated = $this->userRepo->update($user, $data);

		return $this->success([
			'id'     => $updated->id,
			'email'  => $updated->email,
			'role'   => $updated->role?->slug,
			'status' => $updated->status,
		], 'Пользователь обновлён.');
	}

	/**
	 * GET /api/v1/users/export/xlsx
	 *
	 * Экспорт отфильтрованных пользователей в формат XLSX.
	 */
	public function exportXlsx(Request $request)
	{
		$filters = [];
		if ($request->has('status')) {
			$filters['status'] = $request->query('status');
		}
		if ($request->has('company_slug')) {
			$filters['company_slug'] = $request->query('company_slug');
		}
		if ($request->has('search')) {
			$filters['search'] = $request->query('search');
		}

		$sort = [];
		if ($request->has('sort_by')) {
			$sort['by'] = $request->query('sort_by');
			$sort['dir'] = $request->query('sort_dir', 'asc');
		}

		$users = $this->userRepo->all($filters, $sort);

		$spreadsheet = new Spreadsheet();
		$sheet       = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Пользователи');

		// Заголовки столбцов
		$headers = ['ID', 'Telegram ID', 'Telegram Username', 'Имя', 'Фамилия', 'Email', 'Телефон', 'SIN Номер', 'Адрес', 'Компания', 'Роль', 'Статус'];
		foreach ($headers as $colIdx => $header) {
			$colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
			$sheet->setCellValue($colLetter . '1', $header);
		}

		// Стилизуем шапку
		$sheet->getStyle('A1:L1')->getFont()->setBold(true);
		$sheet->getStyle('A1:L1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
		$sheet->getStyle('A1:L1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		// Заполняем данные
		$row = 2;
		foreach ($users as $u) {
			$sheet->setCellValue('A' . $row, $u->id);
			$sheet->setCellValue('B' . $row, $u->id_telegram ?: '');
			$sheet->setCellValue('C' . $row, $u->username ?: '');
			$sheet->setCellValue('D' . $row, $u->firstname ?: '');
			$sheet->setCellValue('E' . $row, $u->lastname ?: '');
			$sheet->setCellValue('F' . $row, $u->email ?: '');
			$sheet->setCellValue('G' . $row, $u->phone ?: '');
			$sheet->setCellValue('H' . $row, $u->sin_num ?: '');
			$sheet->setCellValue('I' . $row, $u->addr ?: '');
			$sheet->setCellValue('J' . $row, $u->company ? $u->company->name : ($u->company_slug ?: ''));
			$sheet->setCellValue('K' . $row, $u->role ? $u->role->name : '');
			$sheet->setCellValue('L' . $row, $u->status ?: '');
			$row++;
		}

		// Автоширина столбцов
		foreach (range(1, 12) as $colIdx) {
			$colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
			$sheet->getColumnDimension($colLetter)->setAutoSize(true);
		}

		// Сетка границ
		$sheet->getStyle('A1:L' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

		// Сохраняем во временный файл
		$tmpPath = tempnam(sys_get_temp_dir(), 'export_users_xlsx');
		(new Xlsx($spreadsheet))->save($tmpPath);

		return response()->download($tmpPath, 'users_export_' . date('Y-m-d') . '.xlsx', [
			'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		])->deleteFileAfterSend(true);
	}

	/**
	 * GET /api/v1/users/export/csv
	 *
	 * Экспорт отфильтрованных пользователей в формат CSV.
	 */
	public function exportCsv(Request $request)
	{
		$filters = [];
		if ($request->has('status')) {
			$filters['status'] = $request->query('status');
		}
		if ($request->has('company_slug')) {
			$filters['company_slug'] = $request->query('company_slug');
		}
		if ($request->has('search')) {
			$filters['search'] = $request->query('search');
		}

		$sort = [];
		if ($request->has('sort_by')) {
			$sort['by'] = $request->query('sort_by');
			$sort['dir'] = $request->query('sort_dir', 'asc');
		}

		$users = $this->userRepo->all($filters, $sort);

		$tmpPath = tempnam(sys_get_temp_dir(), 'export_users_csv');
		$fp = fopen($tmpPath, 'w');

		// Добавим BOM для поддержки кириллицы в Excel
		fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

		fputcsv($fp, ['ID', 'Telegram ID', 'Telegram Username', 'Имя', 'Фамилия', 'Email', 'Телефон', 'SIN Номер', 'Адрес', 'Компания', 'Роль', 'Статус'], ",", "\"", "\\");

		foreach ($users as $u) {
			fputcsv($fp, [
				$u->id,
				$u->id_telegram ?: '',
				$u->username ?: '',
				$u->firstname ?: '',
				$u->lastname ?: '',
				$u->email ?: '',
				$u->phone ?: '',
				$u->sin_num ?: '',
				$u->addr ?: '',
				$u->company ? $u->company->name : ($u->company_slug ?: ''),
				$u->role ? $u->role->name : '',
				$u->status ?: ''
			], ",", "\"", "\\");
		}
		fclose($fp);

		return response()->download($tmpPath, 'users_export_' . date('Y-m-d') . '.csv', [
			'Content-Type' => 'text/csv; charset=UTF-8',
		])->deleteFileAfterSend(true);
	}
}

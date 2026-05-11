<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Контроллер отчётов
 *
 * Отчёты по объектам, времени, чекам. Экспорт XLSX.
 */
class ReportController extends Controller
{
	public function __construct(
		private readonly ReportService $reportService
	) {
	}

	/**
	 * GET /api/v1/reports/objects
	 *
	 * Отчёт по объекту (рабочее время + чеки).
	 */
	public function objects(Request $request): JsonResponse
	{
		$validator = Validator::make($request->all(), [
			'place_id'  => 'required|integer',
			'date_from' => 'required|date_format:Y-m-d',
			'date_to'   => 'required|date_format:Y-m-d|after_or_equal:date_from',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		$employeeIds = [];
		if ($request->has('employees')) {
			$val = $request->query('employees');
			$employeeIds = is_string($val) && str_contains($val, ',') ? explode(',', $val) : (array)$val;
		}

		$report = $this->reportService->objectReport(
			(int)$request->query('place_id'),
			$request->query('date_from'),
			$request->query('date_to'),
			$employeeIds
		);

		return $this->success($report);
	}

	/**
	 * GET /api/v1/reports/objects/xlsx
	 *
	 * Скачать XLSX отчёт по объекту.
	 */
	public function objectsXlsx(Request $request): BinaryFileResponse|JsonResponse
	{
		$validator = Validator::make($request->all(), [
			'place_id'  => 'required|integer',
			'date_from' => 'required|date_format:Y-m-d',
			'date_to'   => 'required|date_format:Y-m-d|after_or_equal:date_from',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		$employeeIds = [];
		if ($request->has('employees')) {
			$val = $request->query('employees');
			$employeeIds = is_string($val) && str_contains($val, ',') ? explode(',', $val) : (array)$val;
		}

		try {
			$tmpPath = $this->reportService->createObjectXlsx(
				(int)$request->query('place_id'),
				$request->query('date_from'),
				$request->query('date_to'),
				$employeeIds
			);

			$fileName = 'report_object_' . $request->query('place_id') . '_'
				. $request->query('date_from') . '.xlsx';

			return response()->download($tmpPath, $fileName, [
				'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			])->deleteFileAfterSend(true);

		} catch (\Exception $e) {
			return $this->error('Ошибка генерации отчёта: ' . $e->getMessage(), 500);
		}
	}
	/**
	 * GET /api/v1/reports/employees
	 *
	 * Отчёт по сотрудникам (рабочее время + чеки).
	 */
	public function employees(Request $request): JsonResponse
	{
		$validator = Validator::make($request->all(), [
			'y' => 'required|integer|min:2020|max:2050',
			'm' => 'required|integer|min:1|max:12',
			'h' => 'required|integer|in:1,2',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		$report = $this->reportService->employeeReport(
			(int)$request->query('y'),
			(int)$request->query('m'),
			(int)$request->query('h')
		);

		return $this->success($report);
	}

	/**
	 * GET /api/v1/reports/employees/xlsx
	 *
	 * Скачать XLSX отчёт по сотрудникам.
	 */
	public function employeesXlsx(Request $request): BinaryFileResponse|JsonResponse
	{
		$validator = Validator::make($request->all(), [
			'y' => 'required|integer|min:2020|max:2050',
			'm' => 'required|integer|min:1|max:12',
			'h' => 'required|integer|in:1,2',
		]);

		if ($validator->fails()) {
			return $this->error($validator->errors()->first(), 422);
		}

		try {
			$tmpPath = $this->reportService->createEmployeeXlsx(
				(int)$request->query('y'),
				(int)$request->query('m'),
				(int)$request->query('h')
			);

			$fileName = sprintf('report_employees_%04d_%02d_h%d.xlsx', 
				$request->query('y'), $request->query('m'), $request->query('h'));

			return response()->download($tmpPath, $fileName, [
				'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			])->deleteFileAfterSend(true);

		} catch (\Exception $e) {
			return $this->error('Ошибка генерации отчёта: ' . $e->getMessage(), 500);
		}
	}
}

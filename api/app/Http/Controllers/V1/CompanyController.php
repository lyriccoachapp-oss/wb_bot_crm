<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Получить список компаний
     */
    public function index()
    {
        $companies = Company::orderBy('id', 'asc')->get();
        return response()->json([
            'success' => true,
            'data' => $companies
        ]);
    }

    /**
     * Создать компанию
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:50|unique:companies,slug'
        ]);

        $company = Company::create($request->only(['name', 'slug']));

        return response()->json([
            'success' => true,
            'data' => $company
        ], 201);
    }

    /**
     * Обновить компанию
     */
    public function update(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:50|unique:companies,slug,' . $id
        ]);

        $company->update($request->only(['name', 'slug']));

        return response()->json([
            'success' => true,
            'data' => $company
        ]);
    }

    /**
     * Удалить компанию
     */
    public function destroy($id)
    {
        $company = Company::findOrFail($id);
        $company->delete();

        return response()->json([
            'success' => true,
            'message' => 'Компании удалена'
        ]);
    }
}

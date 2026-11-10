<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $query = Category::query();
        if ($request->filled('search')) {
            $term = '%'.$data['search'].'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $term)->orWhere('description', 'like', $term));
        }

        return Inertia::render('menu/categories', ['categories' => $query->latest('id')->get(), 'filters' => $data]);
    }

    private function categoryData(Request $request, ?int $id = null): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('categories')->ignore($id)], 'description' => ['nullable', 'string', 'max:1000']]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->categoryData($request);
        DB::transaction(function () use ($data) {
            $category = Category::create($data);
            AuditLog::record('category.created', 'category', $category->id);
        });

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->categoryData($request, $category->id);
        DB::transaction(function () use ($category, $data) {
            $category->update($data);
            AuditLog::record('category.updated', 'category', $category->id);
        });

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        DB::transaction(function () use ($category) {
            $record = Category::whereKey($category->id)->lockForUpdate()->firstOrFail();
            if ($record->contacts()->exists()) {
                throw ValidationException::withMessages(['delete' => 'Reassign or remove this category\'s contacts before deleting it.']);
            }
            AuditLog::record('category.deleted', 'category', $record->id);
            $record->delete();
        });

        return back()->with('success', 'Category deleted.');
    }
}

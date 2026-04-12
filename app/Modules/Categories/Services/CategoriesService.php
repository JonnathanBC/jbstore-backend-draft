<?php

namespace App\Modules\Categories\Services;

use App\Modules\Categories\Models\Category;
use App\Modules\Categories\Models\Family;
use App\Modules\Categories\Models\Subcategory;
use Illuminate\Database\Eloquent\Collection;

class CategoriesService
{
    public function getAllFamilies(): Collection
    {
        return Family::with('categories.subcategories')->get();
    }

    public function getFamilyById(int $id): ?Family
    {
        return Family::with('categories.subcategories')->find($id);
    }

    public function createFamily(array $data): Family
    {
        return Family::create($data);
    }

    public function updateFamily(Family $family, array $data): Family
    {
        $family->update($data);
        return $family->fresh();
    }

    public function deleteFamily(Family $family): bool
    {
        return $family->delete();
    }

    public function getAllCategories(): Collection
    {
        return Category::with(['family', 'subcategories'])->get();
    }

    public function getCategoryById(int $id): ?Category
    {
        return Category::with(['family', 'subcategories'])->find($id);
    }

    public function createCategory(array $data): Category
    {
        return Category::create($data);
    }

    public function updateCategory(Category $category, array $data): Category
    {
        $category->update($data);
        return $category->fresh();
    }

    public function deleteCategory(Category $category): bool
    {
        return $category->delete();
    }

    public function getAllSubcategories(): Collection
    {
        return Subcategory::with('category')->get();
    }

    public function getSubcategoryById(int $id): ?Subcategory
    {
        return Subcategory::with('category')->find($id);
    }

    public function createSubcategory(array $data): Subcategory
    {
        return Subcategory::create($data);
    }

    public function updateSubcategory(Subcategory $subcategory, array $data): Subcategory
    {
        $subcategory->update($data);
        return $subcategory->fresh();
    }

    public function deleteSubcategory(Subcategory $subcategory): bool
    {
        return $subcategory->delete();
    }
}

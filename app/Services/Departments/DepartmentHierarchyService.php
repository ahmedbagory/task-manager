<?php

namespace App\Services\Departments;

use App\Models\Department;

class DepartmentHierarchyService
{
    /**
     * @return array<int, string>
     */
    public function topLevelOptions(): array
    {
        return Department::query()
            ->active()
            ->topLevel()
            ->orderedHierarchy()
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @param  array<int, int|string>|null  $parentIds
     * @return array<string, array<int, string>>
     */
    public function childOptionsGroupedByParent(?array $parentIds = null): array
    {
        $query = Department::query()
            ->with('parent')
            ->active()
            ->childUnits()
            ->whereHas('parent', fn ($parentQuery) => $parentQuery->where('is_active', true))
            ->orderedHierarchy();

        $parentIds = $this->normalizeIds($parentIds ?? []);

        if ($parentIds !== []) {
            $query->whereIn('parent_id', $parentIds);
        }

        return $query
            ->get()
            ->groupBy(fn (Department $department) => $department->parent?->name ?? 'بدون قسم رئيسي')
            ->map(fn ($departments) => $departments
                ->mapWithKeys(fn (Department $department) => [$department->id => $department->name])
                ->all())
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function hierarchyOptions(bool $childrenOnly = false): array
    {
        $query = Department::query()
            ->with('parent')
            ->active()
            ->orderedHierarchy();

        if ($childrenOnly) {
            $query->childUnits();
        }

        return $query
            ->get()
            ->mapWithKeys(fn (Department $department) => [$department->id => $department->hierarchy_name])
            ->all();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function employeeDepartmentOptions(): array
    {
        return $this->childOptionsGroupedByParent();
    }

    /**
     * @param  array<int, int|string>  $values
     * @return array<int, int>
     */
    private function normalizeIds(array $values): array
    {
        return collect($values)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
    }
}

<?php

namespace App\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['name', 'code', 'description', 'is_active', 'parent_id'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'parent_id' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function activeChildren(): HasMany
    {
        return $this->children()->where('is_active', true)->orderBy('code')->orderBy('name');
    }

    public function taskCategories(): HasMany
    {
        return $this->hasMany(TaskCategory::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function assignmentTargets(): MorphMany
    {
        return $this->morphMany(TaskAssignmentTarget::class, 'target');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeChildUnits(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id');
    }

    public function scopeOrderedHierarchy(Builder $query): Builder
    {
        return $query->orderBy('code')->orderBy('name');
    }

    public function getHierarchyNameAttribute(): string
    {
        return $this->parent?->name
            ? "{$this->parent->name} / {$this->name}"
            : $this->name;
    }

    public function getLevelLabelAttribute(): string
    {
        return $this->parent_id ? 'وحدة' : 'قسم رئيسي';
    }
}

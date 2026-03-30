<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filter Builder Trait
 * 
 * 提供查询条件构建功能
 */
trait FilterBuilder
{
    /**
     * 构建查询条件
     *
     * @param Builder $query
     * @param Request $request
     * @param array $filters
     * @return Builder
     */
    public function applyFilters(Builder $query, Request $request, array $filters): Builder
    {
        foreach ($filters as $filter => $callback) {
            $value = $request->input($filter);
            
            if ($value !== null && $value !== '') {
                if (is_callable($callback)) {
                    $callback($query, $value);
                } elseif (is_string($callback)) {
                    // 简单的等于条件
                    $query->where($callback, $value);
                }
            }
        }

        return $query;
    }

    /**
     * 应用排序
     *
     * @param Builder $query
     * @param Request $request
     * @param string $defaultSort
     * @param string $defaultOrder
     * @return Builder
     */
    public function applySort(Builder $query, Request $request, string $defaultSort = 'id', string $defaultOrder = 'desc'): Builder
    {
        $sort = $request->input('sort', $defaultSort);
        $order = $request->input('order', $defaultOrder);

        // 验证排序字段是否在允许列表中
        if (property_exists($this, 'allowedSorts') && !in_array($sort, $this->allowedSorts)) {
            $sort = $defaultSort;
        }

        // 验证排序方向
        $order = in_array(strtolower($order), ['asc', 'desc']) ? $order : $defaultOrder;

        return $query->orderBy($sort, $order);
    }

    /**
     * 应用搜索
     *
     * @param Builder $query
     * @param Request $request
     * @param array $searchFields
     * @return Builder
     */
    public function applySearch(Builder $query, Request $request, array $searchFields): Builder
    {
        $keyword = $request->input('keyword');
        
        if ($keyword) {
            $query->where(function ($q) use ($keyword, $searchFields) {
                foreach ($searchFields as $field) {
                    $q->orWhere($field, 'like', "%{$keyword}%");
                }
            });
        }

        return $query;
    }

    /**
     * 应用日期范围筛选
     *
     * @param Builder $query
     * @param Request $request
     * @param string $field
     * @param string $startParam
     * @param string $endParam
     * @return Builder
     */
    public function applyDateRange(Builder $query, Request $request, string $field, string $startParam = 'start_date', string $endParam = 'end_date'): Builder
    {
        $startDate = $request->input($startParam);
        $endDate = $request->input($endParam);

        if ($startDate) {
            $query->where($field, '>=', $startDate);
        }

        if ($endDate) {
            $query->where($field, '<=', $endDate);
        }

        return $query;
    }

    /**
     * 应用状态筛选
     *
     * @param Builder $query
     * @param Request $request
     * @param string $field
     * @param string $param
     * @return Builder
     */
    public function applyStatus(Builder $query, Request $request, string $field = 'status', string $param = 'status'): Builder
    {
        $status = $request->input($param);

        if ($status !== null && $status !== '') {
            $query->where($field, $status);
        }

        return $query;
    }

    /**
     * 应用租户筛选
     *
     * @param Builder $query
     * @param int|null $tenantId
     * @return Builder
     */
    public function applyTenant(Builder $query, ?int $tenantId = null): Builder
    {
        if ($tenantId === null) {
            $tenantId = config('saas.current_tenant_id');
        }

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }

    /**
     * 应用软删除筛选
     *
     * @param Builder $query
     * @param Request $request
     * @return Builder
     */
    public function applyTrashed(Builder $query, Request $request): Builder
    {
        $withTrashed = $request->input('with_trashed', false);
        $onlyTrashed = $request->input('only_trashed', false);

        if ($onlyTrashed) {
            $query->onlyTrashed();
        } elseif ($withTrashed) {
            $query->withTrashed();
        }

        return $query;
    }
}

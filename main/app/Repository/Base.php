<?php

declare(strict_types=1);

namespace App\Repository;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
abstract class Base
{
    // ****************************************************************
    // ************************* Фабрика ******************************
    // ****************************************************************

    /**
     * Новая (несохранённая) сущность.
     *
     * @return TModel
     */
    public function new() : Model
    {
        return $this->query()->make();
    }

    // ****************************************************************
    // ************************* Инициализация ************************
    // ****************************************************************

    /**
     * Инициализация репозитория.
     *
     * @return class-string<TModel>
     */
    abstract protected function init() : string;

    // ****************************************************************
    // ************************* Билдер *******************************
    // ****************************************************************

    /**
     * Получение Builder объекта.
     *
     * @return Builder<TModel>
     */
    protected function query() : Builder
    {
        /** @var Builder<TModel> $builder */
        $builder = $this->init()::query();

        return $builder;
    }

    /**
     * Получить запись по первичному ключу.
     *
     * @param  array<int, string>  $columns
     * @return TModel|null
     */
    public function find(mixed $id, array $columns = ['*']) : ?Model
    {
        if (empty($columns)) {
            $columns = ['*'];
        }

        return $this->query()->find($id, $columns);
    }

    /**
     * Массовый сдвиг updated_at — один UPDATE на всю пачку.
     *
     * @param  array<int, int>  $ids
     */
    public function touchAll(array $ids) : void
    {
        if ($ids === []) {
            return;
        }

        $this->query()->whereKey($ids)->update(['updated_at' => Carbon::now()]);
    }
}

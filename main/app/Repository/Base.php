<?php

declare(strict_types=1);

namespace App\Repository;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
     * Получение среза данных.
     *
     * @return Builder<TModel>
     */
    public function getBuilderDataSlice(int $limit, int $offset) : Builder
    {
        return $this->query()->limit($limit)->offset($offset);
    }

    /**
     * Получить все записи.
     *
     * @param  array<int, string>  $columns
     * @return Collection<int, TModel>
     */
    public function getAll(array $columns = ['*']) : Collection
    {
        if (empty($columns)) {
            $columns = ['*'];
        }

        /** @var Collection<int, TModel> $rows */
        $rows = $this->query()->get($columns);

        return $rows;
    }

    /**
     * Получить все записи по условию.
     *
     * @param  array<string, mixed>  $where
     * @param  array<int, string>  $columns
     * @return Collection<int, TModel>
     */
    public function getAllBy(array $where, array $columns = ['*']) : Collection
    {
        if (empty($columns)) {
            $columns = ['*'];
        }

        /** @var Collection<int, TModel> $rows */
        $rows = $this->query()->where($where)->get($columns);

        return $rows;
    }

    /**
     * Получить записи по условию.
     *
     * @param  array<int, mixed>  $in
     * @param  array<int, string>  $columns
     * @return Collection<int, TModel>
     */
    public function getWhereIn(
        string $column,
        array $in,
        array $columns = ['*']
    ) : Collection {
        if (empty($columns)) {
            $columns = ['*'];
        }

        /** @var Collection<int, TModel> $rows */
        $rows = $this->query()->whereIn($column, $in)->get($columns);

        return $rows;
    }

    /**
     * Получить запись по первичному ключу.
     * Если ничего не будет найдено, то будет выброшено исключение.
     *
     * @param  array<int, string>  $columns
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function get(mixed $id, array $columns = ['*']) : Model
    {
        if (empty($columns)) {
            $columns = ['*'];
        }

        try {
            return $this->query()->findOrFail($id, $columns);
        } catch (ModelNotFoundException) {
            throw new ModelNotFoundException(
                $this->getRepositoryName()." - id $id not found"
            );
        }
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
     * Поиск записи по условиям.
     *
     * @param  array<string, mixed>  $where
     * @param  array<int, string>  $columns
     * @return TModel|null
     */
    public function findBy(
        array $where,
        array $columns = ['*'],
        string $where_boolean = 'and'
    ) : ?Model {
        if (empty($columns)) {
            $columns = ['*'];
        }

        return $this->query()->where($where, null, null, $where_boolean)->first($columns);
    }

    /**
     * Получить количество записей в таблице.
     */
    public function count() : int
    {
        return $this->query()->count();
    }

    /**
     * Получить количество записей по условию.
     *
     * @param  array<string, mixed>  $where
     */
    public function countBy(array $where) : int
    {
        return $this->query()->where($where)->count();
    }

    // ****************************************************************
    // ************************** Support *****************************
    // ****************************************************************

    /**
     * Получить название репозитория.
     */
    protected function getRepositoryName() : string
    {
        return class_basename(get_class($this));
    }
}

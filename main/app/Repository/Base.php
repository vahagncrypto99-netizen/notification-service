<?php

declare(strict_types=1);

namespace App\Repository;

use Illuminate\Database\Eloquent\Builder;
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
     * Получить количество записей в таблице.
     */
    public function count() : int
    {
        return $this->query()->count();
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

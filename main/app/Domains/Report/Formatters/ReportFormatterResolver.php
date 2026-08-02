<?php

declare(strict_types=1);

namespace App\Domains\Report\Formatters;

use App\Domains\Report\Exceptions\ReportFormatterNotConfiguredException;
use Illuminate\Contracts\Container\Container;

class ReportFormatterResolver
{
    /**
     * ReportFormatterResolver constructor.
     *
     * @param  array<string, class-string>  $formatters  карта «формат → стратегия»
     */
    public function __construct(
        protected Container $container,
        protected string $format,
        protected array $formatters,
    ) {
        //
    }

    /**
     * Получение стратегии форматирования для настроенного типа отчёта.
     *
     * @throws ReportFormatterNotConfiguredException
     */
    public function resolve() : ReportFormatterInterface
    {
        $formatter_class = $this->formatters[$this->format] ?? null;

        if ($formatter_class === null || ! is_subclass_of($formatter_class, ReportFormatterInterface::class)) {
            throw new ReportFormatterNotConfiguredException($this->format);
        }

        return $this->container->make($formatter_class);
    }
}

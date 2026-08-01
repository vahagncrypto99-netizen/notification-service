<?php

declare(strict_types=1);

use App\Base\Notification\Exceptions\ReportFormatterNotConfiguredException;
use App\Base\Notification\Reports\CsvReportFormatter;
use App\Base\Notification\Reports\ReportFormatterResolver;

it('резолвит csv-стратегию из конфига', function () : void {
    expect(
        app(ReportFormatterResolver::class)->resolve()
    )->toBeInstanceOf(CsvReportFormatter::class);
});

it('бросает исключение для формата без стратегии', function () : void {
    config(['notification.reports.format' => 'xlsx']);

    app(ReportFormatterResolver::class)->resolve();
})->throws(ReportFormatterNotConfiguredException::class);

it('бросает исключение для класса, не реализующего контракт', function () : void {
    config(['notification.reports.formatters' => ['csv' => stdClass::class]]);

    app(ReportFormatterResolver::class)->resolve();
})->throws(ReportFormatterNotConfiguredException::class);

it('csv-стратегия собирает контент и расширение', function () : void {
    $formatter = new CsvReportFormatter;

    expect($formatter->format([
        ['channel' => 'email', 'total' => 3, 'failed' => 1],
    ]))->toBe("channel,total,failed\nemail,3,1\n")->and(
        $formatter->extension()
    )->toBe('csv');
});

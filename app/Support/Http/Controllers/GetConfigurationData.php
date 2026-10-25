<?php

/**
 * GetConfigurationData.php
 * Copyright (c) 2019 james@firefly-iii.org
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace FireflyIII\Support\Http\Controllers;

use Carbon\Carbon;
use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Support\Facades\AppConfiguration;
use FireflyIII\Support\Facades\Navigation;
use Illuminate\Support\Facades\Log;

/**
 * Trait GetConfigurationData
 */
trait GetConfigurationData
{
    /**
     * Some common combinations.
     */
    protected function errorReporting(int $value): string // get configuration
    {
        $array = [
            -1                                                             => 'ALL errors',
            E_ALL & ~E_NOTICE & ~E_DEPRECATED                              => 'E_ALL & ~E_NOTICE & ~E_DEPRECATED',
            E_ALL                                                          => 'E_ALL',
            E_ALL & ~E_DEPRECATED                                          => 'E_ALL & ~E_DEPRECATED ',
            E_ALL & ~E_NOTICE                                              => 'E_ALL & ~E_NOTICE',
            E_COMPILE_ERROR | E_RECOVERABLE_ERROR | E_ERROR | E_CORE_ERROR => 'E_COMPILE_ERROR|E_RECOVERABLE_ERROR|E_ERROR|E_CORE_ERROR',
        ];

        return $array[$value] ?? (string) $value;
    }

    /**
     * Get the basic steps from config.
     */
    protected function getBasicSteps(string $route): array // get config values
    {
        $routeKey = str_replace('.', '_', $route);
        $elements = config(sprintf('intro.%s', $routeKey));
        $steps    = [];
        if (is_array($elements) && count($elements) > 0) {
            foreach ($elements as $key => $options) {
                $currentStep         = $options;

                // get the text:
                $currentStep['text'] = (string) trans('intro.'.$route.'_'.$key);

                // save in array:
                $steps[]             = $currentStep;
            }
        }
        Log::debug(sprintf('Total basic steps for %s is %d', $routeKey, count($steps)));

        return $steps;
    }

    /**
     * Get config for date range.
     *
     * @throws FireflyException
     */
    protected function getDateRangeConfig(): array // get configuration + get preferences.
    {
        $viewRange      = Navigation::getViewRange(false);

        Log::debug(sprintf('dateRange: the view range is "%s"', $viewRange));

        /** @var Carbon $start */
        $start          = session('start');

        /** @var Carbon $end */
        $end            = session('end');

        /** @var Carbon $first */
        $first          = session('first');
        $title          = sprintf('%s - %s', $start->isoFormat($this->monthAndDayFormat), $end->isoFormat($this->monthAndDayFormat));
        $today          = today(config('app.timezone'));
        $ranges         = [];
        Log::debug(sprintf('dateRange: the date range in the session is"%s" - "%s"', $start->format('Y-m-d'), $end->format('Y-m-d')));

        // fixed presets instead of ranges relative to the current view range:
        // current month, previous month, current year and the two years before it.
        foreach ([['1M', 0], ['1M', 1], ['1Y', 0], ['1Y', 1], ['1Y', 2]] as [$period, $back]) {
            $date = $today->clone();
            for ($i = 0; $i < $back; ++$i) {
                $date = Navigation::subtractPeriod($date, $period);
            }
            $rangeStart     = Navigation::startOfPeriod($date, $period);
            $rangeEnd       = Navigation::endOfPeriod($rangeStart, $period);
            $index          = Navigation::periodShow($rangeStart, $period);
            $ranges[$index] = [$rangeStart, $rangeEnd];
        }

        // last 30, 90, 180 and 365 days:
        foreach ([30 => 'pref_last30', 90 => 'pref_last90', 180 => 'last_180_days', 365 => 'pref_last365'] as $days => $key) {
            $index          = (string) trans(sprintf('firefly.%s', $key));
            $ranges[$index] = [today(config('app.timezone'))->subDays($days), new Carbon()];
        }

        // month to date:
        $monthBegin     = today(config('app.timezone'))->startOfMonth();
        $index          = (string) trans('firefly.month_to_date');
        $ranges[$index] = [$monthBegin, new Carbon()];

        // year to date:
        $yearBegin      = today(config('app.timezone'))->startOfYear();
        $index          = (string) trans('firefly.year_to_date');
        $ranges[$index] = [$yearBegin, new Carbon()];

        // everything
        $index          = (string) trans('firefly.everything');
        $ranges[$index] = [$first, new Carbon()];

        return [
            'title'         => $title,
            'configuration' => [
                'apply'       => (string) trans('firefly.apply'),
                'cancel'      => (string) trans('firefly.cancel'),
                'from'        => (string) trans('firefly.from'),
                'to'          => (string) trans('firefly.to'),
                'customRange' => (string) trans('firefly.customRange'),
                'start'       => $start->format('Y-m-d'),
                'end'         => $end->format('Y-m-d'),
                'ranges'      => $ranges,
            ],
        ];
    }

    /**
     * Get specific info for special routes.
     */
    protected function getSpecificSteps(string $route, string $specificPage): array
    { // get config values
        $steps    = [];
        $routeKey = '';

        // user is on page with specific instructions:
        if ('' !== $specificPage) {
            $routeKey = str_replace('.', '_', $route);
            $elements = config(sprintf('intro.%s', $routeKey.'_'.$specificPage));
            if (is_array($elements) && count($elements) > 0) {
                foreach ($elements as $key => $options) {
                    $currentStep         = $options;

                    // get the text:
                    $currentStep['text'] = (string) trans('intro.'.$route.'_'.$specificPage.'_'.$key);

                    // save in array:
                    $steps[]             = $currentStep;
                }
            }
        }
        Log::debug(sprintf('Total specific steps for route "%s" and page "%s" (routeKey is "%s") is %d', $route, $specificPage, $routeKey, count($steps)));

        return $steps;
    }

    protected function verifyRecurringCronJob(): void
    {
        $config   = AppConfiguration::get(sprintf('last_rt_job_%d', auth()->user()->id), 0);
        $lastTime = (int) $config?->data;
        $now      = Carbon::now()->getTimestamp();
        Log::debug(sprintf('verifyRecurringCronJob: last time is %d ("%s"), now is %d', $lastTime, $config?->data, $now));
        if (0 === $lastTime) {
            request()->session()->flash('info', trans('firefly.recurring_never_cron'));

            return;
        }
        if (($now - $lastTime) > 129_600) {
            request()->session()->flash('warning', trans('firefly.recurring_cron_long_ago'));
        }
    }
}

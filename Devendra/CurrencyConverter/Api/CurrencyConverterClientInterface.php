<?php
/**
 * @author Devendra Gupta
 * @package Devendra_CurrencyConverter
 */
declare(strict_types=1);

namespace Devendra\CurrencyConverter\Api;

interface CurrencyConverterClientInterface
{
    /**
     * Get list of currencies
     *
     * @return array
     */
    public function getCurrencies(): array;

    /**
     * Get latest exchange rate for given currency pair
     *
     * @param string $from
     * @param string $to
     * @return array
     */
    public function getLatestRate(
        string $from,
        string $to
    ): array;

    /**
     * Get historical exchange rates for given currency pair and date range
     *
     * @param string $from
     * @param string $to
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getHistoricalRates(
        string $from,
        string $to,
        string $startDate,
        string $endDate
    ): array;

}
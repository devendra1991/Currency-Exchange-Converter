<?php
/**
 * @author Devendra Gupta
 * @package Devendra_CurrencyConverter
 */
declare(strict_types=1);

namespace Devendra\CurrencyConverter\ViewModel;

use Devendra\CurrencyConverter\Api\CurrencyConverterClientInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Data implements ArgumentInterface
{
    /**
     * @param CurrencyConverterClientInterface $client
     */
    public function __construct(
        private readonly CurrencyConverterClientInterface $client
    ) {
    }

    /**
     * Get the list of currencies
     *
     * @return array
     */
    public function getCurrencies(): array
    {
        return $this->client->getCurrencies();
    }
}
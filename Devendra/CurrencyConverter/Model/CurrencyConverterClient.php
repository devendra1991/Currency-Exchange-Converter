<?php
/**
 * @author Devendra Gupta
 * @package Devendra_CurrencyConverter
 */
declare(strict_types=1);

namespace Devendra\CurrencyConverter\Model;

use Devendra\CurrencyConverter\Api\CurrencyConverterClientInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;

class CurrencyConverterClient implements CurrencyConverterClientInterface
{
    private const API_URL = 'https://api.frankfurter.dev/v2';

    /**
     * @param Curl $curl
     * @param Json $json
     */
    public function __construct(
        private readonly Curl $curl,
        private readonly Json $json
    ) {
    }

    /**
     * Make a request to the Frankfurter API
     *
     * @param string $endpoint
     * @return array
     * @throws \RuntimeException
     */
    private function request(string $endpoint): array
    {
        $url = self::API_URL . $endpoint;

        try {
            $this->curl->setTimeout(10);
            $this->curl->addHeader('Accept', 'application/json');
            $this->curl->get($url);

            if ($this->curl->getStatus() !== 200) {
                throw new \RuntimeException(
                    'Unable to retrieve exchange rate data.'
                );
            }

            $data = $this->json->unserialize(
                $this->curl->getBody()
            );

            if (!is_array($data)) {
                throw new \RuntimeException('Frankfurter API returned invalid JSON.');
            }

            return $data;
        } catch (\InvalidArgumentException $exception) {
            throw new \RuntimeException(
                'Unable to decode the Frankfurter API response.',
                0,
                $exception
            );
        }
    }

    /**
     * Get list of currencies
     *
     * @return array
     */
    public function getCurrencies(): array
    {
        return $this->request('/currencies');
    }

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
    ): array {
        return $this->request(
            '/rate/' .
            rawurlencode($from) . '/' .
            rawurlencode($to)
        );
    }

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
    ): array {
        $query = http_build_query([
            'base' => $from,
            'quotes' => $to,
            'from' => $startDate,
            'to' => $endDate
        ]);

        return $this->request('/rates?' . $query);
    }
}
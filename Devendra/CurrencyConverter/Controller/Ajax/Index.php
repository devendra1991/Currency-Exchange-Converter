<?php
/**
 * @author Devendra Gupta
 * @package Devendra_CurrencyConverter
 */
declare(strict_types=1);

namespace Devendra\CurrencyConverter\Controller\Ajax;

use Devendra\CurrencyConverter\Api\CurrencyConverterClientInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly CurrencyConverterClientInterface $client,
        private readonly JsonFactory $resultJsonFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        try {
            $currencies = $this->client->getCurrencies();

            return $result->setData([
                'success' => true,
                'data' => $currencies
            ]);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Currency API error: ' . $e->getMessage()
            );

            return $result
                ->setHttpResponseCode(503)
                ->setData([
                    'success' => false,
                    'message' => 'Unable to load currencies.'
                ]);
        }
    }
}

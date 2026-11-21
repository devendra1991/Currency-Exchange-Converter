<?php
/**
 * @author Devendra Gupta
 * @package Devendra_CurrencyConverter
 */
declare(strict_types=1);

namespace Devendra\CurrencyConverter\Controller\Ajax;

use Devendra\CurrencyConverter\Api\CurrencyConverterClientInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

class Rate implements HttpGetActionInterface
{
    /**
     * @param CurrencyConverterClientInterface $client
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CurrencyConverterClientInterface $client,
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Return the Latest Rate
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        try {
            $from = strtoupper(
                trim((string) $this->request->getParam('from'))
            );

            $to = strtoupper(
                trim((string) $this->request->getParam('to'))
            );

            if (
                !preg_match('/^[A-Z]{3}$/', $from) ||
                !preg_match('/^[A-Z]{3}$/', $to)
            ) {
                return $result
                    ->setHttpResponseCode(400)
                    ->setData([
                        'success' => false,
                        'message' => 'Invalid currency code.'
                    ]);
            }

            if ($from === $to) {
                return $result->setData([
                    'success' => true,
                    'data' => [
                        'base' => $from,
                        'quote' => $to,
                        'rate' => 1,
                        'date' => date('Y-m-d')
                    ]
                ]);
            }

            $rate = $this->client->getLatestRate(
                $from,
                $to
            );

            return $result->setData([
                'success' => true,
                'data' => $rate
            ]);

        } catch (\Throwable $e) {
            $this->logger->error(
                'Exchange rate API error: ' . $e->getMessage()
            );

            return $result
                ->setHttpResponseCode(503)
                ->setData([
                    'success' => false,
                    'message' => 'Unable to retrieve exchange rate.'
                ]);
        }
    }
}

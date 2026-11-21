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

class History implements HttpGetActionInterface
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
     * Return the Historical Rate
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

            $startDate = trim(
                (string) $this->request->getParam('start_date')
            );

            $endDate = trim(
                (string) $this->request->getParam('end_date')
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

            $start = \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $startDate
            );

            $end = \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $endDate
            );

            $isValidStart = $start !== false
                && $start->format('Y-m-d') === $startDate;

            $isValidEnd = $end !== false
                && $end->format('Y-m-d') === $endDate;

            if (!$isValidStart || !$isValidEnd) {
                return $result
                    ->setHttpResponseCode(400)
                    ->setData([
                        'success' => false,
                        'message' => 'Invalid date format.'
                    ]);
            }

            if ($start > $end) {
                return $result
                    ->setHttpResponseCode(400)
                    ->setData([
                        'success' => false,
                        'message' => 'Start date cannot be after end date.'
                    ]);
            }

            if ($start > new \DateTimeImmutable('today')) {
                return $result
                    ->setHttpResponseCode(400)
                    ->setData([
                        'success' => false,
                        'message' => 'Start date cannot be in the future.'
                    ]);
            }

            if ($from === $to) {
                return $result->setData([
                    'success' => true,
                    'data' => []
                ]);
            }

            $history = $this->client->getHistoricalRates(
                $from,
                $to,
                $startDate,
                $endDate
            );

            return $result->setData([
                'success' => true,
                'data' => $history
            ]);

        } catch (\Throwable $e) {
            $this->logger->error(
                'Historical rate API error: ' . $e->getMessage()
            );

            return $result
                ->setHttpResponseCode(503)
                ->setData([
                    'success' => false,
                    'message' => 'Unable to retrieve historical rates.'
                ]);
        }
    }
}

<?php
/**
 * @author Devendra Gupta
 * @package Devendra_CurrencyConverter
 */
declare(strict_types=1);

namespace Devendra\CurrencyConverter\Test\Unit\Controller\Ajax;

use Devendra\CurrencyConverter\Api\CurrencyConverterClientInterface;
use Devendra\CurrencyConverter\Controller\Ajax\Index;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class IndexTest extends TestCase
{
    private CurrencyConverterClientInterface&MockObject $client;
    private JsonFactory&MockObject $resultJsonFactory;
    private LoggerInterface&MockObject $logger;
    private Index $controller;

    protected function setUp(): void
    {
        $this->client = $this->createMock(CurrencyConverterClientInterface::class);
        $this->resultJsonFactory = $this->createMock(JsonFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->controller = new Index(
            $this->client,
            $this->resultJsonFactory,
            $this->logger
        );
    }

    public function testExecuteReturnsCurrenciesWhenApiSucceeds(): void
    {
        $currencies = [
            'USD' => 'US Dollar',
            'EUR' => 'Euro'
        ];
        $resultJson = $this->createMock(Json::class);

        $this->client
            ->expects($this->once())
            ->method('getCurrencies')
            ->willReturn($currencies);

        $this->resultJsonFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($resultJson);

        $resultJson
            ->expects($this->once())
            ->method('setData')
            ->with([
                'success' => true,
                'data' => $currencies
            ])
            ->willReturnSelf();

        $this->logger
            ->expects($this->never())
            ->method('error');

        $this->assertSame($resultJson, $this->controller->execute());
    }

    public function testExecuteReturnsServiceUnavailableWhenApiFails(): void
    {
        $exception = new \RuntimeException('API unavailable');
        $resultJson = $this->createMock(Json::class);

        $this->client
            ->expects($this->once())
            ->method('getCurrencies')
            ->willThrowException($exception);

        $this->resultJsonFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($resultJson);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Currency API error: API unavailable');

        $resultJson
            ->expects($this->once())
            ->method('setHttpResponseCode')
            ->with(503)
            ->willReturnSelf();

        $resultJson
            ->expects($this->once())
            ->method('setData')
            ->with([
                'success' => false,
                'message' => 'Unable to load currencies.'
            ])
            ->willReturnSelf();

        $this->assertSame($resultJson, $this->controller->execute());
    }
}

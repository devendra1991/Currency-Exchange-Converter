<?php
/**
 * @author Devendra Gupta
 * @package Devendra_CurrencyConverter
 */
declare(strict_types=1);

namespace Devendra\CurrencyConverter\Test\Unit\Controller\Ajax;

use Devendra\CurrencyConverter\Api\CurrencyConverterClientInterface;
use Devendra\CurrencyConverter\Controller\Ajax\Rate;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RateTest extends TestCase
{
    private CurrencyConverterClientInterface&MockObject $client;
    private RequestInterface&MockObject $request;
    private JsonFactory&MockObject $resultJsonFactory;
    private LoggerInterface&MockObject $logger;
    private Rate $controller;

    protected function setUp(): void
    {
        $this->client = $this->createMock(CurrencyConverterClientInterface::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->resultJsonFactory = $this->createMock(JsonFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->controller = new Rate(
            $this->client,
            $this->request,
            $this->resultJsonFactory,
            $this->logger
        );
    }

    public function testExecuteReturnsLatestRateForValidCurrencies(): void
    {
        $rateData = ['base' => 'USD', 'quote' => 'EUR', 'rate' => 0.85, 'date' => '2026-11-21'];
        $resultJson = $this->createMock(Json::class);

        $this->request->expects($this->exactly(2))->method('getParam')
            ->willReturnMap([
                ['from', null, 'usd'],
                ['to', null, 'eur'],
            ]);

        $this->client->expects($this->once())->method('getLatestRate')
            ->with('USD', 'EUR')
            ->willReturn($rateData);

        $this->resultJsonFactory->expects($this->once())->method('create')->willReturn($resultJson);
        $resultJson->expects($this->once())->method('setData')->with([
            'success' => true,
            'data' => $rateData,
        ])->willReturnSelf();
        $this->logger->expects($this->never())->method('error');

        $this->assertSame($resultJson, $this->controller->execute());
    }

    public function testExecuteRejectsInvalidFromCurrency(): void
    {
        $resultJson = $this->createMock(Json::class);

        $this->request->expects($this->exactly(2))->method('getParam')
            ->willReturnMap([
                ['from', null, 'US'],
                ['to', null, 'EUR'],
            ]);

        $this->resultJsonFactory->expects($this->once())->method('create')->willReturn($resultJson);
        $resultJson->expects($this->once())->method('setHttpResponseCode')->with(400)->willReturnSelf();
        $resultJson->expects($this->once())->method('setData')->with([
            'success' => false,
            'message' => 'Invalid currency code.',
        ])->willReturnSelf();
        $this->client->expects($this->never())->method('getLatestRate');

        $this->assertSame($resultJson, $this->controller->execute());
    }

    public function testExecuteRejectsInvalidToCurrency(): void
    {
        $resultJson = $this->createMock(Json::class);

        $this->request->expects($this->exactly(2))->method('getParam')
            ->willReturnMap([
                ['from', null, 'USD'],
                ['to', null, 'invalid'],
            ]);

        $this->resultJsonFactory->expects($this->once())->method('create')->willReturn($resultJson);
        $resultJson->expects($this->once())->method('setHttpResponseCode')->with(400)->willReturnSelf();
        $resultJson->expects($this->once())->method('setData')->with([
            'success' => false,
            'message' => 'Invalid currency code.',
        ])->willReturnSelf();

        $this->assertSame($resultJson, $this->controller->execute());
    }

    public function testExecuteReturnsUnitRateWhenCurrenciesAreTheSame(): void
    {
        $resultJson = $this->createMock(Json::class);

        $this->request->method('getParam')->willReturnMap([
            ['from', null, 'usd'],
            ['to', null, 'USD'],
        ]);

        $this->resultJsonFactory->expects($this->once())->method('create')->willReturn($resultJson);
        $resultJson->expects($this->once())->method('setData')->with([
            'success' => true,
            'data' => [
                'base' => 'USD',
                'quote' => 'USD',
                'rate' => 1,
                'date' => date('Y-m-d'),
            ],
        ])->willReturnSelf();
        $this->client->expects($this->never())->method('getLatestRate');

        $this->assertSame($resultJson, $this->controller->execute());
    }

    public function testExecuteReturnsServiceUnavailableWhenApiFails(): void
    {
        $resultJson = $this->createMock(Json::class);

        $this->request->method('getParam')->willReturnMap([
            ['from', null, 'USD'],
            ['to', null, 'EUR'],
        ]);

        $this->client->expects($this->once())->method('getLatestRate')
            ->with('USD', 'EUR')
            ->willThrowException(new \RuntimeException('API unavailable'));

        $this->resultJsonFactory->expects($this->once())->method('create')->willReturn($resultJson);
        $this->logger->expects($this->once())->method('error')
            ->with('Exchange rate API error: API unavailable');
        $resultJson->expects($this->once())->method('setHttpResponseCode')->with(503)->willReturnSelf();
        $resultJson->expects($this->once())->method('setData')->with([
            'success' => false,
            'message' => 'Unable to retrieve exchange rate.',
        ])->willReturnSelf();

        $this->assertSame($resultJson, $this->controller->execute());
    }
}

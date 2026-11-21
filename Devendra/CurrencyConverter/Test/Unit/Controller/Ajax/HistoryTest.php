<?php

use Devendra\CurrencyConverter\Controller\Ajax\History;
use Devendra\CurrencyConverter\Api\CurrencyConverterClientInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class HistoryTest extends TestCase
{
    private $client;
    private $request;
    private $resultJsonFactory;
    private $logger;
    private $history;

    protected function setUp(): void
    {
        $this->client = $this->createMock(CurrencyConverterClientInterface::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->resultJsonFactory = $this->createMock(JsonFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->history = new History($this->client, $this->request, $this->resultJsonFactory, $this->logger);
    }

    public function testExecuteWithValidData(): void
    {
        // Mocking the request parameters
        $this->request->method('getParam')->willReturnMap([
            ['from', 'USD'],
            ['to', 'EUR'],
            ['startDate', '2022-01-01'],
            ['endDate', '2022-01-31']
        ]);

        // Mocking the JsonFactory
        $resultJson = $this->createMock(Json::class);
        $this->resultJsonFactory->method('create')->willReturn($resultJson);

        // Expecting the logger to not be called
        $this->logger->expects($this->never())->method('error');

        // Execute the method
        $result = $this->history->execute();

        // Assertions
        $this->assertInstanceOf(Json::class, $result);
    }

    public function testExecuteWithInvalidCurrency(): void
    {
        // Mocking the request parameters
        $this->request->method('getParam')->willReturnMap([
            ['from', 'USD'],
            ['to', 'INVALID'],
            ['startDate', '2022-01-01'],
            ['endDate', '2022-01-31']
        ]);

        // Mocking the JsonFactory
        $resultJson = $this->createMock(Json::class);
        $this->resultJsonFactory->method('create')->willReturn($resultJson);

        // Expecting the logger to be called
        $this->logger->expects($this->once())->method('error');

        // Execute the method
        $result = $this->history->execute();

        // Assertions
        $this->assertInstanceOf(Json::class, $result);
    }

    public function testExecuteWithInvalidDates(): void
    {
        // Mocking the request parameters
        $this->request->method('getParam')->willReturnMap([
            ['from', 'USD'],
            ['to', 'EUR'],
            ['startDate', 'invalid-date'],
            ['endDate', '2022-01-31']
        ]);

        // Mocking the JsonFactory
        $resultJson = $this->createMock(Json::class);
        $this->resultJsonFactory->method('create')->willReturn($resultJson);

        // Expecting the logger to be called
        $this->logger->expects($this->once())->method('error');

        // Execute the method
        $result = $this->history->execute();

        // Assertions
        $this->assertInstanceOf(Json::class, $result);
    }
}

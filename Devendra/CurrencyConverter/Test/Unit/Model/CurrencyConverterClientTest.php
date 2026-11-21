<?php
/**
 * @author Devendra Gupta
 * @package Devendra_CurrencyConverter
 */
declare(strict_types=1);

namespace Devendra\CurrencyConverter\Test\Unit\Model;

use Devendra\CurrencyConverter\Model\CurrencyConverterClient;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CurrencyConverterClientTest extends TestCase
{
    private Curl&MockObject $curl;
    private Json&MockObject $json;
    private CurrencyConverterClient $client;

    protected function setUp(): void
    {
        $this->curl = $this->createMock(Curl::class);
        $this->json = $this->createMock(Json::class);
        $this->client = new CurrencyConverterClient($this->curl, $this->json);
    }

    public function testGetCurrencies(): void
    {
        $response = ['USD' => 'US Dollar', 'EUR' => 'Euro'];

        $this->curl->expects($this->once())->method('setTimeout')->with(10);
        $this->curl->expects($this->once())->method('addHeader')->with('Accept', 'application/json');
        $this->curl->expects($this->once())->method('get')
            ->with('https://api.frankfurter.dev/v2/currencies');
        $this->curl->expects($this->once())->method('getStatus')->willReturn(200);
        $this->curl->expects($this->once())->method('getBody')->willReturn('{"USD":"US Dollar","EUR":"Euro"}');
        $this->json->expects($this->once())->method('unserialize')
            ->with('{"USD":"US Dollar","EUR":"Euro"}')
            ->willReturn($response);

        $this->assertSame($response, $this->client->getCurrencies());
    }

    public function testGetLatestRate(): void
    {
        $response = ['base' => 'USD', 'quote' => 'EUR', 'rate' => 0.85];

        $this->curl->expects($this->once())->method('setTimeout')->with(10);
        $this->curl->expects($this->once())->method('addHeader')->with('Accept', 'application/json');
        $this->curl->expects($this->once())->method('get')
            ->with('https://api.frankfurter.dev/v2/rate/USD/EUR');
        $this->curl->expects($this->once())->method('getStatus')->willReturn(200);
        $this->curl->expects($this->once())->method('getBody')->willReturn('{"base":"USD","quote":"EUR","rate":0.85}');
        $this->json->expects($this->once())->method('unserialize')->willReturn($response);

        $this->assertSame($response, $this->client->getLatestRate('USD', 'EUR'));
    }

    public function testGetLatestRateUrlEncodesCurrencyCodes(): void
    {
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('[]');
        $this->json->method('unserialize')->willReturn([]);
        $this->curl->expects($this->once())->method('get')
            ->with('https://api.frankfurter.dev/v2/rate/US%20D/Eu%2Br');

        $this->assertSame([], $this->client->getLatestRate('US D', 'Eu+r'));
    }

    public function testGetHistoricalRates(): void
    {
        $response = ['rates' => ['2026-11-20' => ['EUR' => 0.85]]];

        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('{"rates":{"2026-11-20":{"EUR":0.85}}}');
        $this->json->method('unserialize')->willReturn($response);
        $this->curl->expects($this->once())->method('get')->with(
            'https://api.frankfurter.dev/v2/rates?base=USD&quotes=EUR&from=2026-11-01&to=2026-11-21'
        );

        $this->assertSame(
            $response,
            $this->client->getHistoricalRates('USD', 'EUR', '2026-11-01', '2026-11-21')
        );
    }

    public function testRequestThrowsWhenResponseStatusIsNotSuccessful(): void
    {
        $this->curl->method('getStatus')->willReturn(503);

        $this->expectException('\RuntimeException');
        $this->expectExceptionMessage('Unable to retrieve exchange rate data.');

        $this->client->getCurrencies();
    }

    public function testRequestThrowsWhenDecodedDataIsNotAnArray(): void
    {
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('"invalid"');
        $this->json->method('unserialize')->willReturn('invalid');

        $this->expectException('\RuntimeException');
        $this->expectExceptionMessage('Frankfurter API returned invalid JSON.');

        $this->client->getCurrencies();
    }

    public function testRequestWrapsJsonDecodingException(): void
    {
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('{invalid-json');
        $this->json->method('unserialize')->willThrowException(
            new \InvalidArgumentException('Malformed JSON')
        );

        $this->expectException('\RuntimeException');
        $this->expectExceptionMessage('Unable to decode the Frankfurter API response.');

        $this->client->getCurrencies();
    }
}

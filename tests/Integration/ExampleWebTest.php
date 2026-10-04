<?php

declare(strict_types=1);

namespace StrObj\Tests\Integration;

use PDO;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

/** Exercises the actual example pages, forms and persistence over local HTTP. */
final class ExampleWebTest extends TestCase
{
    /** @var resource|null Server owned by this test class. */
    private static $server;

    /** @var string */
    private static $baseUrl;

    /** @var string */
    private static $serverLog;

    /** @var string Each test starts with a new browser session. */
    private $cookie = '';

    /** Boots a separate PHP server with runtime data isolated from users' demos. */
    public static function setUpBeforeClass(): void
    {
        if (
            !class_exists(PDO::class) || !in_array('sqlite', PDO::getAvailableDrivers(), true)
            || !is_callable('proc_open') || !ini_get('allow_url_fopen')
        ) {
            self::markTestSkipped('Example HTTP tests require pdo_sqlite, proc_open and allow_url_fopen.');
        }

        $listener = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        self::assertNotFalse($listener, 'Cannot reserve a local test port: ' . $errorMessage);
        $address = stream_socket_get_name($listener, false);
        fclose($listener);
        $root = dirname(__DIR__, 2);
        $storage = $root . '/build/examples/http-test-' . bin2hex(random_bytes(6));
        mkdir($storage, 0770, true);
        self::$serverLog = $storage . '/server.log';
        self::$baseUrl = 'http://' . $address;
        $httpBinary = PHP_SAPI === 'phpdbg'
        ? dirname(PHP_BINARY) . '/php' . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '') : PHP_BINARY;
        self::$server = proc_open(
            [
            $httpBinary, '-d', 'session.sid_length=32', '-d', 'session.sid_bits_per_character=4',
            '-S', $address, '-t', $root . '/examples',
            ],
            [0 => ['pipe', 'r'], 1 => ['file', self::$serverLog, 'a'], 2 => ['file', self::$serverLog, 'a']],
            $pipes,
            $root,
            array_merge(getenv(), ['STROBJ_EXAMPLE_STORAGE' => $storage])
        );
        self::assertIsResource(self::$server);
        fclose($pipes[0]);
        $ready = false;

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $connection = @stream_socket_client('tcp://' . $address, $errorCode, $errorMessage, 0.1);

            if ($connection !== false) {
                fclose($connection);
                $ready = true;
                break;
            }

            usleep(30000);
        }

        if (!$ready) {
            proc_terminate(self::$server);
        }

        self::assertTrue($ready, 'Example server failed to start: ' . file_get_contents(self::$serverLog));
    }

    /** Stops only the child server created by this class; logs remain under build/. */
    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
    }

    /** A browser can perform all four CRUD operations and recover from validation. */
    public function testCreateReloadEditRejectAndDelete(): void
    {
        $page = $this->request('GET', '/02-crud/');
        self::assertSame(200, $page['status']);
        self::assertStringContainsString('No products yet.', $page['body']);
        $token = $this->token($page['body']);
        $product = $this->product();
        $created = $this->request('POST', '/02-crud/', ['csrf' => $token, 'action' => 'create', 'product' => $product]);
        self::assertSame(303, $created['status']);
        $listed = $this->request('GET', '/02-crud/');
        self::assertStringContainsString('created. It is now in your list.', $listed['body']);
        $rows = $this->xpath($listed['body']);
        self::assertSame(1, $rows->query('//tr[@data-product-id]')->length);
        $id = $rows->evaluate('string(//tr[@data-product-id]/@data-product-id)');
        self::assertSame('0', $rows->evaluate('string(//tr[@data-product-id]/td[3])'));
        self::assertSame('Inactive', $rows->evaluate('string(//tr[@data-product-id]/td[5]/span)'));
        self::assertSame(
            1,
            $this->xpath($this->request('GET', '/02-crud/')['body'])->query('//tr[@data-product-id]')->length
        );

        $edit = $this->request('GET', '/02-crud/?edit=' . $id);
        self::assertSame(
            'Demo Product',
            $this->xpath($edit['body'])->evaluate('string(//input[@id="product-name"]/@value)')
        );
        self::assertStringContainsString('Save changes', $edit['body']);
        $product['name'] = 'Updated Product';
        $product['pricing']['amount'] = '22.50';
        $product['inventory'] = ['stock' => '5', 'warehouse' => ['city' => 'Boston']];
        $product['publication']['active'] = '1';
        self::assertSame(303, $this->request('POST', '/02-crud/', [
            'csrf' => $token, 'action' => 'update', 'id' => $id, 'product' => $product,
        ])['status']);
        $listed = $this->xpath($this->request('GET', '/02-crud/')['body']);
        self::assertSame('Updated Product', $listed->evaluate('string(//tr[@data-product-id]/td[1]/strong)'));
        self::assertSame('USD 22.50', $listed->evaluate('string(//tr[@data-product-id]/td[2])'));
        self::assertSame('Boston', $listed->evaluate('string(//tr[@data-product-id]/td[4])'));

        $product['pricing']['amount'] = '-1';
        $product['inventory']['warehouse']['city'] = 'Seattle';
        $invalid = $this->request('POST', '/02-crud/', [
            'csrf' => $token, 'action' => 'update', 'id' => $id, 'product' => $product,
        ]);
        self::assertSame(422, $invalid['status']);
        self::assertStringContainsString('Price must be zero or more', $invalid['body']);
        self::assertSame(
            '-1',
            $this->xpath($invalid['body'])->evaluate('string(//input[@id="product-pricing-amount"]/@value)')
        );
        self::assertSame(
            'Seattle',
            $this->xpath($invalid['body'])->evaluate('string(//input[@id="product-inventory-warehouse-city"]/@value)')
        );
        $stored = $this->xpath($this->request('GET', '/02-crud/')['body']);
        self::assertSame('USD 22.50', $stored->evaluate('string(//tr[@data-product-id]/td[2])'));
        self::assertSame('Boston', $stored->evaluate('string(//tr[@data-product-id]/td[4])'));

        $product['pricing']['amount'] = '0';
        $product['inventory']['stock'] = '0';
        $product['publication']['active'] = '0';
        $product['description'] = '';
        self::assertSame(303, $this->request('POST', '/02-crud/', [
            'csrf' => $token, 'action' => 'update', 'id' => $id, 'product' => $product,
        ])['status']);
        $listed = $this->request('GET', '/02-crud/');
        $rows = $this->xpath($listed['body']);
        self::assertSame('USD 0.00', $rows->evaluate('string(//tr[@data-product-id]/td[2])'));
        self::assertSame('0', $rows->evaluate('string(//tr[@data-product-id]/td[3])'));
        self::assertSame('Inactive', $rows->evaluate('string(//tr[@data-product-id]/td[5]/span)'));
        self::assertNull($this->storedRecords($listed['body'])[0]['product']['description']);

        self::assertSame(303, $this->request('POST', '/02-crud/', [
            'csrf' => $token, 'action' => 'delete', 'id' => $id,
        ])['status']);
        self::assertSame(
            0,
            $this->xpath($this->request('GET', '/02-crud/')['body'])->query('//tr[@data-product-id]')->length
        );
        self::assertSame(404, $this->request('GET', '/02-crud/?edit=' . $id)['status']);
    }

    /** Invalid writes, unknown fields and markup cannot alter another browser's list. */
    public function testWriteGuardsEscapingAndSessionIsolation(): void
    {
        $page = $this->request('GET', '/02-crud/');
        $token = $this->token($page['body']);
        $invalid = $this->request('POST', '/02-crud/', [
            'action' => 'create', 'csrf' => 'wrong', 'product' => $this->product(),
        ]);
        self::assertSame(422, $invalid['status']);
        self::assertStringContainsString('This form expired.', $invalid['body']);
        self::assertSame(0, $this->xpath($invalid['body'])->query('//tr[@data-product-id]')->length);
        $missing = $this->request('POST', '/02-crud/', [
            'action' => 'create', 'csrf' => $token, 'product' => ['name' => 'A'],
        ]);
        self::assertSame(422, $missing['status']);
        self::assertStringContainsString('Use a name between 2 and 80 characters.', $missing['body']);
        self::assertSame(0, $this->xpath($missing['body'])->query('//tr[@data-product-id]')->length);

        $product = $this->product();
        $product['name'] = '<script>alert(1)</script>';
        $product['internalCost'] = 999;
        self::assertSame(303, $this->request('POST', '/02-crud/', [
            'action' => 'create', 'csrf' => $token, 'product' => $product,
        ])['status']);
        $listed = $this->request('GET', '/02-crud/');
        self::assertStringNotContainsString('<script>alert(1)</script>', $listed['body']);
        self::assertSame(
            '<script>alert(1)</script>',
            $this->xpath($listed['body'])->evaluate('string(//tr[@data-product-id]/td[1]/strong)')
        );
        self::assertArrayNotHasKey('internalCost', $this->storedRecords($listed['body'])[0]['product']);
        $badId = $this->request('POST', '/02-crud/', ['action' => 'delete', 'csrf' => $token, 'id' => '0']);
        self::assertSame(422, $badId['status']);
        self::assertSame(1, $this->xpath($badId['body'])->query('//tr[@data-product-id]')->length);
        $firstBrowser = $this->cookie;
        $this->cookie = '';
        self::assertSame(
            0,
            $this->xpath($this->request('GET', '/02-crud/')['body'])->query('//tr[@data-product-id]')->length
        );
        $this->cookie = $firstBrowser;
        self::assertSame(
            1,
            $this->xpath($this->request('GET', '/02-crud/')['body'])->query('//tr[@data-product-id]')->length
        );
    }

    /** The overview and table work from links and user-selected view controls. */
    public function testNavigationColumnsAndFiltering(): void
    {
        $home = $this->request('GET', '/');
        self::assertSame(200, $home['status']);
        self::assertStringContainsString('href="01-json-table/"', $home['body']);
        self::assertStringContainsString('href="02-crud/"', $home['body']);
        self::assertSame(200, $this->request('GET', '/assets/examples.css')['status']);
        $table = $this->xpath($this->request('GET', '/01-json-table/')['body']);
        self::assertSame(3, $table->query('//table')->length);
        self::assertSame(7, $table->query('(//table)[2]/thead/tr/th')->length);
        self::assertSame(2, $table->query('(//table)[3]/tbody/tr')->length);
        $query = http_build_query([
            'configured' => 1, 'columns' => ['Phone', 'Latest shipment'],
            'city' => '', 'active' => '0', 'minimumSpend' => 0,
        ]);
        $table = $this->xpath($this->request('GET', '/01-json-table/?' . $query)['body']);
        self::assertSame(5, $table->query('(//table)[2]/thead/tr/th')->length);
        self::assertSame('Phone', $table->evaluate('string((//table)[2]/thead/tr/th[4])'));
        self::assertSame('Delivered', $table->evaluate('string((//table)[2]/tbody/tr[1]/td[5])'));
        self::assertSame(4, $table->query('(//table)[3]/tbody/tr')->length);
        $table = $this->xpath($this->request(
            'GET',
            '/01-json-table/?configured=1&city=Denver&active=0&minimumSpend=0'
        )['body']);
        self::assertSame(3, $table->query('(//table)[2]/thead/tr/th')->length);
        self::assertSame(1, $table->query('(//table)[3]/tbody/tr')->length);
        self::assertSame('Ben Reed', $table->evaluate('string((//table)[3]/tbody/tr/td[1])'));
        $empty = $this->request('GET', '/01-json-table/?minimumSpend=200000');
        self::assertSame(0, $this->xpath($empty['body'])->query('(//table)[3]/tbody/tr')->length);
        self::assertStringContainsString('No customers match.', $empty['body']);
    }

    /** Sends HTTP without following write redirects, preserving this browser's cookie. */
    private function request(string $method, string $path, array $data = []): array
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: " . $this->cookie . "\r\n",
            'content' => $method === 'POST' ? http_build_query($data) : '',
            'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5,
        ]]);
        $body = file_get_contents(self::$baseUrl . $path, false, $context);
        self::assertNotFalse($body, 'HTTP request failed. See ' . self::$serverLog);
        preg_match('/\s(\d{3})\s/', $http_response_header[0], $status);

        foreach ($http_response_header as $header) {
            if (preg_match('/^Set-Cookie: ([^;]+)/i', $header, $cookie)) {
                $this->cookie = $cookie[1];
            }
        }

        return ['status' => (int) $status[1], 'body' => $body];
    }

    /** Parses rendered HTML while keeping libxml warnings out of test output. */
    private function xpath(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    /** Reads the hidden form token from the page rather than bypassing form checks. */
    private function token(string $html): string
    {
        $token = $this->xpath($html)->evaluate('string(//input[@name="csrf"]/@value)');
        self::assertNotSame('', $token);

        return $token;
    }

    /** Reads the example's public JSON inspector to check persisted types and fields. */
    private function storedRecords(string $html): array
    {
        $json = $this->xpath($html)->evaluate(
            'string(//details[summary="Inspect the stored records as JSON"]/pre/code)'
        );

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /** Supplies the same nested field shape submitted by the visible form. */
    private function product(): array
    {
        return ['name' => 'Demo Product', 'sku' => 'DEMO-01', 'pricing' => ['amount' => '12.50', 'currency' => 'USD'],
            'inventory' => ['stock' => '0', 'warehouse' => ['city' => 'Chicago']],
            'publication' => ['active' => '0'], 'description' => 'Stored description'];
    }
}

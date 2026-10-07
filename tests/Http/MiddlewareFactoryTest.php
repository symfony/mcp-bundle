<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Http;

use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\AI\McpBundle\Http\MiddlewareFactory;

class MiddlewareFactoryTest extends TestCase
{
    public function testKeepsSecureDefaultsWhenAllowedHostsIsUnset()
    {
        $this->assertNull((new MiddlewareFactory(null))->create());
    }

    public function testRestrictsDnsRebindingProtectionToConfiguredHosts()
    {
        $middleware = (new MiddlewareFactory(['example.com', 'mcp.example.com']))->create();

        $this->assertIsArray($middleware);

        $dnsMiddleware = null;
        foreach ($middleware as $entry) {
            if ($entry instanceof DnsRebindingProtectionMiddleware) {
                $dnsMiddleware = $entry;
            }
        }

        $this->assertInstanceOf(DnsRebindingProtectionMiddleware::class, $dnsMiddleware);

        $allowedHosts = (new \ReflectionProperty(DnsRebindingProtectionMiddleware::class, 'allowedHosts'))
            ->getValue($dnsMiddleware);
        $this->assertSame(['example.com', 'mcp.example.com'], $allowedHosts);
    }

    public function testKeepsDefaultHostsNextToConfiguredOrigins()
    {
        $dnsMiddleware = $this->dnsRebindingProtection((new MiddlewareFactory(null, ['http://localhost:6274']))->create());

        $this->assertSame(['localhost', '127.0.0.1', '[::1]'], $this->property($dnsMiddleware, 'allowedHosts'));
        $this->assertSame(['http://localhost:6274'], $this->property($dnsMiddleware, 'allowedOrigins'));
    }

    public function testAcceptsARequestFromAConfiguredOrigin()
    {
        $dnsMiddleware = $this->dnsRebindingProtection((new MiddlewareFactory(null, ['http://localhost:6274']))->create());
        $factory = new Psr17Factory();
        $handler = new class($factory) implements RequestHandlerInterface {
            public function __construct(private readonly Psr17Factory $factory)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->factory->createResponse(202);
            }
        };

        $request = $factory->createServerRequest('POST', 'http://localhost:8000/mcp')
            ->withHeader('Host', 'localhost:8000');

        $this->assertSame(202, $dnsMiddleware->process($request->withHeader('Origin', 'http://localhost:6274'), $handler)->getStatusCode());
        $this->assertSame(403, $dnsMiddleware->process($request->withHeader('Origin', 'http://localhost:5173'), $handler)->getStatusCode());
    }

    public function testCombinesConfiguredHostsAndOrigins()
    {
        $dnsMiddleware = $this->dnsRebindingProtection((new MiddlewareFactory(['mcp.example.com'], ['https://app.example.com']))->create());

        $this->assertSame(['mcp.example.com'], $this->property($dnsMiddleware, 'allowedHosts'));
        $this->assertSame(['https://app.example.com'], $this->property($dnsMiddleware, 'allowedOrigins'));
    }

    public function testRemovesDnsRebindingProtectionWhenAllowedHostsIsFalse()
    {
        $middleware = (new MiddlewareFactory(false))->create();

        $this->assertIsArray($middleware);
        $this->assertNotSame([], $middleware);

        foreach ($middleware as $entry) {
            $this->assertNotInstanceOf(DnsRebindingProtectionMiddleware::class, $entry);
        }
    }

    /**
     * @param list<MiddlewareInterface>|null $middleware
     */
    private function dnsRebindingProtection(?array $middleware): DnsRebindingProtectionMiddleware
    {
        $this->assertIsArray($middleware);

        foreach ($middleware as $entry) {
            if ($entry instanceof DnsRebindingProtectionMiddleware) {
                return $entry;
            }
        }

        $this->fail('The middleware stack holds no DnsRebindingProtectionMiddleware.');
    }

    private function property(DnsRebindingProtectionMiddleware $middleware, string $name): mixed
    {
        return (new \ReflectionProperty(DnsRebindingProtectionMiddleware::class, $name))->getValue($middleware);
    }
}

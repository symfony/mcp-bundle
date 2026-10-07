<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Http;

use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Resolves the middleware stack for the streamable HTTP transport based on the configured DNS
 * rebinding protection: the SDK secure defaults are kept, tailored to custom hosts and origins, or disabled.
 *
 * @author Christopher Hertel
 */
final class MiddlewareFactory
{
    /**
     * @param list<string>|false|null $allowedHosts   Hostnames allowed by the DNS rebinding protection: null keeps the
     *                                                SDK default (localhost only), an array restricts it to those hosts,
     *                                                false disables the protection entirely
     * @param list<string>            $allowedOrigins Origins accepted by the DNS rebinding protection besides
     *                                                same-origin requests
     */
    public function __construct(
        private readonly array|false|null $allowedHosts = null,
        private readonly array $allowedOrigins = [],
    ) {
    }

    /**
     * Returns null to keep the SDK secure defaults, or a tailored middleware stack when the DNS
     * rebinding protection needs to be disabled or tailored to custom hosts and origins.
     *
     * @return list<MiddlewareInterface>|null
     */
    public function create(): ?array
    {
        // Unset: keep the SDK secure defaults (localhost hosts, same-origin requests only).
        if (null === $this->allowedHosts && [] === $this->allowedOrigins) {
            return null;
        }

        $middleware = [];
        foreach (StreamableHttpTransport::defaultMiddleware() as $defaultMiddleware) {
            if ($defaultMiddleware instanceof DnsRebindingProtectionMiddleware) {
                // false: disable the protection entirely to expose a public MCP server.
                if (false === $this->allowedHosts) {
                    continue;
                }

                // Unset hosts keep the SDK default (localhost only) next to the configured origins.
                $defaultMiddleware = null === $this->allowedHosts
                    ? new DnsRebindingProtectionMiddleware(allowedOrigins: $this->allowedOrigins)
                    : new DnsRebindingProtectionMiddleware($this->allowedHosts, $this->allowedOrigins);
            }

            $middleware[] = $defaultMiddleware;
        }

        return $middleware;
    }
}

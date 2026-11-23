<?php
declare(strict_types=1);

namespace Tests\Unit\Core\Http;

use App\Core\Http\ClientIp;
use App\Core\Http\IpRange;
use Tests\TestCase;

final class ClientIpTest extends TestCase
{
    public function testWithoutTrustedProxyTheForwardedHeaderIsIgnored(): void
    {
        $this->assertSame('203.0.113.7', ClientIp::resolve('203.0.113.7', '1.2.3.4', []), 'a client cannot pick its own address');
        $this->assertSame('203.0.113.7', ClientIp::resolve('203.0.113.7', '1.2.3.4', ['10.0.0.0/8']), 'not from a trusted proxy');
        $this->assertSame('', ClientIp::resolve('', null, []));
    }

    public function testTheChainIsReadFromTheRightThroughTrustedProxiesOnly(): void
    {
        $trusted = ['10.0.0.0/8', '127.0.0.1'];
        $this->assertSame('198.51.100.4', ClientIp::resolve('10.0.0.1', '198.51.100.4', $trusted));
        $this->assertSame('198.51.100.4', ClientIp::resolve('127.0.0.1', '6.6.6.6, 198.51.100.4, 10.1.2.3', $trusted),
            'two proxies; the forged left entry is never read');
        $this->assertSame('10.0.0.1', ClientIp::resolve('10.0.0.1', 'garbage', $trusted), 'unreadable entry: stop at the proxy');
        $this->assertSame('10.0.0.1', ClientIp::resolve('10.0.0.1', null, $trusted));
        $this->assertSame('198.51.100.4', ClientIp::resolve('::ffff:10.0.0.1', '::ffff:198.51.100.4', $trusted), 'IPv4 seen as IPv6');
    }

    public function testIpv6ClientsAreGroupedByTheirSlash64(): void
    {
        $this->assertSame('203.0.113.7', ClientIp::rateLimitKey('203.0.113.7'));
        $this->assertSame('2001:db8:1:2::/64', ClientIp::rateLimitKey('2001:db8:1:2:aaaa:bbbb:cccc:dddd'));
        $this->assertSame(ClientIp::rateLimitKey('2001:db8:1:2::1'), ClientIp::rateLimitKey('2001:db8:1:2:ffff::9'));
        $this->assertSame('', ClientIp::rateLimitKey(''));
    }

    public function testRanges(): void
    {
        $this->assertTrue(IpRange::contains('10.0.0.0/8', '10.255.0.1'));
        $this->assertFalse(IpRange::contains('10.0.0.0/8', '11.0.0.1'));
        $this->assertTrue(IpRange::contains('192.168.1.0/23', '192.168.0.200'), 'prefix not on a byte boundary');
        $this->assertFalse(IpRange::contains('192.168.1.0/24', '192.168.0.200'));
        $this->assertTrue(IpRange::contains('127.0.0.1', '127.0.0.1'));
        $this->assertTrue(IpRange::contains('2001:db8::/32', '2001:db8:ffff::1'));
        $this->assertFalse(IpRange::contains('::/0', '10.0.0.1'), 'IPv4 never in an IPv6 range');
        $this->assertTrue(IpRange::contains('0.0.0.0/0', '10.0.0.1'));
        foreach (['10.0.0.0/33', '10.0.0.0/', '10.0.0.0/-1', 'localhost', '', '2001:db8::/129'] as $invalid) {
            $this->assertFalse(IpRange::isValid($invalid), $invalid);
        }
    }
}

<?php

namespace Tests\Unit;

use App\Support\ConfigSanitizer;
use PHPUnit\Framework\TestCase;

class ConfigSanitizerTest extends TestCase
{
    /**
     * @dataProvider sessionDomainProvider
     */
    public function test_session_domain_is_normalized(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, ConfigSanitizer::sessionDomain($input));
    }

    public static function sessionDomainProvider(): array
    {
        return [
            'url dengan skema & trailing slash' => ['https://absensilantaburo.my.id/', 'absensilantaburo.my.id'],
            'domain polos' => ['absensilantaburo.my.id', 'absensilantaburo.my.id'],
            'skema tanpa slash' => ['https://contoh.sch.id', 'contoh.sch.id'],
            'ada port & path' => ['http://contoh.sch.id:8080/path', 'contoh.sch.id'],
            'leading dot dipertahankan' => ['.contoh.sch.id', '.contoh.sch.id'],
            'localhost jadi null' => ['localhost', null],
            'ip address jadi null' => ['127.0.0.1', null],
            'tanpa titik jadi null' => ['nodot', null],
            'literal null' => ['null', null],
            'kosong' => ['', null],
            'null php' => [null, null],
        ];
    }

    public function test_trim_url_removes_trailing_slash(): void
    {
        $this->assertSame('https://x.test', ConfigSanitizer::trimUrl('https://x.test/'));
        $this->assertSame('https://x.test', ConfigSanitizer::trimUrl('  https://x.test  '));
        $this->assertNull(ConfigSanitizer::trimUrl(''));
        $this->assertNull(ConfigSanitizer::trimUrl(null));
    }

    public function test_cors_origins_are_cleaned(): void
    {
        $this->assertSame(
            ['https://a.test', 'https://b.test'],
            ConfigSanitizer::corsOrigins('https://a.test/, https://b.test')
        );

        $this->assertSame(
            ['https://a.test', 'https://b.test'],
            ConfigSanitizer::corsOrigins(['https://a.test/', 'https://b.test', '', null])
        );

        $this->assertSame([], ConfigSanitizer::corsOrigins(null));
    }
}

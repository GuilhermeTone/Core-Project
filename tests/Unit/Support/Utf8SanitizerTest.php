<?php

namespace Tests\Unit\Support;

use App\Support\Utf8Sanitizer;
use PHPUnit\Framework\TestCase;

class Utf8SanitizerTest extends TestCase
{
    public function test_sanitize_converts_latin1_string_to_valid_utf8(): void
    {
        $latin1 = iconv('UTF-8', 'ISO-8859-1', 'Alicate pressão') ?: '';

        $sanitized = Utf8Sanitizer::sanitize(['nome' => $latin1]);

        $this->assertTrue(mb_check_encoding($sanitized['nome'], 'UTF-8'));
        $this->assertSame('Alicate pressão', $sanitized['nome']);
    }

    public function test_sanitize_removes_invalid_control_characters_recursively(): void
    {
        $sanitized = Utf8Sanitizer::sanitize([
            'produto' => [
                'nome' => "Chave\x00 Grifo",
            ],
        ]);

        $this->assertSame('Chave Grifo', $sanitized['produto']['nome']);
    }
}

<?php

namespace Tests\Unit;

use App\Casts\EncryptedFaceDescriptor;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use Tests\TestCase;

class EncryptedFaceDescriptorTest extends TestCase
{
    public function test_descriptor_is_encrypted_and_can_be_decoded(): void
    {
        $descriptor = array_fill(0, 512, 0.125);

        $encoded = EncryptedFaceDescriptor::encode($descriptor);

        $this->assertStringStartsWith('enc:', $encoded);
        $this->assertStringNotContainsString('0.125', $encoded);
        $this->assertSame($descriptor, EncryptedFaceDescriptor::decode($encoded));
    }

    public function test_legacy_json_descriptor_remains_readable(): void
    {
        $descriptor = array_fill(0, 512, -0.25);

        $this->assertSame($descriptor, EncryptedFaceDescriptor::decode(json_encode($descriptor)));
    }

    public function test_invalid_encrypted_descriptor_is_rejected_without_leaking_crypto_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Template wajah tidak dapat dibaca.');

        EncryptedFaceDescriptor::decode('enc:'.Crypt::encryptString('{invalid json'));
    }
}

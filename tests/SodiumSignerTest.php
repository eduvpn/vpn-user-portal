<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal\Tests;

use PHPUnit\Framework\TestCase;
use SURFnet\VPN\Portal\SodiumSigner;

class SodiumSignerTest extends TestCase
{
    public function testSign()
    {
        $sodiumSigner = new SodiumSigner(\file_get_contents(\sprintf('%s/data/server.key', __DIR__)));
        $this->assertSame(
            'kwPPZ7fktSIGWJwbXytaHtJ5DfCvoQeD0RSBnDXdClf1OUSqnMHTM4F9f4QDaRrEBJwumWkPAc6ZUlVBkL10DHsiZm9vIjoiYmFyIn0',
            $sodiumSigner->sign(['foo' => 'bar'])
        );
    }

    public function testVerify()
    {
        $sodiumSigner = new SodiumSigner(\file_get_contents(\sprintf('%s/data/server.key', __DIR__)));
        $this->assertSame(
            [
                'foo' => 'bar',
                'key_id' => 'local',
            ],
            $sodiumSigner->verify(
                'kwPPZ7fktSIGWJwbXytaHtJ5DfCvoQeD0RSBnDXdClf1OUSqnMHTM4F9f4QDaRrEBJwumWkPAc6ZUlVBkL10DHsiZm9vIjoiYmFyIn0'
            )
        );
    }

    public function testSignVerifyWrongKey()
    {
        $sodiumSigner = new SodiumSigner(\file_get_contents(\sprintf('%s/data/server_2.key', __DIR__)));
        $this->assertFalse(
            $sodiumSigner->verify(
                'kwPPZ7fktSIGWJwbXytaHtJ5DfCvoQeD0RSBnDXdClf1OUSqnMHTM4F9f4QDaRrEBJwumWkPAc6ZUlVBkL10DHsiZm9vIjoiYmFyIn0'
            )
        );
    }

    public function testAdditionalPublicKeys()
    {
        $sodiumSigner = new SodiumSigner(
            \file_get_contents(\sprintf('%s/data/server_2.key', __DIR__)),
            [
                'remote_0' => \hex2bin('8eb83482647f677615be50834ba9043588a5c07e62be88ba80ab7f2c6785f75d'),
                'remote_1' => \hex2bin('8eb83482647f677615be50834ba9043588a5c07e62be88ba80ab7f2c6785f76d'),
                'remote_2' => \hex2bin('8eb83482647f677615be50834ba9043588a5c07e62be88ba80ab7f2c6785f77d'),
            ]
        );
        $this->assertSame(
            [
                'foo' => 'bar',
                'key_id' => 'remote_1',
            ],
            $sodiumSigner->verify(
                's2J7rZp6UK9xiXSa9fZ6CjDbotGnx7YrAtD84w5WyMU_-RnkVlw6FxCsPSrgP7njSXgL-Wsa6O8HvEW3aSYaAXsiZm9vIjoiYmFyIn0'
            )
        );
    }

    public function testAdditionalPublicKeysHint()
    {
        $sodiumSigner = new SodiumSigner(
            \file_get_contents(\sprintf('%s/data/server_2.key', __DIR__)),
            [
                'remote_0' => \hex2bin('8eb83482647f677615be50834ba9043588a5c07e62be88ba80ab7f2c6785f75d'),
                'remote_1' => \hex2bin('8eb83482647f677615be50834ba9043588a5c07e62be88ba80ab7f2c6785f76d'),
                'remote_2' => \hex2bin('8eb83482647f677615be50834ba9043588a5c07e62be88ba80ab7f2c6785f77d'),
            ]
        );
        $this->assertSame(
            [
                'foo' => 'bar',
                'key_id' => 'remote_1',
            ],
            $sodiumSigner->verify(
                's2J7rZp6UK9xiXSa9fZ6CjDbotGnx7YrAtD84w5WyMU_-RnkVlw6FxCsPSrgP7njSXgL-Wsa6O8HvEW3aSYaAXsiZm9vIjoiYmFyIn0.eyJpc3MiOiJyZW1vdGVfMSJ9Cg'
            )
        );
    }

    /**
     * @expectedException \fkooman\OAuth\Server\Exception\InvalidRequestException
     * @expectedExceptionMessage invalid_request
     */
    public function testAdditionalPublicKeysHintMalformedJson()
    {
        $sodiumSigner = new SodiumSigner(
            \file_get_contents(\sprintf('%s/data/server_2.key', __DIR__)),
            [
                'remote_0' => \hex2bin('8eb83482647f677615be50834ba9043588a5c07e62be88ba80ab7f2c6785f75d'),
                'remote_1' => \hex2bin('8eb83482647f677615be50834ba9043588a5c07e62be88ba80ab7f2c6785f76d'),
                'remote_2' => \hex2bin('8eb83482647f677615be50834ba9043588a5c07e62be88ba80ab7f2c6785f77d'),
            ]
        );
        $sodiumSigner->verify('s2J7rZp6UK9xiXSa9fZ6CjDbotGnx7YrAtD84w5WyMU_-RnkVlw6FxCsPSrgP7njSXgL-Wsa6O8HvEW3aSYaAXsiZm9vIjoiYmFyIn0.fXsK');
    }
}

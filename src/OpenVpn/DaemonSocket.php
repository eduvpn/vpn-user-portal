<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OpenVpn;

use RuntimeException;

class DaemonSocket
{
    /** @var resource|null */
    private $daemonSocket = null;

    private string $certDir;

    private bool $useTls;

    public function __construct(string $certDir, bool $useTls)
    {
        $this->certDir = $certDir;
        $this->useTls = $useTls;
    }

    public function open(string $nodeIp): void
    {
        $this->daemonSocket = self::getSocket($nodeIp, $this->certDir, $this->useTls);
    }

    /**
     * @param array<int> $managementPortList
     */
    public function setPorts(array $managementPortList): void
    {
        $this->sendCommand(sprintf('SET_PORTS %s', implode(' ', $managementPortList)));
    }

    /**
     * @return array<array{management_port:int,common_name:string,virtual_address:array{0:string,1:string}}>
     */
    public function connections(): array
    {
        $connectionList = self::parseConnectionList($this->sendCommand('LIST'));

        return $connectionList;
    }

    /**
     * @param array<string> $commonNameList
     */
    public function disconnect(array $commonNameList): void
    {
        $this->sendCommand(sprintf('DISCONNECT %s', implode(' ', $commonNameList)));
    }

    public function close(): void
    {
        if (null !== $daemonSocket = $this->daemonSocket) {
            // we don't care if it fails, fail in silence...
            @fclose($daemonSocket);
        }
    }

    /**
     * @return resource
     */
    private static function getSocket(string $nodeIp, string $certDir, bool $useTls)
    {
        // never use TLS to connect to localhost, no matter whether useTls is
        // true...
        if (!\in_array($nodeIp, ['127.0.0.1', '::1'], true) && $useTls) {
            // we MUST have a TLS cert
            // @see https://www.php.net/manual/en/context.ssl.php
            // @see https://www.php.net/manual/en/transports.inet.php
            $streamContext = stream_context_create(
                [
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                        'peer_name' => 'vpn-daemon',
                        'cafile' => $certDir.'/ca.crt',
                        'local_cert' => $certDir.'/vpn-daemon-client.crt',
                        'local_pk' => $certDir.'/vpn-daemon-client.key',
                        'ciphers' => 'ECDHE-RSA-AES256-GCM-SHA384',
                        'disable_compression' => true,
                    ],
                ]
            );

            $socketAddress = sprintf('ssl://%s:41194', $nodeIp);
            if (false === $daemonSocket = @stream_socket_client($socketAddress, $errno, $errstr, 5, \STREAM_CLIENT_CONNECT, $streamContext)) {
                throw new RuntimeException(sprintf('unable to open socket to "%s": [%d]: %s', $socketAddress, $errno, $errstr));
            }

            return $daemonSocket;
        }

        $socketAddress = sprintf('tcp://%s:41194', $nodeIp);
        if (false === $daemonSocket = @stream_socket_client($socketAddress, $errno, $errstr, 5, \STREAM_CLIENT_CONNECT)) {
            throw new RuntimeException(sprintf('unable to open socket to "%s": [%d]: %s', $socketAddress, $errno, $errstr));
        }

        return $daemonSocket;
    }

    /**
     * @return array<string>
     */
    private function sendCommand(string $socketCommand): array
    {
        $this->writeLineToSocket(sprintf("%s\n", $socketCommand));

        return $this->handleResponse();
    }

    /**
     * @return array<string>
     */
    private function handleResponse(): array
    {
        $statusLine = $this->readLineFromSocket();
        if (0 !== strpos($statusLine, 'OK: ')) {
            throw new RuntimeException(sprintf('expected "OK <n>", got "%s"', $statusLine));
        }
        $resultLineCount = (int) substr($statusLine, 4);
        $resultData = [];
        for ($i = 0; $i < $resultLineCount; ++$i) {
            $resultData[] = trim($this->readLineFromSocket());
        }

        return $resultData;
    }

    /**
     * @param array<string> $connectionList
     *
     * @return array<array{management_port:int,common_name:string,virtual_address:array{0:string,1:string}}>
     */
    private static function parseConnectionList(array $connectionList): array
    {
        $clientInfoList = [];
        foreach ($connectionList as $connectionLine) {
            $clientInfo = explode(' ', $connectionLine);
            $clientInfoList[] = [
                'management_port' => (int) $clientInfo[0],
                'common_name' => $clientInfo[1],
                'virtual_address' => [$clientInfo[2], $clientInfo[3]],
            ];
        }

        return $clientInfoList;
    }

    private function writeLineToSocket(string $lineToWrite): void
    {
        if (null === $this->daemonSocket) {
            throw new RuntimeException('socket not open');
        }

        if (false === @fwrite($this->daemonSocket, $lineToWrite)) {
            throw new RuntimeException('unable to WRITE to socket');
        }
    }

    private function readLineFromSocket(): string
    {
        if (null === $this->daemonSocket) {
            throw new RuntimeException('socket not open');
        }

        if (false === $responseData = @fgets($this->daemonSocket)) {
            throw new RuntimeException('unable to READ from socket');
        }

        return $responseData;
    }
}

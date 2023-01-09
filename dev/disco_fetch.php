<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';

use Vpn\Portal\Crypto\Minisign\PublicKey;
use Vpn\Portal\Crypto\Minisign\Verifier;
use Vpn\Portal\HttpClient\CurlHttpClient;
use Vpn\Portal\HttpClient\HttpClientRequest;

$c = new CurlHttpClient();

// ** PRODUCTION **
// $h1 = new HttpClientRequest('GET', 'https://disco.eduvpn.org/v2/server_list.json');
// $h2 = new HttpClientRequest('GET', 'https://disco.eduvpn.org/v2/server_list.json.minisig');
// $v = new Verifier(
//    [
//        new PublicKey('RWQKqtqvd0R7rUDp0rWzbtYPA3towPWcLDCl7eY9pBMMI/ohCmrS0WiM'),
//        new PublicKey('RWRtBSX1alxyGX+Xn3LuZnWUT0w//B6EmTJvgaAxBMYzlQeI+jdrO6KF'),
//    ]
// );

// ** DEV **
$h1 = new HttpClientRequest('GET', 'https://git.sr.ht/~eduvpn/disco.eduvpn.org/blob/dev/out/server_list.json');
$h2 = new HttpClientRequest('GET', 'https://git.sr.ht/~eduvpn/disco.eduvpn.org/blob/dev/out/server_list.json.minisig');
$v = new Verifier(
    [
        new PublicKey('RWT8KVFvyVjgPIIKA3Wogh5O8eFASpDOd3H8YEv961+SXLZkrS+276Gr'),
    ]
);

$r1 = $c->send($h1);
$r2 = $c->send($h2);

var_dump(
    $v->verifyDetached(
        $r1->body(),
        $r2->body()
    )
);

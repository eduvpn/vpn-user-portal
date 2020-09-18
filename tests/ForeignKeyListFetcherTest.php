<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use LC\Portal\ForeignKeyListFetcher;
use PHPUnit\Framework\TestCase;

class ForeignKeyListFetcherTest extends TestCase
{
    /**
     * @return void
     */
    public function testFetch()
    {
        $tmpDir = sprintf('%s/%s', sys_get_temp_dir(), bin2hex(random_bytes(10)));
        mkdir($tmpDir);
        $foreignKeyListFetcher = new ForeignKeyListFetcher($tmpDir);
        $foreignKeyListFetcher->update(
            new TestForeignKeyHttpClient(),
            'https://disco.eduvpn.org/v2/server_list.json',
            [
                'RWQ68Y5/b8DED0TJ41B1LE7yAvkmavZWjDwCBUuC+Z2pP9HaSawzpEDA', // jornane@uninett.no
                'RWRtBSX1alxyGX+Xn3LuZnWUT0w//B6EmTJvgaAxBMYzlQeI+jdrO6KF', // fkooman@deic.dk
                'RWQKqtqvd0R7rUDp0rWzbtYPA3towPWcLDCl7eY9pBMMI/ohCmrS0WiM', // RoSp
            ]
        );
        $this->assertSame(
            [
                'h4lbqzomDee9bDEhPTSM5PE0P8QEqKVnRjOwjOdHu7U' => [
                    'public_key' => 'Xv3l24gbMX8NtTnFQbWO2fGKPwKuc6EbjQDv8qw2GVk',
                    'base_uri' => 'https://eduvpn.rash.al/',
                ],
                'B8xhz41VQEqF1jkiXvFwVRWOQFWV1WJlL19WpFoEofM' => [
                    'public_key' => 'HpY5RKF0OzYcYUcogKzgt1MvC6CxBmDJoUBsyiKjioA',
                    'base_uri' => 'https://gdpt-eduvpndev1.tnd.aarnet.edu.au/',
                ],
                '07wQOlf0uFqs5PL7zkcnMY73HpH0_uP09l68pK1YgBI' => [
                    'public_key' => 'bRTz33KIuYo_w_-AbzNtdmLDqIm11_eGiHXQniojxY4',
                    'base_uri' => 'https://eduvpn.deic.dk/',
                ],
                '6He9SqGAcIcpQUrsGTF5dRxVYmt2aLAIYtkfzNE9MEs' => [
                    'public_key' => 'jGpivOdwCRoLlexYKQjulZPPP4s3d9SVBFslI6RroAo',
                    'base_uri' => 'https://eduvpn.eenet.ee/',
                ],
                'k8OpvL--5EK-HvVFAnpAfumbodNbjBAFab7UkzTlgd0' => [
                    'public_key' => 'H4NTnM18BJgU_B3r8OBDVblBSfozB2Zu97I_ag2whmM',
                    'base_uri' => 'https://eduvpn1.funet.fi/',
                ],
                'ZECzTWMcnz63LcMk9uGnu-TL-eaqccKjAAJvVS3Qxsc' => [
                    'public_key' => 'ePVNzE15h0yS6Xf3s8nJWmc8V6FeFziA3TZr0uOacFg',
                    'base_uri' => 'https://eduvpn-poc.renater.fr/',
                ],
                'GSYOWtNfpyfAMEYAO1mPme1mSKmYt8lpm4naDEG9ogc' => [
                    'public_key' => 'QjJHMit3vhHwLKi-fu2dXXSxMxnkskFVS3hMwyCnWQs',
                    'base_uri' => 'https://eduvpn1.eduvpn.de/',
                ],
                'RQzPWL_Z0GVH_3p76ie6y0B41opH8qMJpBcPoIueNqo' => [
                    'public_key' => 'aX-El_yRPdcUDF5S2smQ-9U7BzB35_1RtFYSjbHfEz8',
                    'base_uri' => 'https://eduvpn.marwan.ma/',
                ],
                'MwuxPK3cHSOWTJIwEHQqmm278cws7o5dlTyzBFumrqs' => [
                    'public_key' => 'qOLCcqXWZm9nmjsrwiJQxxWD606vDEJ2MIcc85oJmnE',
                    'base_uri' => 'https://guest.eduvpn.no/',
                ],
                'xD55nbR-rIiZc_VPh2GvCVK0y-O2RXE-fU32XGaEaUI' => [
                    'public_key' => 'LKWFZblpTFvFDY4E_0tnD8yHpK-iOSrJop_1x-A4cQ8',
                    'base_uri' => 'https://vpn.pern.edu.pk/',
                ],
                'ffPiNV6t_-kJXa3f7SQBfVXmjbMxe1Hv4f3VcxARlnA' => [
                    'public_key' => 'drq4-3Sg35UJHxVfx-ssR3onjHihCVH-zjXYPnEfudI',
                    'base_uri' => 'https://eduvpn.ac.lk/',
                ],
                'xGAxo6xS9R3CHXc_fYhzeYACoB1dTHCen1mXEd-vmhE' => [
                    'public_key' => 'O53DTgB956magGaWpVCKtdKIMYqywS3FMAC5fHXdFNg',
                    'base_uri' => 'https://nl.eduvpn.org/',
                ],
                'e8f15hlgOJFJtzr-gRP-nf2g4ObBvUJmM9VMk9s4_i0' => [
                    'public_key' => '4plEYRW26amB1-PcRRxWcKlEcDXsUO4yNHnf0VA2MPE',
                    'base_uri' => 'https://eduvpn.renu.ac.ug/',
                ],
                'vdEHE0QNgzHq4L7I3SBGMY3PkqCW3gNp49GEYR2j9RM' => [
                    'public_key' => 'gCUDsmSIn0pVPc4C-QNLsBil6WxkwDDwxfvTC262p9U',
                    'base_uri' => 'https://eduvpn.uran.ua/',
                ],
            ],
            $foreignKeyListFetcher->extract()
        );
    }
}

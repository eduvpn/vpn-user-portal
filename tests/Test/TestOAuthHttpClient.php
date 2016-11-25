<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace SURFnet\VPN\Portal\Test;

use fkooman\OAuth\Client\HttpClientInterface;
use fkooman\OAuth\Client\Provider;

class TestOAuthHttpClient implements HttpClientInterface
{
    public function post(Provider $provider, array $postData)
    {
        return [
            'access_token' => 'X',
            'token_type' => 'bearer'
        ];
    }
}

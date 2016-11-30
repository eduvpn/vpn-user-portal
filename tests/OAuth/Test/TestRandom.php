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

namespace SURFnet\VPN\Portal\OAuth\Test;

use RuntimeException;
use SURFnet\VPN\Portal\OAuth\RandomInterface;

class TestRandom implements RandomInterface
{
    public function get($len)
    {
        if (8 === $len) {
            return 'abcd1234abcd1234';
        }
        if (16 === $len) {
            return 'wxyz1234efgh5678wxyz1234efgh5678';
        }

        throw new RuntimeException('unexpected length');
    }
}

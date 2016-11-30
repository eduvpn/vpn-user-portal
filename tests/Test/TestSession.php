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

use SURFnet\VPN\Common\Http\SessionInterface;

class TestSession implements SessionInterface
{
    /** var @array */
    private $s;

    public function __construct()
    {
        $this->s = [];
    }

    public function set($key, $value)
    {
        $this->s[$key] = $value;
    }

    public function delete($key)
    {
        unset($this->s[$key]);
    }

    public function has($key)
    {
        return array_key_exists($key, $this->s);
    }

    public function get($key)
    {
        if (!$this->has($key)) {
            return;
        }

        return $this->s[$key];
    }

    public function destroy()
    {
        $this->s = [];
    }
}

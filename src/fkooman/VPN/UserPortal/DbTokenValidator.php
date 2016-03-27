<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Lesser General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Lesser General Public License for more details.
 *
 *  You should have received a copy of the GNU Lesser General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace fkooman\VPN\UserPortal;

use fkooman\Rest\Plugin\Authentication\Bearer\ValidatorInterface;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use PDO;

class DbTokenValidator implements ValidatorInterface
{
    /** @var \PDO */
    private $db;

    /** @var string */
    private $prefix;

    public function __construct(PDO $db, $prefix = '')
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db = $db;
        $this->prefix = $prefix;
    }

    /**
     * @return TokenInfo
     */
    public function validate($bearerToken)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT client_id, user_id, issued_at, scope FROM %s WHERE token = :token',
                $this->prefix.'access_token'
            )
        );
        $stmt->bindValue(':token', $bearerToken, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (false === $result) {
            return new TokenInfo(
                array(
                    'active' => false,
                )
            );
        }

        return new TokenInfo(
            array(
                'active' => true,
                'client_id' => $result['client_id'],
                'scope' => $result['scope'],
                'token_type' => 'bearer',
                'iat' => intval($result['issued_at']),
                'sub' => $result['user_id'],
            )
        );
    }
}

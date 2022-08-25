#!/bin/sh

if [ "Darwin" == "$(uname)" ]; then
    BROWSER=open        # macOS
else
    BROWSER=xdg-open	# other
fi

WEB_PORTAL_URL="http://localhost:8082"
#WEB_PORTAL_URL="https://vpn.tuxed.net"

PORT=12345
REDIRECT_URI=http://127.0.0.1:${PORT}/callback
CLIENT_ID=org.eduvpn.app.linux
SCOPE=config
STATE=$(openssl rand -base64 32 | tr '/+' '_-' | tr -d '=' | tr -d '\n')
CODE_VERIFIER=$(openssl rand -base64 32 | tr '/+' '_-' | tr -d '=' | tr -d '\n')
CODE_CHALLENGE=$(echo -n "${CODE_VERIFIER}" | openssl sha256 -binary | base64 | tr '/+' '_-' | tr -d '=' | tr -d '\n')

SERVER_INFO_URL="${WEB_PORTAL_URL}/.well-known/vpn-user-portal"
SERVER_INFO=$(curl -s "${SERVER_INFO_URL}")

AUTHZ_ENDPOINT=$(echo "${SERVER_INFO}" | jq -r '.api."http://eduvpn.org/api#3".authorization_endpoint')
TOKEN_ENDPOINT=$(echo "${SERVER_INFO}" | jq -r '.api."http://eduvpn.org/api#3".token_endpoint')
AUTHZ_URL="${AUTHZ_ENDPOINT}?client_id=${CLIENT_ID}&redirect_uri=${REDIRECT_URI}&response_type=code&scope=${SCOPE}&state=${STATE}&code_challenge_method=S256&code_challenge=${CODE_CHALLENGE}"

# open the browser
${BROWSER} "${AUTHZ_URL}" &

SERVER() {
	P=/tmp/$$.fifo
	trap "rm ${P}" EXIT
	mkfifo ${P}
	cat ${P} | nc -l ${PORT} | while read -r L
	do
        echo "${L}"
		printf 'HTTP/1.0 200 OK\r\nContent-Type: text/plain\r\n\r\nAll done! Close browser tab.' >${P}
        break
    done
}

RESPONSE=$(SERVER)
ERROR=$(echo "${RESPONSE}" | cut -d '?' -f 2 | cut -d ' ' -f 1 | tr '&' '\n' | grep ^error | cut -d '=' -f 2)
if [ -n "${ERROR}" ]
then
    echo "ERROR: ${ERROR}"
    exit 1
fi

RESPONSE_STATE=$(echo "${RESPONSE}" | cut -d '?' -f 2 | cut -d ' ' -f 1 | tr '&' '\n' | grep ^state | cut -d '=' -f 2)
RESPONSE_CODE=$(echo "${RESPONSE}" | cut -d '?' -f 2 | cut -d ' ' -f 1 | tr '&' '\n' | grep ^code | cut -d '=' -f 2)

if [ "${STATE}" != "${RESPONSE_STATE}" ]
then
    echo "ERROR: request/response state does NOT match!"
    exit 1
fi

# use response code to obtain access token
TOKEN_RESPONSE=$(curl -s -d 'grant_type=authorization_code' -d "client_id=${CLIENT_ID}" -d "code=${RESPONSE_CODE}" -d "code_verifier=${CODE_VERIFIER}" -d "redirect_uri=${REDIRECT_URI}" "${TOKEN_ENDPOINT}")
BEARER_TOKEN=$(echo "${TOKEN_RESPONSE}" | jq -r '.access_token')
API_ENDPOINT=$(echo "${SERVER_INFO}" | jq -r '.api."http://eduvpn.org/api#3".api_endpoint')
PROFILE_ID_LIST=$(curl -s -H "Authorization: Bearer ${BEARER_TOKEN}" "${API_ENDPOINT}/info" | jq -r '.info.profile_list[].profile_id' | xargs)
for PROFILE_ID in ${PROFILE_ID_LIST}; do
    O_CFG_FILE=$(echo "${WEB_PORTAL_URL}_${PROFILE_ID}.o.conf" | sed 's/[^a-zA-Z0-9.-]/_/g')
    W_CFG_FILE=$(echo "${WEB_PORTAL_URL}_${PROFILE_ID}.w.conf" | sed 's/[^a-zA-Z0-9.-]/_/g')
    
    curl -o "${O_CFG_FILE}" -s -d "profile_id=${PROFILE_ID}" -H "Accept: application/x-openvpn-profile" -H "Authorization: Bearer ${BEARER_TOKEN}" "${API_ENDPOINT}/connect"

    # TODO: add the secret key to the wireguard config
    SECRET_KEY=$(wg genkey)
    PUBLIC_KEY=$(echo ${SECRET_KEY} | wg pubkey)
    curl -o "${W_CFG_FILE}" -s --data-urlencode "public_key=${PUBLIC_KEY}" -d "profile_id=${PROFILE_ID}" -H "Accept: application/x-wireguard-profile" -H "Authorization: Bearer ${BEARER_TOKEN}" "${API_ENDPOINT}/connect"

#    # add the configuration to NetworkManager
#    nmcli connection import type openvpn file "${O_CFG_FILE}"
#    rm ${O_CFG_FILE}
#    rm ${W_CFG_FILE}
done

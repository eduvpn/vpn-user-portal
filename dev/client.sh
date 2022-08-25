#!/bin/sh

SERVER="http://localhost:8082"
BROWSER=/usr/bin/firefox
#BROWSER=open    # macOS

PORT=12345
REDIRECT_URI=http://127.0.0.1:${PORT}/callback
CLIENT_ID=org.eduvpn.app.linux
SCOPE=config
STATE=$(openssl rand -base64 32 | tr '/+' '_-' | tr -d '=' | tr -d '\n')
CODE_VERIFIER=$(openssl rand -base64 32 | tr '/+' '_-' | tr -d '=' | tr -d '\n')
CODE_CHALLENGE=$(echo -n "${CODE_VERIFIER}" | openssl sha256 -binary | base64 | tr '/+' '_-' | tr -d '=' | tr -d '\n')

# figure out "authorize endpoint"
AUTHZ_URL="${SERVER}/.well-known/vpn-user-portal"
echo ${AUTHZ_URL}
SERVER_INFO=$(curl -s "${AUTHZ_URL}")
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
echo ${BEARER_TOKEN}
#API_BASE_URI=$(echo "${SERVER_INFO}" | jq -r '.api."http://eduvpn.org/api#3".api_endpoint')

## try to fetch the profile_list
#PROFILE_ID_LIST=$(curl -s -H "Authorization: Bearer ${BEARER_TOKEN}" "${API_BASE_URI}/profile_list" | jq -r '.profile_list.data[].profile_id' | xargs)
#for PROFILE_ID in ${PROFILE_ID_LIST}
#do
#    # fetch OpenVPN config files for each server/profile available to us
#    CONF_FILE=$(echo "${SERVER}_${PROFILE_ID}.ovpn" | sed 's/[^a-zA-Z0-9.-]/_/g')
#    echo ${CONF_FILE}
#    curl -o "${CONF_FILE}" -s -H "Authorization: Bearer ${BEARER_TOKEN}" "${API_BASE_URI}/profile_config?profile_id=${PROFILE_ID}"
##    cat ${CONF_FILE}
#    # add cert / key to it
#    CERT=$(jq -r '.create_keypair.data.certificate' < "${KEY_FILE}")
#    KEY=$(jq -r '.create_keypair.data.private_key' < "${KEY_FILE}")

#    echo "" >> "${CONF_FILE}"
#    echo "<cert>${CERT}</cert><key>${KEY}</key>" >> "${CONF_FILE}"

#    # add the configuration to NetworkManager
#    nmcli connection import type openvpn file "${CONF_FILE}"
#    rm ${CONF_FILE}
#done
## delete key 
#rm "${KEY_FILE}"

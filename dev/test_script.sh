#!/bin/sh
if [ "C" == "${VPN_EVENT}" ]; then
	env | grep ^VPN_ > /tmp/connect_env_dump.txt
else 
	env | grep ^VPN_ > /tmp/disconnect_env_dump.txt
fi
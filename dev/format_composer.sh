#!/bin/sh
cat composer.json | jq -S --indent 4 > X
mv X composer.json

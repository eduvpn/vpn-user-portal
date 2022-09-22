#!/bin/sh
sassc -t expanded web/css/screen.css web/css/X
mv web/css/X web/css/screen.css

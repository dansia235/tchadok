#!/bin/sh
set -eu

: "${ICECAST_HOSTNAME:=radio.tchadok.td}"
: "${ICECAST_ADMIN_USER:=admin}"
: "${ICECAST_ADMIN_PASSWORD:=change-me-admin}"
: "${ICECAST_SOURCE_PASSWORD:=change-me-source}"
: "${ICECAST_RELAY_PASSWORD:=change-me-relay}"

envsubst '${ICECAST_HOSTNAME} ${ICECAST_ADMIN_USER} ${ICECAST_ADMIN_PASSWORD} ${ICECAST_SOURCE_PASSWORD} ${ICECAST_RELAY_PASSWORD}' \
  < /etc/icecast2/icecast.xml.template \
  > /etc/icecast2/icecast.xml

exec icecast2 -n -c /etc/icecast2/icecast.xml


#!/bin/sh

set -e
/app/app/verja getCVE -f ${CONFIG} --cpe-path=${CPE}

#!/bin/sh

CUR_DIR="$( cd "$( dirname "$0" )" && pwd )"
CHEF=${CUR_DIR}/../_piecrust/chef
OUT_DIR=${CUR_DIR}/../_piecrust/resources/messages
ROOT_DIR=${CUR_DIR}/messages

$CHEF bake -o $OUT_DIR $ROOT_DIR
rm ${OUT_DIR}/index.html

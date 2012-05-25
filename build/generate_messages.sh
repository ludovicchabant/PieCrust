#!/bin/sh

CUR_DIR="$( cd "$( dirname "$0" )" && pwd )"
CHEF=${CUR_DIR}/../bin/chef
OUT_DIR=${CUR_DIR}/../res/messages
ROOT_DIR=${CUR_DIR}/messages

$CHEF --root=$ROOT_DIR bake -o $OUT_DIR
rm ${OUT_DIR}/index.html

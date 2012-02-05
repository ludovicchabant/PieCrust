#!/bin/sh

set -e

CWD="$( cd "$( dirname "$0" )" && pwd )"

THIS_REPO="$( hg root )"
APP_REPO=${THIS_REPO}/../PieCrust_App
SAMPLE_REPO=${THIS_REPO}/../PieCrust_Sample

# Exporting app-only repository.
hg convert --filemap ${CWD}/piecrust_app_filemap ${THIS_REPO} ${APP_REPO}


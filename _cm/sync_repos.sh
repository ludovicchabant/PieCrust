#!/bin/sh

set -e

CWD="$( cd "$( dirname "$0" )" && pwd )"

THIS_REPO="$( hg root )"
APP_REPO=${THIS_REPO}/../PieCrust_App
SAMPLE_REPO=${THIS_REPO}/../PieCrust_Sample

# Exporting app-only repository.
hg convert --filemap ${CWD}/piecrust_app_filemap ${THIS_REPO} ${APP_REPO}

# Exporting sample website repository.
hg convert --filemap ${CWD}/piecrust_sample_filemap ${THIS_REPO} ${SAMPLE_REPO}


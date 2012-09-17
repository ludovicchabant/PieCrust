#!/bin/sh

set -e

# Setup defaults.
EXE_DIR=`pwd`
CLONE_DIR=$EXE_DIR/piecrust-src
OUT_DIR=$EXE_DIR/piecrust-bin
BUILD_SCRIPT=build/compile.php
REPO_URL=https://bitbucket.org/ludovicchabant/piecrust
PHAR_FILE=piecrust.phar
VERSION_FILE=version
BUILDLOG_FILE=buildlog
LASTVERSION_FILE=lastversion


# Display help/usage?
if [ "$1" == "-h" ] || [ "$1" == "--help" ]; then
    echo "Usage: "
    echo "  $0 <output_dir>"
    echo ""
    echo "This will create/update the PieCrust binaries for each known version in <output_dir>."
    echo "Note that the repository will be cloned at: $CLONE_DIR"
    exit 1
fi

# Get arguments.
if [ "$1" != "" ]; then
    OUT_DIR=$1
fi

# Clone the PieCrust source if needed.
if [ ! -d "$CLONE_DIR" ]; then
    echo "Cloning PieCrust repository into: $CLONE_DIR"
    hg clone $REPO_URL $CLONE_DIR
fi

# Create the output directory if needed.
if [ ! -d "$OUT_DIR" ]; then
    mkdir -p "$OUT_DIR"
fi

# We'll do everything from the cloned repository, so we can run Mercurial commands directly.
cd "$CLONE_DIR"


# Create binaries for default and stable branches.
BuildHeadBinary() {
    # $1 should be the branch name.
    REV_ID=`hg id -r $1 -i`
    if [ ! -f "$OUT_DIR/$1/$PHAR_FILE" ] || [ $REV_ID != `cat "$OUT_DIR/$1/$VERSION_FILE"` ]; then
        hg up $1
        mkdir -p "$OUT_DIR/$1/"
        echo "Building $1..."
        php -d phar.readonly=0 $BUILD_SCRIPT "$OUT_DIR/$1/$PHAR_FILE" > "$OUT_DIR/$1/$BUILDLOG_FILE"
        hg id -i > "$OUT_DIR/$1/$VERSION_FILE"
    else
        echo "Binary for '$1' is up to date with revision '$REV_ID'."
    fi
}
BuildHeadBinary default
BuildHeadBinary stable


# Create binaries for tagged releases.
BuildTaggedBinary() {
    # $1 should be the tag name.
    if [ ! -f "$OUT_DIR/$1/$PHAR_FILE" ]; then
        if [ -f "$OUT_DIR/$1/$LASTVERSION_FILE" ]; then
            # We know for sure this version, and the ones after that,
            # don't have the Phar compiler anymore.
            return 1
        fi
        hg up -r "$1"
        mkdir -p "$OUT_DIR/$1/"
        if [ ! -f $BUILD_SCRIPT ]; then
            # Got to a version so old the Phar compiler didn't exist yet.
            # Remember this for next time.
            echo "true" > "$OUT_DIR/$1/$LASTVERSION_FILE"
            return 1
        fi
        echo "Building $1..."
        php -d phar.readonly=0 $BUILD_SCRIPT "$OUT_DIR/$1/$PHAR_FILE" > "$OUT_DIR/$1/$BUILDLOG_FILE"
        if [ ! -f "$OUT_DIR/$1/$PHAR_FILE" ]; then
            # Originally, it would only create the Phar file in the current directory.
            mv piecrust.phar "$OUT_DIR/$1/$PHAR_FILE"
        fi
    else
        echo "Binary for '$1' is up to date."
    fi
    return 0
}
BuildTaggedBinaries() {
    VERSION_TAGS=`hg log -r "reverse(tag())" --template "{tags}\n"`
    for version in $VERSION_TAGS; do
        if [[ $version == version_* ]]; then
            BuildTaggedBinary $version
            if [ $? -gt 0 ]; then
                return
            fi
        fi
    done
}
BuildTaggedBinaries



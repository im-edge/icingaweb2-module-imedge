#!/bin/bash
find -L vendor/*/*/public/css -type f -name "*.less" -exec cat {} \; 2>/dev/null > public/css/combined.less
find -L vendor/*/*/public/js -type f -name "*.js" -exec cat {} \; 2>/dev/null > public/js/combined.js
# TODO: this does not clean up vanished files
cp -r vendor/*/*/public/img/* public/img/vendor

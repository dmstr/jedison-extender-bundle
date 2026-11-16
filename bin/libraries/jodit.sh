# file generated with AI assistance: Claude Code - 2026-11-16 16:10:00 UTC
#
# Recipe for bin/fetch-libraries. The "fat" build (es2018) is what Jedison's
# demo uses: it bundles all languages, the icons (inline SVG) and the plugins,
# and its CSS embeds images as data: URIs, so no further files are requested at
# runtime. The full package is ~27 MB; only the two minified files and the
# licence are copied. The trailing sourceMappingURL comments are dropped
# because no source maps are shipped.
PACKAGE=jodit
VERSION=4.2.50
INTEGRITY='sha512-fEOyJyLzOTE20qeu0tXItcI8kGWOVWOcPsABMlkGXrhq+Lee9RXNSLshK6xScudh+n/AvRS0DisAPcm2fx5JmQ=='
TARGET=jx/jodit/vendor

install_files() {
    sed '/^\/\/# sourceMappingURL=/d' "$PKG/es2018/jodit.fat.min.js" \
        > "$TARGET/jodit.fat.min.js"
    sed '/^\/\*# sourceMappingURL=.*\*\/$/d' "$PKG/es2018/jodit.fat.min.css" \
        > "$TARGET/jodit.fat.min.css"
    cp "$PKG/LICENSE.txt" "$TARGET/LICENSE.txt"
}

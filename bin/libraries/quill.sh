# file generated with AI assistance: Claude Code - 2026-11-16 16:02:59 UTC
#
# Recipe for bin/fetch-libraries. Quill 2.x with the snow theme. The package
# ships no minified build; dist/quill.js is what the Jedison demo loads.
# Trailing sourceMappingURL comments are dropped because no source maps are
# shipped and the browser would otherwise request a missing file.
PACKAGE=quill
VERSION=2.0.3
INTEGRITY='sha512-xEYQBqfYx/sfb33VJiKnSJp8ehloavImQ2A6564GAbqG55PGw1dAWUn1MUbQB62t0azawUS2CZZhWCjO8gRvTw=='
TARGET=jx/quill/vendor

install_files() {
    sed '/^\/\/# sourceMappingURL=/d' "$PKG/dist/quill.js" \
        > "$TARGET/quill.js"
    sed '/^\/\*# sourceMappingURL=.*\*\/ *$/d' "$PKG/dist/quill.snow.css" \
        > "$TARGET/quill.snow.css"
    cp "$PKG/LICENSE" "$TARGET/LICENSE"
}

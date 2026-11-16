# file generated with AI assistance: Claude Code - 2026-11-16 16:02:59 UTC
#
# Recipe for bin/fetch-libraries. IMask 6.x, the major the Jedison demo uses.
# Trailing sourceMappingURL comments are dropped because no source maps are
# shipped and the browser would otherwise request a missing file.
PACKAGE=imask
VERSION=6.6.3
INTEGRITY='sha512-a9MTDhm+ET4G2IRcdUGuVTXHS05WsRNPGM5CeNJnXiXuoi4zv7g0/UDFLlRF4lBBeb8EWds4C4JVwhI0nuAIug=='
TARGET=jx/imask/vendor

install_files() {
    sed '/^\/\/# sourceMappingURL=/d' "$PKG/dist/imask.min.js" \
        > "$TARGET/imask.min.js"
    cp "$PKG/LICENSE" "$TARGET/LICENSE"
}

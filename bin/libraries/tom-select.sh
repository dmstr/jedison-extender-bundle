# file generated with AI assistance: Claude Code - 2026-11-16 16:02:59 UTC
#
# Recipe for bin/fetch-libraries. Tom Select 2.x, complete build (all plugins)
# as in the Jedison demo.
# Trailing sourceMappingURL comments are dropped because no source maps are
# shipped and the browser would otherwise request a missing file.
PACKAGE=tom-select
VERSION=2.7.0
INTEGRITY='sha512-i/iuTfrzYfGPAILfgPdU9/CCSm8XcnomjO2bHjAcFQPV7od0ghVngjaMfB5x5Agkrpe+49/tK6soegrwWyi6Lg=='
TARGET=jx/tom-select/vendor

install_files() {
    sed '/^\/\/# sourceMappingURL=/d' "$PKG/dist/js/tom-select.complete.min.js" \
        > "$TARGET/tom-select.complete.min.js"
    sed '/^\/\*# sourceMappingURL=.*\*\/ *$/d' "$PKG/dist/css/tom-select.min.css" \
        > "$TARGET/tom-select.min.css"
    cp "$PKG/LICENSE" "$TARGET/LICENSE"
}

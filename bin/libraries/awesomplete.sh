# file generated with AI assistance: Claude Code - 2026-11-16 15:54:43 UTC
#
# Recipe for bin/fetch-libraries. Awesomplete belongs to jx/core and is always
# enabled. The trailing sourceMappingURL comments are dropped because no source
# maps are shipped and the browser would otherwise request a missing file.
PACKAGE=awesomplete
VERSION=1.1.7
INTEGRITY='sha512-hwClzFReIIfheoMWpoJ7juLp1o6Q7YjTtEdZIGfXvhRHA9ewKM03VS4ZoK+OEFIKM066PfQt9pzRIGV1TguRBw=='
TARGET=jx/core/vendor

install_files() {
    sed '/^\/\/# sourceMappingURL=/d' "$PKG/awesomplete.min.js" \
        > "$TARGET/awesomplete.min.js"
    sed '/^\/\*# sourceMappingURL=.*\*\/$/d' "$PKG/awesomplete.css" \
        > "$TARGET/awesomplete.css"
    cp "$PKG/LICENSE" "$TARGET/LICENSE"
}

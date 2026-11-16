# file generated with AI assistance: Claude Code - 2026-11-16 16:10:00 UTC
#
# Recipe for bin/fetch-libraries. Ace ships as the "noconflict" minified build
# (it defines the global `ace`, which Jedison checks). The full package is
# ~59 MB, so only ace.js, a FIXED selection of modes and themes and the licence
# are copied; keep the list in jx/ace/index.js in sync. No workers, no
# extensions, no snippets, no keybindings.
PACKAGE=ace-builds
VERSION=1.32.9
INTEGRITY='sha512-dqBLPj//Gq0b92YUtRIsdWsORf4J+4xW3r8/4Wr2Vqid7O1j7YBV/ZsVvWBjZFy+EnvMCRFCFOEIM1cbt4BQ/g=='
TARGET=jx/ace/vendor

MODES="text json javascript html css markdown xml yaml sql"
THEMES="chrome github github_dark monokai tomorrow"

install_files() {
    local name
    # sed drops the trailing sourceMappingURL comment, if a file has one.
    sed '/^\/\/# sourceMappingURL=/d' "$PKG/src-min-noconflict/ace.js" \
        > "$TARGET/ace.js"
    for name in $MODES; do
        sed '/^\/\/# sourceMappingURL=/d' \
            "$PKG/src-min-noconflict/mode-$name.js" > "$TARGET/mode-$name.js"
    done
    for name in $THEMES; do
        sed '/^\/\/# sourceMappingURL=/d' \
            "$PKG/src-min-noconflict/theme-$name.js" > "$TARGET/theme-$name.js"
    done
    cp "$PKG/LICENSE" "$TARGET/LICENSE"
}

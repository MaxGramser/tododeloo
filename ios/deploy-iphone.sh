#!/usr/bin/env bash
# Build, install and launch Tododeloo on a physical iPhone in one command.
# Usage: ./deploy-iphone.sh [device-udid]
# Defaults to Max's iPhone; pass a UDID to target another device
# (find it with: xcrun xctrace list devices).
set -euo pipefail
cd "$(dirname "$0")"

DEVICE="${1:-00008130-001838142242001C}"
DD=/tmp/tododeloo-dev

echo "→ Device: $DEVICE"
echo "→ Bouwen…"
xcodebuild -project Tododeloo.xcodeproj -scheme Tododeloo -configuration Debug \
    -destination "id=$DEVICE" -derivedDataPath "$DD" -allowProvisioningUpdates build

APP="$DD/Build/Products/Debug-iphoneos/Tododeloo.app"
echo "→ Installeren…"
xcrun devicectl device install app --device "$DEVICE" "$APP"

echo "→ Starten…"
xcrun devicectl device process launch --device "$DEVICE" com.tododeloo.app

echo "✓ Klaar — Tododeloo draait op je iPhone."

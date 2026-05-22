---
name: ios-app
description: "Use when building, running, installing, deploying, or debugging the Tododeloo iOS/Mac app in ios/, or touching its Xcode project, signing, Siri App Intents, or device install. Covers deploying to a physical iPhone (ios/deploy-iphone.sh), the simulator build command, Mac Catalyst being intentionally off, the Apple Team ID living in a git-ignored Local.xcconfig, file-system-synchronized groups (no pbxproj edits to add Swift files), bundle id com.tododeloo.app, the production API URL, and password autofill (apple-app-site-association / APPLE_APP_ID). Do NOT use for the Laravel backend or the Vue web app."
metadata:
  author: tododeloo
---

# Tododeloo iOS app

Native SwiftUI client in `ios/` (iOS 17+, also runnable on Mac). Talks to the
Laravel JSON API with Sanctum bearer tokens; defaults to the production URL.

## Deploy to a physical iPhone (reliable, one command)

```bash
cd ios && ./deploy-iphone.sh        # optional: ./deploy-iphone.sh <device-udid>
```

Builds (Debug, device), installs and launches via `xcrun devicectl`. Use this —
it is more reliable than Xcode's Run button in this project.

In Xcode instead: pick the real device (e.g. **iPhone van max**) in the
destination menu — NOT "Any iOS Device" (that only builds, never installs) — and
press **Run (⌘R)**, not Build (⌘B). Quit and reopen Xcode after editing
`project.pbxproj` so it reloads.

## Verify a build without a device

```bash
cd ios && xcodebuild build -scheme Tododeloo -sdk iphonesimulator \
  -destination 'generic/platform=iOS Simulator' CODE_SIGNING_ALLOWED=NO
```

## Project facts & gotchas

- **Mac Catalyst is intentionally OFF** (`SUPPORTS_MACCATALYST` absent,
  `TARGETED_DEVICE_FAMILY = "1,2"`). If re-enabled, Xcode auto-selects
  "My Mac" as the run target and the iPhone never gets installed. Leave it off.
- **File-system-synchronized groups** (objectVersion 77): new Swift files placed
  under `ios/Tododeloo/` are included automatically — do NOT hand-edit
  `project.pbxproj` just to add sources.
- **Team ID is local-only**: `ios/Config/Local.xcconfig` (git-ignored) holds
  `DEVELOPMENT_TEAM`, loaded via `Config/Signing.xcconfig`. On a fresh clone:
  `cp ios/Config/Local.xcconfig.example ios/Config/Local.xcconfig` and set the
  Team ID. Never hardcode `DEVELOPMENT_TEAM` in `project.pbxproj` — it was
  scrubbed from git history.
- **Bundle id** `com.tododeloo.app`, automatic signing.
- **API URL** defaults to `https://tododeloo.on-forge.com` (`AppConfig.swift`),
  overridable at runtime via the in-app Server sheet (stored in UserDefaults).
  DEBUG builds trust self-signed certs only for `.test` hosts (local Herd).

## Siri (App Intents)

- `QuickAddTodoIntent` + `TododelooShortcuts` register phrases like
  "Voeg toe aan Tododeloo"; Siri then asks for the task and it lands on today.
- iOS does NOT allow free-form text inside an App Shortcut trigger phrase
  (phrase parameters must be `AppEnum`/`AppEntity`). Adding a `String` param to a
  phrase makes `appintentsmetadataprocessor` fail the build. So one-breath
  "voeg <x> toe aan Tododeloo" is impossible via App Shortcuts — for that, use a
  Shortcuts-app shortcut with a "Dictate Text" step feeding the intent.
- The intent reads the token from the Keychain (shared with the app) and posts to
  `/api/quick-add`.

## Password autofill (Associated Domains)

- Entitlement `ios/Tododeloo.entitlements`: `webcredentials:tododeloo.on-forge.com`.
- Backend serves `/.well-known/apple-app-site-association` (route in
  `routes/web.php`); the app id comes from the `APPLE_APP_ID` env var. Set it on
  Forge: `APPLE_APP_ID=<TEAMID>.com.tododeloo.app`, deploy, then reinstall the app.

## When Xcode rewrites project.pbxproj

Opening the project can reformat `project.pbxproj`. Re-check afterwards that:
Mac Catalyst stays off, no `DEVELOPMENT_TEAM` line was re-added (it belongs in
`Local.xcconfig`), and the bundle id is still `com.tododeloo.app`.

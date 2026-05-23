import AppKit
import Carbon.HIToolbox
import SwiftUI

/// Owns the system-wide quick-capture hotkey (⌃⌥⌘Space) and the floating panel it
/// opens — capture a todo from anywhere without switching to the app, the
/// Akiflow/TickTick move. The Mac target is non-sandboxed, so a Carbon hotkey
/// works without extra permissions.
final class AppDelegate: NSObject, NSApplicationDelegate {
    private var hotKey: GlobalHotKey?
    private let capture = CapturePanelController()

    func applicationDidFinishLaunching(_ notification: Notification) {
        hotKey = GlobalHotKey(
            keyCode: UInt32(kVK_Space),
            modifiers: UInt32(controlKey | optionKey | cmdKey)
        ) { [weak self] in
            self?.capture.toggle()
        }
    }
}

/// A registered Carbon hot key. Holds its registration for the app's lifetime;
/// the shared C event handler routes presses back to the matching instance.
final class GlobalHotKey {
    private var ref: EventHotKeyRef?
    private let callback: () -> Void
    private let identifier: UInt32

    private static var registry: [UInt32: GlobalHotKey] = [:]
    private static var nextID: UInt32 = 1
    private static var handlerInstalled = false

    init(keyCode: UInt32, modifiers: UInt32, callback: @escaping () -> Void) {
        self.callback = callback
        self.identifier = Self.nextID
        Self.nextID += 1
        Self.registry[identifier] = self

        Self.installHandlerIfNeeded()

        let hotKeyID = EventHotKeyID(signature: OSType(0x5444_4C4F /* 'TDLO' */), id: identifier)
        RegisterEventHotKey(keyCode, modifiers, hotKeyID, GetApplicationEventTarget(), 0, &ref)
    }

    deinit {
        if let ref { UnregisterEventHotKey(ref) }
        Self.registry[identifier] = nil
    }

    private static func installHandlerIfNeeded() {
        guard !handlerInstalled else { return }
        handlerInstalled = true

        var spec = EventTypeSpec(eventClass: OSType(kEventClassKeyboard), eventKind: UInt32(kEventHotKeyPressed))
        InstallEventHandler(GetApplicationEventTarget(), { _, event, _ -> OSStatus in
            guard let event else { return noErr }
            var pressedID = EventHotKeyID()
            GetEventParameter(
                event,
                EventParamName(kEventParamDirectObject),
                EventParamType(typeEventHotKeyID),
                nil,
                MemoryLayout<EventHotKeyID>.size,
                nil,
                &pressedID
            )
            GlobalHotKey.registry[pressedID.id]?.callback()
            return noErr
        }, 1, &spec, nil, nil)
    }
}

/// Lazily-built floating panel that hosts the SwiftUI capture field.
final class CapturePanelController {
    private var panel: NSPanel?

    func toggle() {
        if let panel, panel.isVisible {
            panel.orderOut(nil)
        } else {
            present()
        }
    }

    private func present() {
        if panel == nil {
            let view = CaptureView { [weak self] in self?.panel?.orderOut(nil) }
            let panel = NSPanel(
                contentRect: NSRect(x: 0, y: 0, width: 460, height: 76),
                styleMask: [.titled, .fullSizeContentView, .nonactivatingPanel],
                backing: .buffered,
                defer: false
            )
            panel.titleVisibility = .hidden
            panel.titlebarAppearsTransparent = true
            panel.isMovableByWindowBackground = true
            panel.isFloatingPanel = true
            panel.level = .floating
            panel.hidesOnDeactivate = true
            panel.contentViewController = NSHostingController(rootView: view)
            self.panel = panel
        }
        NSApplication.shared.activate(ignoringOtherApps: true)
        panel?.center()
        panel?.makeKeyAndOrderFront(nil)
    }
}

/// The capture field itself: type a todo, press return, it lands via /quick-add
/// (Dutch parser included). Escape closes.
struct CaptureView: View {
    let onClose: () -> Void
    @State private var text = ""
    @State private var isBusy = false
    @FocusState private var focused: Bool

    var body: some View {
        HStack(spacing: 12) {
            AccentDot(size: 8)
            TextField("Snel toevoegen aan Tododeloo…", text: $text)
                .textFieldStyle(.plain)
                .font(.system(size: 18, weight: .semibold))
                .foregroundStyle(Theme.ink)
                .focused($focused)
                .onSubmit(submit)
            if isBusy {
                ProgressView().controlSize(.small)
            }
        }
        .padding(.horizontal, 20)
        .padding(.vertical, 18)
        .frame(width: 460)
        .background(Theme.background)
        .onAppear { focused = true }
        .onExitCommand(perform: onClose)
    }

    private func submit() {
        let title = text.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !title.isEmpty else { onClose(); return }
        guard APIClient.shared.isAuthenticated else { onClose(); return }
        isBusy = true
        Task {
            _ = try? await APIClient.shared.quickAdd(title: title)
            isBusy = false
            text = ""
            onClose()
        }
    }
}
